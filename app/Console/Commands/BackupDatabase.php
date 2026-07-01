<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature   = 'db:backup';
    protected $description = 'Esegue un backup del database PostgreSQL (percorso e retention configurabili in config/backup.php)';

    /**
     * Given the existing backup files (any order) and how many to keep,
     * return the list of files that should be deleted (oldest first).
     * Extracted as a pure function so it can be unit-tested.
     *
     * @param  array<int,string>  $files
     * @return array<int,string>
     */
    public static function filesToPrune(array $files, int $keep): array
    {
        sort($files); // filenames are timestamped, so lexical sort == chronological
        return array_slice($files, 0, max(0, count($files) - max(0, $keep)));
    }

    public function handle(): int
    {
        $host     = config('database.connections.pgsql.host');
        $port     = config('database.connections.pgsql.port', 5432);
        $db       = config('database.connections.pgsql.database');
        $user     = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $filename  = 'backup_' . now()->format('Y-m-d_His') . '.sql.gz';
        $backupDir = rtrim((string) config('backup.path', storage_path('app/backups')), '/');
        $retention = (int) config('backup.retention', 14);

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filepath = "{$backupDir}/{$filename}";

        $cmd = "PGPASSWORD=" . escapeshellarg((string) $password)
            . " pg_dump -h " . escapeshellarg((string) $host)
            . " -p " . (int) $port
            . " -U " . escapeshellarg((string) $user)
            . " " . escapeshellarg((string) $db)
            . " | gzip > " . escapeshellarg($filepath);

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error("Backup fallito (exit code: {$exitCode}).");
            return Command::FAILURE;
        }

        // Retention: keep only the most recent N backups.
        $files = glob("{$backupDir}/backup_*.sql.gz");
        if (is_array($files)) {
            foreach (self::filesToPrune($files, $retention) as $old) {
                @unlink($old);
            }
        }

        $size = round(filesize($filepath) / 1024, 1);
        $this->info("Backup completato: {$filename} ({$size} KB) in {$backupDir}");

        return Command::SUCCESS;
    }
}
