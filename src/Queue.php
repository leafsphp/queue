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
    protected $connection = [];

    /**
     * Connect to queue adapter
     * @param array $connection The connection to use
     * @return \Leaf\Queue
     */
    public function connect($connection): Queue
    {
        $adapter = ucfirst($connection['driver']);
        $adapter = "\\Leaf\\Queue\\Adapters\\$adapter";

        $this->adapter = (new $adapter())->connect($connection);

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
        $this->adapter->pushJobToQueue($job);
    }

    /**
     * Pop job from queue
     * @param string|int $id The id of the job to pop
     */
    public function pop($id)
    {
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
        $this->adapter->setJobStatus($id, $status);
    }

    /**
     * Get all jobs for processing
     * @return object
     */
    public function getNextJob()
    {
        return $this->adapter->getNextJob();
    }

    /**
     * Mark job as failed
     * @param string|int $id The id of the job to mark as failed
     */
    public function markJobAsFailed($id)
    {
        $this->adapter->markJobAsFailed($id);
    }

    /**
     * Retry failed job
     * @param string|int $id The id of the job to retry
     * @param string|int $retryCount The number of times the job has been retried
     */
    public function retryFailedJob($id, $retryCount = 0)
    {
        $this->adapter->retryFailedJob($id, $retryCount);
    }

    /**
     * Return queue commands
     */
    public static function commands()
    {
        return [
            \Leaf\Queue\Commands\DeleteJobCommand::class,
            \Leaf\Queue\Commands\GenerateJobCommand::class,
            \Leaf\Queue\Commands\QueueWorkCommand::class,
        ];
    }
}
