<?php

namespace Leaf\Queue\Commands;

use Leaf\Sprout\Command;

class QueueWorkCommand extends Command
{
    protected $queueConfig;

    protected $signature = 'queue:work
        {--queue? : The queue you want to run}';
    protected $description = 'Start your queue worker';
    protected $help = 'Start your queue worker';

    protected function handle()
    {
        $queue = $this->option('queue')
            ?? $this->queueConfig = MvcConfig('queue')['default']
            ?? null;

        $this->writeln("Queue worker started for queue '$queue'...");

        if ($this->queueConfig['connections'][$queue]['driver'] === 'database') {
            $this->writeln('> Using database connection for queue...');

            if (!file_exists(DatabasePath("{$this->queueConfig['connections'][$queue]['table']}.yml"))) {
                $this->writeln("> Queue table not found. Creating queue table...");

                \Leaf\FS\File::copy(__DIR__ . '/stubs/schema.yml', DatabasePath("{$this->queueConfig['connections'][$queue]['table']}.yml"));
                \Leaf\FS\File::copy(__DIR__ . '/stubs/schedules.yml', DatabasePath("{$this->queueConfig['connections'][$queue]['schedules.table']}.yml"));

                sprout()
                    ->process("php leaf db:migrate {$this->queueConfig['connections'][$queue]['table']}")
                    ->run();

                sprout()
                    ->process("php leaf db:migrate {$this->queueConfig['connections'][$queue]['schedules.table']}")
                    ->run();
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
