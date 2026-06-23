<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature   = 'db:backup';
    protected $description = 'Esegue un backup del database PostgreSQL in storage/app/backups/';

    public function handle(): int
    {
        $host     = config('database.connections.pgsql.host');
        $port     = config('database.connections.pgsql.port', 5432);
        $db       = config('database.connections.pgsql.database');
        $user     = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $filename  = 'backup_' . now()->format('Y-m-d_His') . '.sql.gz';
        $backupDir = storage_path('app/backups');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filepath = "{$backupDir}/{$filename}";

        $cmd = "PGPASSWORD=" . escapeshellarg($password)
            . " pg_dump -h " . escapeshellarg($host)
            . " -p " . (int) $port
            . " -U " . escapeshellarg($user)
            . " " . escapeshellarg($db)
            . " | gzip > " . escapeshellarg($filepath);

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error("Backup fallito (exit code: {$exitCode}).");
            return Command::FAILURE;
        }

        // Keep only the last 14 backups
        $files = glob("{$backupDir}/backup_*.sql.gz");
        if (is_array($files)) {
            sort($files);
            $toDelete = array_slice($files, 0, max(0, count($files) - 14));
            foreach ($toDelete as $old) {
                unlink($old);
            }
        }

        $size = round(filesize($filepath) / 1024, 1);
        $this->info("Backup completato: {$filename} ({$size} KB)");

        return Command::SUCCESS;
    }
}
