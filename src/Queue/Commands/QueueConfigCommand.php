<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;
use Aloe\Core;

class QueueConfigCommand extends Command
{
    protected static $defaultName = 'queue:config';
    public $description = 'Generate queue config';
    public $help = 'Generate queue config';

    protected function handle()
    {
        $this->comment('Generating queue config...');

        $appConfigDir = getcwd() . '/config';
        $appConfigFile = $appConfigDir . '/queue.php';

        if (!Core::isMVCProject()) {
            $appConfigDir = getcwd();
            $appConfigFile = $appConfigDir . '/queue.config.php';
        }

        if (file_exists($appConfigFile)) {
            $this->error('Queue config already exists');
            return 1;
        }

        if (!copy(__DIR__ . '/stubs/config.stub', $appConfigFile)) {
            $this->error('Failed to generate queue config');
            return 1;
        }

        $this->info('Queue config generated successfully');

        return 0;
    }
}
