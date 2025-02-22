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
    protected static $data = [];

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
     * Number of seconds to wait before processing a job
     */
    protected $delay = 0;

    /**
     * Number of seconds to wait before retrying a job that has failed.
     */
    protected $delayBeforeRetry = 0;

    /**
     * Number of seconds to wait before archiving a job that has not yet been processed
     */
    protected $expire = 60;

    /**
     * Force the worker to process the job, even if it has expired or has reached its maximum number of retries
     */
    protected $force = false;

    /**
     * The maximum amount of memory the job is allowed to consume (MB)
     */
    protected $memory = 128;

    /**
     * The number of seconds a child process can run before being killed.
     */
    protected $timeout = 60;

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

        if (isset($config['data'])) {
            static::$data = $config['data'];
        }

        $this->delay = $config['delay'] ?? 0;
        $this->delayBeforeRetry = $config['delayBeforeRetry'] ?? 0;
        $this->expire = $config['expire'] ?? 60;
        $this->force = $config['force'] ?? false;
        $this->memory = $config['memory'] ?? 128;
        $this->timeout = $config['timeout'] ?? 60;
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
     * Handle delay for job
     */
    public function handleDelay()
    {
        echo "Job #{$this->job['id']} is delayed for {$this->delay} seconds\n";
        sleep($this->delay);
    }

    /**
     * Check if job has expired
     */
    public function hasExpired()
    {
        return $this->job['created_at'] < (time() - $this->expire);
    }

    /**
     * Handle job expiry
     */
    public function handleExpiry()
    {
        echo "Job #{$this->job['id']} has expired\n";
        $this->queue->pop($this->job['id']);
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
     * Retry job
     */
    public function retry()
    {
        sleep($this->delayBeforeRetry ?? 0);
        $this->queue->retryFailedJob($this->job['id'], $this->job['retry_count']);
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        sleep($delay);

        $this->queue->push([
            'class' => $this->job['class'],
            'status' => 'pending',
            'retry_count' => $this->job['retry_count'] + 1,
            'config' => json_encode([
                'delay' => $this->delay,
                'delayBeforeRetry' => $this->delayBeforeRetry,
                'expire' => $this->expire,
                'force' => $this->force,
                'memory' => $this->memory,
                'timeout' => $this->timeout,
                'tries' => $this->tries,
                'data' => static::$data,
            ]),
        ]);
    }

    public static function with($data)
    {
        static::$data[] = $data;

        return new static();
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
            'force' => $this->force,
            'memory' => $this->memory,
            'timeout' => $this->timeout,
            'tries' => $this->tries,
            'data' => static::$data,
        ];
    }

    public function trigger()
    {
        $this->queue->setJobStatus($this->job['id'], 'processing');
        $this->handle(...static::$data);
    }

    public function removeFromQueue()
    {
        $this->queue->pop($this->job['id']);
    }
}
