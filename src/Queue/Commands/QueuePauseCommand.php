<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;

class QueuePauseCommand extends Command
{
    protected static $defaultName = 'queue:pause';
    public $description = 'Pause the execution of the queue jobs';
    public $help = 'Pause the execution of the queue jobs';

    protected function handle()
    {
        $this->writeln('Pausing queue jobs');
    }
}
