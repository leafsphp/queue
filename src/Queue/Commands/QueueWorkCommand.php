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

    /**
     * Resolve the queue to run from the --queue option and the app's
     * queue config. Returns [queueName, connection] on success or
     * [null, errorMessage] when resolution fails. (LEAF-120)
     *
     * @param string|null $option Value of the --queue option
     * @param array|null $config The full queue module config
     * @return array{0: string|null, 1: array|string}
     */
    public static function resolveQueueConnection(?string $option, ?array $config): array
    {
        $config = $config ?? [];
        $queue = $option ?? $config['default'] ?? null;

        if (!$queue) {
            return [null, 'No queue specified and no default queue configured. Set `default` in your queue config or pass --queue.'];
        }

        if (!isset($config['connections'][$queue])) {
            return [null, "Queue '$queue' is not defined in your queue config. Available queues: " . implode(', ', array_keys($config['connections'] ?? []))];
        }

        return [$queue, $config['connections'][$queue]];
    }

    protected function handle()
    {
        $this->queueConfig = MvcConfig('queue') ?? [];

        [$queue, $connection] = static::resolveQueueConnection($this->option('queue'), $this->queueConfig);

        if ($queue === null) {
            $this->error($connection);

            return 1;
        }

        $this->writeln("Queue worker started for queue '$queue'...");

        $jobsTable = $connection['table'] ?? 'leaf_php_jobs';
        $scheduleTable = $connection['schedule.table'] ?? 'leaf_php_schedules';

        if (($connection['driver'] ?? 'database') === 'database') {
            $this->writeln('> Using database connection for queue...');

            if (!file_exists(DatabasePath("{$jobsTable}.yml"))) {
                $this->writeln("> Queue table not found. Creating queue table...");

                \Leaf\FS\File::copy(__DIR__ . '/stubs/schema.yml', DatabasePath("{$jobsTable}.yml"));
                \Leaf\FS\File::copy(__DIR__ . '/stubs/schedules.yml', DatabasePath("{$scheduleTable}.yml"));

                sprout()
                    ->process("php leaf db:migrate {$jobsTable}")
                    ->run();

                sprout()
                    ->process("php leaf db:migrate {$scheduleTable}")
                    ->run();
            }
        } else {
            $this->writeln('> Using redis connection for queue...');
        }

        (new \Leaf\Worker())
            ->queue($connection)
            ->scheduler($connection)
            ->run();

        return 0;
    }
}
