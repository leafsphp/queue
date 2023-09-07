<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;

class DeleteJobCommand extends Command
{
    protected static $defaultName = 'd:job';
    public $description = 'Delete a job class';
    public $help = 'Delete a job class';

    protected function config()
    {
        $this->setArgument('job', 'required');
    }

    protected function handle()
    {
        $job = $this->argument('config');

        $this->writeln("Deleting job $job");
    }
}
