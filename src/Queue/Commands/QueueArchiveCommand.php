<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;

class QueueArchiveCommand extends Command
{
    protected static $defaultName = 'queue:archive';
    public $description = 'Archive a queue job';
    public $help = 'Archive a queue job';

    protected function config()
    {
        $this->setArgument('job', 'required');
    }

    protected function handle()
    {
        $job = $this->argument('config');

        $this->writeln("Archiving job $job");
    }
}
