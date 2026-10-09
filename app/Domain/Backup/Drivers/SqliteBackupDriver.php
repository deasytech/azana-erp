<?php

namespace App\Domain\Backup\Drivers;

use App\Domain\Backup\BackupException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** SQLite: a consistent copy of the file (VACUUM INTO), compressed. A restore test opens the copy and checks its integrity. */
class SqliteBackupDriver implements BackupDriver
{
    public function __construct(private readonly string $connection) {}

    public function dump(string $gzPath): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'azana-dump');
        @unlink($copy);   // VACUUM INTO needs a path that does not exist

        try {
            DB::connection($this->connection)->getPdo()->prepare('VACUUM INTO ?')->execute([$copy]);
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

            Config::set('database.connections.backup_restore', ['driver' => 'sqlite', 'database' => $copy, 'prefix' => '', 'foreign_key_constraints' => false]);
            DB::purge('backup_restore');
            $restored = DB::connection('backup_restore');

            $check = array_values((array) $restored->selectOne('PRAGMA integrity_check'))[0] ?? null;
            $check === 'ok' || throw new BackupException("The restored copy failed its integrity check: {$check}");

            $counts = [];

            foreach (Schema::connection('backup_restore')->getTableListing(schemaQualified: false) as $table) {
                $counts[$table] = $restored->table($table)->count();
            }

            return $counts;
        } finally {
            DB::purge('backup_restore');
            @unlink($copy);
        }
    }

    private function gzip(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb9');

        if (! $in || ! $out) {
            throw new BackupException('Could not write the backup file.');
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
            throw new BackupException('Could not read the backup file.');
        }

        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1 << 20));
        }

        gzclose($in);
        fclose($out);
    }
}
