<?php

namespace Leaf\Queue\Commands;

use Illuminate\Support\Str;
use Leaf\Sprout\Command;

class DeleteJobCommand extends Command
{
    protected $signature = 'd:job
        {job : job name}';
    protected $description = 'Delete a job class';
    protected $help = 'Delete a job class';

    protected function handle()
    {
        $job = Str::studly(Str::singular($this->argument('job')));

        if (!strpos($job, 'Job')) {
            $job .= 'Job';
        }

        $file = getcwd() . DIRECTORY_SEPARATOR . AppPaths('jobs') . "/$job.php";

        if (!\Leaf\FS\File::exists($file)) {
            $this->error("$job doesn't exist");
            return 1;
        }

        \Leaf\FS\File::delete($file);

        $this->comment("$job deleted successfully");

        return 0;
    }
}
