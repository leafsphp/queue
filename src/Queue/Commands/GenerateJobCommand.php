<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;
use Illuminate\Support\Str;

class GenerateJobCommand extends Command
{
    protected static $defaultName = 'g:job';
    public $description = 'Create a job class';
    public $help = 'Generate a new job class';

    protected function config()
    {
        $this->setArgument('job', 'required', 'job name');
    }

    protected function handle()
    {
        $job = Str::studly(Str::singular($this->argument('job')));

        if (!strpos($job, 'Job')) {
            $job .= 'Job';
        }

        $file = \Aloe\Command\Config::rootpath(AppPaths('jobs') . "/$job.php");

        if (file_exists($file)) {
            $this->error("$job already exists");
            return 1;
        }

        touch($file);

        $fileContent = \file_get_contents(__DIR__ . '/stubs/job.stub');
        $fileContent = str_replace('ClassName', $job, $fileContent);

        file_put_contents($file, $fileContent);

        $this->comment("$job generated successfully");
        return 0;
    }
}
