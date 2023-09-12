<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;
use Aloe\Core;

class QueueInstallCommand extends Command
{
    protected static $defaultName = 'queue:install';
    public $description = 'Install leaf queue';
    public $help = 'Generate and run migrations';

    protected function handle()
    {
        $this->comment('Generating queue migrations...');

        $migrationDir = getcwd() . '/app/database/migrations';
        $migrationFile = $migrationDir . '/2023_09_13_133625_create_jobs.php';

        if (!Core::isMVCProject()) {
            $migrationDir = getcwd() . '/database/migrations';

            if (!is_dir($migrationDir)) {
                mkdir($migrationDir, 0777, true);
            }

            $migrationFile = $migrationDir . '/queue.config.php';
        }

        if (file_exists($migrationFile)) {
            $this->error('Queue migration already exists');

            return 1;
        }

        if (!copy(__DIR__ . '/stubs/migration.stub', $migrationFile)) {
            $this->error('Failed to generate queue migration');

            return 1;
        }

        $this->info('Queue migration generated successfully');
        $this->comment('Running queue migration...');

        $filename = basename($migrationFile, '.php');

        if (!\Aloe\Core::run("php leaf db:migrate -f $filename", $this->output)) {
            $this->error('Failed to run queue migration');

            return 1;
        }

        $this->info('Queue migration ran successfully');

        return 0;
    }
}
