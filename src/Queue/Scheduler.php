<?php

namespace Leaf\Queue;

/**
 * Leaf Queue Scheduler (DB Only)
 * ---
 * Scheduler checks the queue schedules table and pops them unto the main queue when due
 */
class Scheduler
{
    protected bool $enabled = false;

    /**
     * @var \Leaf\Db|\Leaf\Redis
     */
    protected $adapter = null;

    protected array $connection = [];

    protected array $schedule = [];

    /**
     * Check if the scheduler is enabled
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Connect to queue driver ONLY if there are jobs that need scheduling
     * @param array $connection The connection config
     * @return $this
     */
    public function connect($connection)
    {
        if ($connection['driver'] !== 'database') {
            return $this;
        }

        foreach (glob(AppPaths('jobs') . '/*.php') as $file) {
            require $file;

            /**@var \Leaf\Job */
            $job = new ("App\\Jobs\\" . pathinfo($file, PATHINFO_FILENAME))();

            if ($schedule = $job->schedule()) {
                $this->schedule[] = [
                    'class' => get_class($job),
                    'schedule' => is_string($schedule) ? $schedule : $schedule->getSchedule(),
                    'next_run' => $this->getNextRunTime(is_string($schedule) ? $schedule : $schedule->getSchedule()),
                ];

                $this->enabled = true;
            }
        }

        if ($this->enabled) {
            $appDbConfig = MvcConfig('database');
            $dbConnection = $appDbConfig['connections'][$connection['connection']] ?? $appDbConfig['connections'][$appDbConfig['default']];

            $this->adapter = new \Leaf\Db();
            $this->adapter->connect([
                'dbtype' => $dbConnection['driver'] ?? 'mysql',
                'charset' => $dbConnection['charset'] ?? null,
                'port' => $dbConnection['port'] ?? null,
                'unixSocket' => $dbConnection['unixSocket'] ?? null,
                'host' => $dbConnection['host'] ?? '127.0.0.1',
                'username' => $dbConnection['username'] ?? 'root',
                'password' => $dbConnection['password'] ?? '',
                'dbname' => $dbConnection['database'] ?? '',
            ]);

            $this->enabled = true;
            $this->connection = $connection;
            $this->connection['table'] = $connection['schedule.table'] ?? 'leaf_php_schedules';

            $this->writeScheduleToAdapter();
        }

        return $this;
    }

    /**
     * Write schedules to the adapter
     * @return void
     */
    protected function writeScheduleToAdapter()
    {
        foreach ($this->schedule as $schedule) {
            $existing = $this->adapter->select($this->connection['table'])
                ->where('class', $schedule['class'])
                ->first();

            if ($existing) {
                $this->adapter->update($this->connection['table'])
                    ->params([
                        'schedule' => $schedule['schedule'],
                    ])
                    ->where('class', $schedule['class'])
                    ->execute();
            } else {
                $this->adapter->insert($this->connection['table'])
                    ->params($schedule)
                    ->execute();
            }
        }
    }

    /**
     * Push due schedules to the main queue
     * @return void
     */
    public function writeDueSchedulesToQueue()
    {
        if (!$this->enabled) {
            return;
        }

        $dueSchedules = $this->adapter->select($this->connection['table'])
            ->where('next_run', '<=', date('Y-m-d H:i:s'))
            ->get();

        foreach ($dueSchedules as $schedule) {
            dispatch($schedule['class']);

            $this->adapter->update($this->connection['table'])
                ->params([
                    'next_run' => $this->getNextRunTime($schedule['schedule']),
                    'last_run' => date('Y-m-d H:i:s'),
                    'run_count' => $schedule['run_count'] + 1,
                ])
                ->where('class', $schedule['class'])
                ->execute();
        }
    }

    /**
     * Get the next run time for a cron expression
     * @param string $cronExpression The cron expression
     * @return string The next run time in Y-m-d H:i:s format
     */
    protected function getNextRunTime(string $cronExpression): string
    {
        $cron = new \Cron\CronExpression($cronExpression);
        return $cron->getNextRunDate()->format('Y-m-d H:i:s');
    }

    /**
     * Check if a schedule is due
     * @param string $cronExpression The cron expression
     * @return bool True if due, false otherwise
     */
    protected function isDue(string $cronExpression): bool
    {
        $cron = new \Cron\CronExpression($cronExpression);
        return $cron->isDue();
    }

    /**
     * Disconnect from adapter and shut down scheduler
     */
    public function disconnect()
    {
        if ($this->adapter) {
            $this->adapter->close();
        }

        $this->enabled = false;
    }
}
