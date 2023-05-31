<?php

namespace Leaf;

class Job
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
     * Queue instance
     */
    protected $queue = null;

    /**
     * Load a job for running
     */
    public function __construct($job, $config, $queue)
    {
        $this->job = $job;
        $this->config = $config;
        $this->queue = $queue;
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
    
    public static function dispatch($config = [])
    {
        $queue = new \Leaf\Queue();

        return $queue->push([
            'class' => get_called_class(),
            'config' => json_encode(array_merge($queue->config()['workerConfig'] ?? [], $config)),
            'status' => 'pending',
            'retry_count' => 0,
        ]);
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
