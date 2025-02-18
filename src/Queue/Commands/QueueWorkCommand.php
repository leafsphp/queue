<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;

class QueueWorkCommand extends Command
{
    protected static $defaultName = 'queue:work';
    public $description = 'Start your queue worker';
    public $help = 'Start your queue worker';

    protected function handle()
    {
        $this->writeln('Queue worker started for queue \'default\'...');

        $queueConfig = MvcConfig('queue');

        (new \Leaf\Worker())
            ->queue($queueConfig['connections'][$queueConfig['default']])
            ->run();

        return 0;
    }
}
