<?php

namespace Leaf;

use Leaf\Queue\Dispatchable;

class Batch implements Dispatchable
{
    /**
     * Job config
     */
    protected $config = [];

    /**
     * Current job
     */
    protected $job = [];

    /**
     * Data to pass to the job
     */
    protected $data = [];

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
            $this->data = $config['data'];
            unset($config['data']);
        }

        $this->config = $config;

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
        sleep($this->config['delay']);
    }

    /**
     * Check if job has expired
     */
    public function hasExpired()
    {
        return $this->job['created_at'] < time() - $this->config['expire'];
    }

    /**
     * Handle job expiry
     */
    public function handleExpiry()
    {
        echo "Job {$this->job['id']} has expired\n";
        $this->queue->pop($this->job['id']);
    }

    /**
     * Check if job has reached retry limit
     */
    public function hasReachedRetryLimit()
    {
        return $this->job['retry_count'] >= $this->config['tries'];
    }

    /**
     * Handle job retry limit
     */
    public function handleRetryLimit()
    {
        echo "Job {$this->job['id']} has reached retry limit\n";
        $this->queue->pop($this->job['id']);
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
        sleep($this->config['delayBeforeRetry'] ?? 0);
        $this->queue->retryFailedJob($this->job['id'], $this->config['retry_count']);
    }

    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $this->queue->push([
            'class' => $this->job['class'],
            'config' => json_encode($this->config),
            'status' => 'pending',
            'retry_count' => $this->job['retry_count'] + 1,
        ]);
    }

    /**
     * Pass data to the job
     */
    public static function with($data)
    {
        static::$data = $data;

        return new static;
    }

    /**
     * Create a new job
     */
    public static function create(callable $job)
    {
        //
    }

    public function handle()
    {
        //
    }

    public function stack()
    {
        return;
    }

    public function getConfig()
    {
        return [];
    }

    public function trigger()
    {
        $this->queue->setJobStatus($this->job['id'], 'processing');
        $this->handle();
    }

    public function removeFromQueue()
    {
        $this->queue->pop($this->job['id']);
    }
}
