<?php

namespace App\Domain\Backup\Drivers;

use App\Domain\Backup\BackupException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $out = gzopen($gzPath, 'wb9') ?: throw new BackupException('Could not write the backup file.');
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
            throw new BackupException('mysqldump failed: '.trim($process->getErrorOutput()));
        }

        // mysqldump ends a complete dump with this line; a dump cut short does not have it.
        str_contains($tail, 'Dump completed') || throw new BackupException('The dump looks incomplete (no end marker).');
    }

    public function restoreScratch(string $gzPath): array
    {
        $scratch = (string) config('backup.restore_test_database');

        if ($scratch === '' || $scratch === $this->config('database') || ! preg_match('/^\w+$/', $scratch)) {
            throw new BackupException('Set BACKUP_RESTORE_TEST_DATABASE to the name of a scratch database that is not the live one.');
        }

        $this->sql("DROP DATABASE IF EXISTS `{$scratch}`; CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4");

        try {
            // The file is decompressed here and streamed into the client's standard input: no shell, no command string to inject into.
            $load = new Process(
                [config('backup.mysql'), ...$this->connectionArguments(), $scratch],
                env: $this->environment(), input: $this->decompressed($gzPath), timeout: 3600,
            );
            $load->run();

            if (! $load->isSuccessful()) {
                throw new BackupException('Loading the backup failed: '.trim($load->getErrorOutput()));
            }

            return $this->count($scratch);
        } finally {
            $this->sql("DROP DATABASE IF EXISTS `{$scratch}`");
        }
    }

    /** @return \Generator<int, string> */
    private function decompressed(string $gzPath): \Generator
    {
        $in = gzopen($gzPath, 'rb') ?: throw new BackupException('Could not read the backup file.');

        try {
            while (! gzeof($in)) {
                yield (string) gzread($in, 1 << 20);
            }
        } finally {
            gzclose($in);
        }
    }

    /** @return array<string, int> */
    private function count(string $database): array
    {
        $live = Config::get("database.connections.{$this->connection}");
        Config::set('database.connections.backup_restore', array_merge($live, ['database' => $database]));
        DB::purge('backup_restore');

        try {
            $counts = [];

            foreach (Schema::connection('backup_restore')->getTableListing($database, false) as $table) {
                $counts[$table] = DB::connection('backup_restore')->table($table)->count();
            }

            return $counts;
        } finally {
            DB::purge('backup_restore');
        }
    }

    private function sql(string $statement): void
    {
        $process = new Process([config('backup.mysql'), ...$this->connectionArguments(), '--execute='.$statement], env: $this->environment(), timeout: 120);
        $process->run();

        $process->isSuccessful() || throw new BackupException('Could not prepare the scratch database: '.trim($process->getErrorOutput()));
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
