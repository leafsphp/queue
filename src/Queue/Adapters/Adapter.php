<?php

namespace Leaf\Queue\Adapters;

/**
 * Database adapter
 * -----
 * Db adapter for the worker
 */
interface Adapter
{
    /**
     * Connect to queue storage
     * @param array $connection Credentials for the queue storage
     */
    public function connect($connection);

    /**
     * Push job to queue
     * @param array $job The job to push to the queue
     */
    public function pushJobToQueue($job);

    /**
     * Pop job from queue
     * @param string|int $id The id of the job to pop
     */
    public function popJobFromQueue($id);

    /**
     * Set job status
     *
     * @param string|int $id The id of the job to set status
     * @param string $status The status to set
     */
    public function setJobStatus($id, $status);

    /**
     * Get all jobs for processing
     * @return array
     */
    public function getJobs();

    /**
     * Get next job for processing
     * @return object
     */
    public function getNextJob();

    /**
     * Mark job as failed
     */
    public function markJobAsFailed($id);

    /**
     * Retry failed job
     */
    public function retryFailedJob($id, $retryCount);
}
