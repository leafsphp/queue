<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;
use Illuminate\Support\Str;

class DeleteJobCommand extends Command
{
    protected static $defaultName = 'd:job';
    public $description = 'Delete a job class';
    public $help = 'Delete a job class';

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

        if (!file_exists($file)) {
            $this->error("$job doesn't exist");

            return 1;
        }

        unlink($file);

        $this->comment("$job deleted successfully");

        return 0;
    }
}
