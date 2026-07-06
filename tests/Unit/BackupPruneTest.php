<?php

namespace Tests\Unit;

use App\Console\Commands\BackupDatabase;
use PHPUnit\Framework\TestCase;

class BackupPruneTest extends TestCase
{
    public function test_keeps_the_most_recent_files_and_prunes_the_rest(): void
    {
        $files = [
            '/b/backup_2026-06-01_010000.sql.gz',
            '/b/backup_2026-06-03_010000.sql.gz',
            '/b/backup_2026-06-02_010000.sql.gz',
            '/b/backup_2026-06-05_010000.sql.gz',
            '/b/backup_2026-06-04_010000.sql.gz',
        ];

        $prune = BackupDatabase::filesToPrune($files, 2);

        // Keep the 2 newest (06-04, 06-05); prune the 3 oldest.
        $this->assertSame([
            '/b/backup_2026-06-01_010000.sql.gz',
            '/b/backup_2026-06-02_010000.sql.gz',
            '/b/backup_2026-06-03_010000.sql.gz',
        ], $prune);
    }

    public function test_prunes_nothing_when_under_retention(): void
    {
        $files = [
            '/b/backup_2026-06-01_010000.sql.gz',
            '/b/backup_2026-06-02_010000.sql.gz',
        ];

        $this->assertSame([], BackupDatabase::filesToPrune($files, 14));
    }

    public function test_handles_empty_list(): void
    {
        $this->assertSame([], BackupDatabase::filesToPrune([], 14));
    }
}
