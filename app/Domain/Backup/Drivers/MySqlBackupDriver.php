<?php

namespace App\Domain\Backup\Drivers;

use Illuminate\Support\Facades\Config;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * MySQL / MariaDB through mysqldump (a single consistent transaction, with routines and triggers) and the mysql client. The password
 * is handed over in the environment, never on the command line, so it does not show in the process list or the logs.
 */
class MySqlBackupDriver implements BackupDriver
{
    public function __construct(private readonly string $connection) {}

    public function dump(string $gzPath): void
    {
        $out = gzopen($gzPath, 'wb9') ?: throw new RuntimeException('Could not write the backup file.');
        $tail = '';

        $process = new Process([
            config('backup.mysqldump'), '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4',
            ...config('backup.mysqldump_args'), ...$this->connectionArguments(), $this->config('database'),
        ], env: $this->environment(), timeout: 3600);

        $process->run(function (string $type, string $buffer) use ($out, &$tail) {
            if ($type === Process::OUT) {
                gzwrite($out, $buffer);
                $tail = substr($tail.$buffer, -300);
            }
        });

        gzclose($out);

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput()));
        }

        // mysqldump ends a complete dump with this line; a dump cut short does not have it.
        str_contains($tail, 'Dump completed') || throw new RuntimeException('The dump looks incomplete (no end marker).');
    }

    public function restoreScratch(string $gzPath): array
    {
        $scratch = (string) config('backup.restore_test_database');

        if ($scratch === '' || $scratch === $this->config('database') || ! preg_match('/^[A-Za-z0-9_]+$/', $scratch)) {
            throw new RuntimeException('Set BACKUP_RESTORE_TEST_DATABASE to the name of a scratch database that is not the live one.');
        }

        $this->sql("DROP DATABASE IF EXISTS `{$scratch}`; CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4");

        try {
            $load = Process::fromShellCommandline(
                'gzip -dc "$BACKUP_FILE" | "$BACKUP_MYSQL" --host="$BACKUP_HOST" --port="$BACKUP_PORT" --user="$BACKUP_USER" "$BACKUP_DB"',
                env: $this->environment() + ['BACKUP_FILE' => $gzPath, 'BACKUP_MYSQL' => config('backup.mysql'), 'BACKUP_HOST' => $this->config('host'),
                    'BACKUP_PORT' => $this->config('port') ?: 3306, 'BACKUP_USER' => $this->config('username'), 'BACKUP_DB' => $scratch],
                timeout: 3600,
            );
            $load->run();

            if (! $load->isSuccessful()) {
                throw new RuntimeException('Loading the backup failed: '.trim($load->getErrorOutput()));
            }

            return $this->count($scratch);
        } finally {
            $this->sql("DROP DATABASE IF EXISTS `{$scratch}`");
        }
    }

    /** @return array<string, int> */
    private function count(string $database): array
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $this->config('host'), $this->config('port') ?: 3306, $database);
        $pdo = new PDO($dsn, $this->config('username'), $this->config('password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $counts = [];

        foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `'.str_replace('`', '``', $table).'`')->fetchColumn();
        }

        return $counts;
    }

    private function sql(string $statement): void
    {
        $process = new Process([config('backup.mysql'), ...$this->connectionArguments(), '--execute='.$statement], env: $this->environment(), timeout: 120);
        $process->run();

        $process->isSuccessful() || throw new RuntimeException('Could not prepare the scratch database: '.trim($process->getErrorOutput()));
    }

    /** @return list<string> */
    private function connectionArguments(): array
    {
        return ['--host='.$this->config('host'), '--port='.($this->config('port') ?: 3306), '--user='.$this->config('username')];
    }

    /** @return array<string, string> */
    private function environment(): array
    {
        return ['MYSQL_PWD' => (string) $this->config('password')];
    }

    private function config(string $key): mixed
    {
        return Config::get("database.connections.{$this->connection}.{$key}");
    }
}
