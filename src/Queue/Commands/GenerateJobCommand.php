<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;

class GenerateJobCommand extends Command
{
    protected static $defaultName = 'g:job';
    public $description = 'Generate a job class';
    public $help = 'Generate a job class';

    protected function config()
    {
        $this->setArgument('job', 'required');
    }

    protected function handle()
    {
        $job = $this->argument('config');

        $this->writeln("Generating job $job");
    }
}
