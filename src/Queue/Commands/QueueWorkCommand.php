<?php

namespace Leaf\Queue\Commands;

use Aloe\Command;

class QueueWorkCommand extends Command
{
    protected $queueConfig;

    protected static $defaultName = 'queue:work';
    public $description = 'Start your queue worker';
    public $help = 'Start your queue worker';

    protected function config()
    {
        $this->queueConfig = MvcConfig('queue');
        $this->setOption('queue', 'queue', 'optional', 'The queue you want to run', $this->queueConfig['default']);
    }

    protected function handle()
    {
        $queue = $this->option('queue');

        $this->writeln("Queue worker started for queue '$queue'...");

        if ($this->queueConfig['connections'][$queue]['driver'] === 'database') {
            $this->writeln('> Using database connection for queue...');

            if (!file_exists(DatabasePath("{$this->queueConfig['connections'][$queue]['table']}.yml"))) {
                $this->writeln("> Queue table not found. Creating queue table...");

                \Leaf\FS\File::copy(__DIR__ . '/stubs/schema.yml', DatabasePath("{$this->queueConfig['connections'][$queue]['table']}.yml"));
                \Leaf\FS\File::copy(__DIR__ . '/stubs/schedules.yml', DatabasePath("{$this->queueConfig['connections'][$queue]['schedules.table']}.yml"));

                \Aloe\Core::run(
                    "php leaf db:migrate {$this->queueConfig['connections'][$queue]['table']}",
                    $this->output
                );
                \Aloe\Core::run(
                    "php leaf db:migrate {$this->queueConfig['connections'][$queue]['schedules.table']}",
                    $this->output
                );
            }
        } else {
            $this->writeln('> Using redis connection for queue...');
        }

        (new \Leaf\Worker())
            ->queue($this->queueConfig['connections'][$queue])
            ->scheduler($this->queueConfig['connections'][$queue])
            ->run();

        return 0;
    }
}
