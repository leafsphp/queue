<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;
use Leaf\Queue;

class QueueRunCommand extends Command
{
    protected static $defaultName = 'queue:run';
    public $description = 'Start your queue worker';
    public $help = 'Start your queue worker';

    protected function handle()
    {
        $this->writeln('Starting queue worker...');

        $queue = new Queue();
        $queueConfigFile = getcwd() . '/config/queue.php';
        $queueConfig = [];

        if (file_exists($queueConfigFile)) {
            $queueConfig = require $queueConfigFile;
        }

        if (empty($queueConfig)) {
            if (!file_exists($configFile = getcwd() . '/queue.config.php')) {
                throw new \Exception("Queue config not found");
            }

            $queueConfig = require $configFile;
        }

        $queue->config($queueConfig);
        $queue->connect();

        $this->comment("\nQueue worker started");

        $queue->run();

        return 0;
    }
}
