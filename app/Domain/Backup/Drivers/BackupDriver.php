<?php

namespace App\Domain\Backup\Drivers;

/** Knows how to dump one kind of database to a gzip file and how to prove that file restores. */
interface BackupDriver
{
    /** Writes a complete copy of the database to $gzPath (gzip). Throws RuntimeException when the dump fails. */
    public function dump(string $gzPath): void;

    /**
     * Loads the backup into a scratch database that is thrown away afterwards, and reports what it found.
     * Throws RuntimeException when the file cannot be restored.
     *
     * @return array<string, int> row count per table in the restored copy
     */
    public function restoreScratch(string $gzPath): array;
}
