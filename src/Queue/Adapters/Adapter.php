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
     * @param string|int $id The id of the job to mark as failed
     * @param string|null $exception The exception that caused the failure
     */
    public function markJobAsFailed($id, $exception = null);

    /**
     * Retry failed job
     * @param string|int $id The id of the job to retry
     * @param string|int $retryCount The number of times the job has been retried
     * @param int $delay Seconds to wait before the job becomes available again
     */
    public function retryFailedJob($id, $retryCount, $delay = 0);

    /**
     * Reset jobs stuck in 'processing' (eg. after a worker crash) back to 'pending'
     */
    public function resetStuckJobs();

    /**
     * Disconnect
     */
    public function disconnect();
}
