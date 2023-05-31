<?php

namespace Leaf;

class Queue
{
    /**
     * The queue adapter
     * @var \Leaf\Queue\Adapters\Adapter
     */
    protected $adapter;

    protected $jobs = [];

    /**
     * The queue config
     */
    protected $config = [
        'adapter' => 'db',
        'connection' => [],
        'table' => 'leafphp_queue_main',
        'workers' => 1,
        'workerConfig' => [
            'delay' => 0,
            'delayBeforeRetry' => 0,
            'expire' => 60,
            'force' => false,
            'memory' => 128,
            'quitOnEmpty' => false,
            'sleep' => 3,
            'timeout' => 60,
            'tries' => 3,
        ],
    ];

    /**
     * Config for the queue and worker
     * @param array $config The config for the queue and worker
     */
    public function config(?array $config = null)
    {
        if (!$config) {
            return $this->config;
        }

        $this->config = array_merge(
            $this->config,
            \Leaf\Config::get('queue') ?? [],
            $config
        );

        return $this->config;
    }

    /**
     * Connect to queue adapter
     * @return \Leaf\Queue
     */
    public function connect(): Queue
    {
        $config = $this->config(\Leaf\Config::get('queue') ?? []);

        $adapter = ucfirst($config['adapter']);
        $adapter = "\\Leaf\\Queue\\Adapters\\$adapter";
        
        $this->adapter = new $adapter($config);
        $this->adapter->connect($config['connection']);

        return $this;
    }

    /**
     * Get the queue adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Push job to queue
     * @param array $job The job to push to the queue
     */
    public function push(array $job)
    {
        if (!$this->adapter) {
            $this->connect();
        }

        $this->adapter->pushJobToQueue($job);
    }

    /**
     * Pop job from queue
     * @param string|int $id The id of the job to pop
     */
    public function pop($id)
    {
        if (!$this->adapter) {
            $this->connect();
        }

        $this->adapter->popJobFromQueue($id);
    }

    /**
     * Set job status
     * 
     * @param string|int $id The id of the job to set status
     * @param string $status The status to set
     */
    public function setJobStatus($id, $status)
    {
        if (!$this->adapter) {
            $this->connect();
        }

        $this->adapter->setJobStatus($id, $status);
    }

    /**
     * Get all jobs for processing
     * @return object
     */
    public function getNextJob()
    {
        if (!$this->adapter) {
            $this->connect();
        }

        return $this->adapter->getNextJob();
    }

    /**
     * Mark job as failed
     * @param string|int $id The id of the job to mark as failed
     */
    public function markJobAsFailed($id)
    {
        if (!$this->adapter) {
            $this->connect();
        }

        $this->adapter->markJobAsFailed($id);
    }

    /**
     * Retry failed job
     * @param string|int $id The id of the job to retry
     * @param string|int $retryCount The number of times the job has been retried
     */
    public function retryFailedJob($id, $retryCount = 0)
    {
        if (!$this->adapter) {
            $this->connect();
        }

        $this->adapter->retryFailedJob($id, $retryCount);
    }

    /**
     * Initialize a worker to work the queue
     */
    public function run()
    {
        for ($i = 0; $i < $this->config['workers']; $i++) {
            $worker = new Worker();
            $worker
                ->config($this->config['workerConfig'])
                ->queue($this)
                ->run();
        }
    }
}
