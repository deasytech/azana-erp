<?php

namespace App\Domain\Backup\Drivers;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/** SQLite: a consistent copy of the file (VACUUM INTO), compressed. A restore test opens the copy and checks its integrity. */
class SqliteBackupDriver implements BackupDriver
{
    public function __construct(private readonly string $connection) {}

    public function dump(string $gzPath): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'azana-dump');
        @unlink($copy);   // VACUUM INTO needs a path that does not exist

        try {
            DB::connection($this->connection)->getPdo()->exec('VACUUM INTO '.DB::connection($this->connection)->getPdo()->quote($copy));
            $this->gzip($copy, $gzPath);
        } finally {
            @unlink($copy);
        }
    }

    public function restoreScratch(string $gzPath): array
    {
        $copy = tempnam(sys_get_temp_dir(), 'azana-restore');

        try {
            $this->gunzip($gzPath, $copy);

            $pdo = new PDO('sqlite:'.$copy, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $check = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $check === 'ok' || throw new RuntimeException("The restored copy failed its integrity check: {$check}");

            $counts = [];

            foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
                $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $table).'"')->fetchColumn();
            }

            return $counts;
        } finally {
            unset($pdo);
            @unlink($copy);
        }
    }

    private function gzip(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb9');

        if (! $in || ! $out) {
            throw new RuntimeException('Could not write the backup file.');
        }

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }

        fclose($in);
        gzclose($out);
    }

    private function gunzip(string $from, string $to): void
    {
        $in = gzopen($from, 'rb');
        $out = fopen($to, 'wb');

        if (! $in || ! $out) {
            throw new RuntimeException('Could not read the backup file.');
        }

        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1 << 20));
        }

        gzclose($in);
        fclose($out);
    }
}
