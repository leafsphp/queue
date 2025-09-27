<?php

namespace Leaf\Queue\Commands;

use Illuminate\Support\Str;
use Leaf\Sprout\Command;

class GenerateJobCommand extends Command
{
    protected $signature = 'g:job
        {job : job name}';
    protected $description = 'Create a job class';
    protected $help = 'Generate a new job class';

    protected function handle()
    {
        $rootDir = getcwd();
        $job = Str::studly(Str::singular($this->argument('job')));

        if (!strpos($job, 'Job')) {
            $job .= 'Job';
        }

        $file = $rootDir . DIRECTORY_SEPARATOR . AppPaths('jobs') . "/$job.php";

        if (!\Leaf\FS\Directory::exists($rootDir . DIRECTORY_SEPARATOR . AppPaths('jobs'))) {
            \Leaf\FS\Directory::create($rootDir . DIRECTORY_SEPARATOR . AppPaths('jobs'), [
                'recursive' => true,
            ]);
        }

        if (\Leaf\FS\File::exists($file)) {
            $this->error("$job already exists");
            return 1;
        }

        \Leaf\FS\File::create($file, function () use ($job) {
            $fileContent = \file_get_contents(__DIR__ . '/stubs/job.stub');
            $fileContent = str_replace('ClassName', $job, $fileContent);

            return $fileContent;
        });

        $this->comment("$job generated successfully");

        return 0;
    }
}
