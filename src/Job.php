<?php

namespace Leaf;

use Leaf\Queue\Dispatchable;

abstract class Job implements Dispatchable
{
    /**
     * Current job
     */
    protected $job = [];

    /**
     * Data to pass to the job
     */
    protected array $data = [];

    /**
     * Queue instance
     * @var \Leaf\Queue
     */
    protected $queue = null;

    /**
     * Configured queue connection
     */
    protected string $connection = 'default';

    /**
     * Schedule to run the job (cron or interval)
     */
    protected $schedule = null;

    /**
     * Number of seconds to wait before processing a job
     */
    protected $delay = 0;

    /**
     * Number of seconds to wait before retrying a job that has failed.
     */
    protected $delayBeforeRetry = 0;

    /**
     * Number of seconds to wait before archiving a job that has not yet been processed.
     * Set to 0 to never expire.
     */
    protected $expire = 3600;

    /**
     * The maximum number of times the job may be attempted.
     */
    protected $tries = 3;

    /**
     * Return configured connection
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * Load a job for running
     */
    public function fromQueue($job, $config, $queue)
    {
        $this->job = $job;
        $this->queue = $queue;

        $this->data = $config['data'] ?? [];

        $this->delay = $config['delay'] ?? 0;
        $this->delayBeforeRetry = $config['delayBeforeRetry'] ?? 0;
        $this->expire = $config['expire'] ?? 3600;
        $this->tries = $config['tries'] ?? 3;

        return $this;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->job['id'];
    }

    /**
     * Check if job has expired
     */
    public function hasExpired()
    {
        if ((int) $this->expire === 0) {
            return false;
        }

        return ((int) ($this->job['created_at'] ?? time())) < (time() - $this->expire);
    }

    /**
     * Handle job expiry
     */
    public function handleExpiry()
    {
        echo "Job #{$this->job['id']} has expired\n";
        $this->setStatus('expired');
    }

    /**
     * Check if job has reached retry limit
     */
    public function hasReachedRetryLimit()
    {
        return $this->job['retry_count'] >= $this->tries;
    }

    /**
     * Set job status
     */
    public function setStatus($status)
    {
        $this->queue->setJobStatus($this->job['id'], $status);
    }

    /**
     * Retry job. If the retry limit has been reached, the job
     * is marked as failed with the exception recorded instead.
     *
     * @param \Throwable|null $exception The exception that caused the failure
     */
    public function retry(?\Throwable $exception = null)
    {
        if (($this->job['retry_count'] + 1) >= $this->tries) {
            $this->fail($exception);

            return;
        }

        $this->queue->retryFailedJob(
            $this->job['id'],
            $this->job['retry_count'],
            $this->delayBeforeRetry ?? 0
        );
    }

    /**
     * Mark the job as permanently failed and record the exception
     *
     * @param \Throwable|null $exception The exception that caused the failure
     */
    public function fail(?\Throwable $exception = null)
    {
        $exceptionDump = null;

        if ($exception) {
            $trace = explode("\n", $exception->getTraceAsString());
            $exceptionDump = $exception->getMessage() . "\n" . implode("\n", array_slice($trace, 0, 5));
        }

        $this->queue->markJobAsFailed($this->job['id'], $exceptionDump);
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $this->queue->push([
            'class' => $this->job['class'],
            'status' => 'pending',
            'retry_count' => $this->job['retry_count'] + 1,
            'available_at' => time() + $delay,
            'config' => json_encode([
                'delay' => $this->delay,
                'delayBeforeRetry' => $this->delayBeforeRetry,
                'expire' => $this->expire,
                'tries' => $this->tries,
                'data' => $this->data,
            ]),
        ]);

        $this->removeFromQueue();
    }

    public static function with($data)
    {
        $instance = new static();
        $instance->data = [$data];

        return $instance;
    }

    public function stack()
    {
        return;
    }

    public function getConfig()
    {
        return [
            'delay' => $this->delay,
            'delayBeforeRetry' => $this->delayBeforeRetry,
            'expire' => $this->expire,
            'tries' => $this->tries,
            'data' => $this->data,
        ];
    }

    public function trigger()
    {
        $this->queue->setJobStatus($this->job['id'], 'processing');
        $this->handle(...$this->data);
    }

    public function removeFromQueue()
    {
        $this->queue->pop($this->job['id']);
    }

    public function schedule()
    {
        return null;
    }

    public function cron($expression)
    {
        $this->schedule = $expression;

        return $expression;
    }

    /**
     * Add a recurring interval to the job schedule
     * @param string{minute|hour|day|week|month|year} $interval
     * @throws \Exception
     * @return static
     */
    public function every(string $interval)
    {
        if (!in_array($interval, ['minute', 'hour', 'day', 'week', 'month', 'year'])) {
            throw new \Exception("Invalid interval: {$interval}");
        }

        if ($interval === 'minute') {
            $interval = '* * * * *';
        } elseif ($interval === 'hour') {
            $interval = '0 * * * *';
        } elseif ($interval === 'day') {
            $interval = '0 0 * * *';
        } elseif ($interval === 'week') {
            $interval = '0 0 * * 0';
        } elseif ($interval === 'month') {
            $interval = '0 0 1 * *';
        } elseif ($interval === 'year') {
            $interval = '0 0 1 1 *';
        }

        $this->schedule = $interval;

        return $this;
    }

    /**
     * Add a day to the job schedule (only if interval is set)
     * @param string{monday|tuesday|wednesday|thursday|friday|saturday|sunday} $day
     * @throws \Exception
     * @return static
     */
    public function on(string $day)
    {
        if (!in_array(strtolower($day), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])) {
            throw new \Exception("Invalid day: {$day}");
        }

        if ($this->schedule === null) {
            throw new \Exception("You must set an interval before setting a day. E.g. ->every('week')->on('monday')");
        }

        if (strtolower($day) === 'sunday') {
            $dayNumber = 0;
        } elseif (strtolower($day) === 'monday') {
            $dayNumber = 1;
        } elseif (strtolower($day) === 'tuesday') {
            $dayNumber = 2;
        } elseif (strtolower($day) === 'wednesday') {
            $dayNumber = 3;
        } elseif (strtolower($day) === 'thursday') {
            $dayNumber = 4;
        } elseif (strtolower($day) === 'friday') {
            $dayNumber = 5;
        } elseif (strtolower($day) === 'saturday') {
            $dayNumber = 6;
        }

        $parts = explode(' ', $this->schedule);

        if (count($parts) !== 5) {
            throw new \Exception("Invalid schedule format: {$this->schedule}");
        }

        $parts[4] = $dayNumber;

        $this->schedule = implode(' ', $parts);

        return $this;
    }

    /**
     * Add a recurring interval in minutes to the job schedule
     * @param int $minutes
     * @throws \Exception
     * @return static
     */
    public function inMinutes(int $minutes)
    {
        if ($minutes < 1 || $minutes > 59) {
            throw new \Exception("Invalid minutes: {$minutes}");
        }

        $parts = explode(' ', $this->schedule);

        if (count($parts) !== 5) {
            throw new \Exception("Invalid schedule format: {$this->schedule}");
        }

        $parts[0] = "*/{$minutes}";

        $this->schedule = implode(' ', $parts);

        return $this;
    }

    public function at(string $time)
    {
        if (!preg_match('/^(2[0-3]|[01]?[0-9]):([0-5]?[0-9])$/', $time, $matches)) {
            throw new \Exception("Invalid time format: {$time}. Expected format is HH:MM in 24-hour format.");
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        $parts = explode(' ', $this->schedule);

        if (count($parts) !== 5) {
            throw new \Exception("Invalid schedule format: {$this->schedule}");
        }

        $parts[0] = (string) $minute;
        $parts[1] = (string) $hour;

        $this->schedule = implode(' ', $parts);

        return $this;
    }

    public function getSchedule()
    {
        return $this->schedule;
    }
}
