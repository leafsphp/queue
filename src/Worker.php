<?php

namespace Leaf;

class Worker
{
    /** @var \Leaf\Queue */
    protected $queue = null;
    protected $config = [
        'expire' => 60,
        'delay' => 0,
        'memory' => 128,
        'timeout' => 60,
        'sleep' => 3,
        'tries' => 3,
        'force' => false,
        'quitOnEmpty' => false,
    ];

    public function queue($queue)
    {
        $this->queue = (new Queue())->connect($queue);
        return $this;
    }

    public function memoryExceeded($memory)
    {
        return memory_get_usage(true) >= $this->config['memory'] * 1024 * 1024;
    }

    public function run()
    {
        while (true) {
            $jobData = $this->queue->getNextJob();

            if (!$jobData) {
                if ($this->config['quitOnEmpty']) {
                    break;
                }

                // [ENHANCE] Would be better to use events instead of sleeping for a fixed time
                sleep($this->config['sleep']);

                continue;
            }

            $jobConfig = json_decode($jobData['config'] ?? "{}", true);

            /** @var \Leaf\Job */
            $job = (new $jobData['class'])->fromQueue($jobData, $jobConfig, $this->queue);

            $job->handleDelay();

            if ($job->hasExpired()) {
                $job->handleExpiry();

                continue;
            }

            if ($job->hasReachedRetryLimit()) {
                echo "  - Job {$job->getJobId()} has reached retry limit, marking as failed\n";
                $job->setStatus('failed');

                continue;
            }

            echo "Processing job: {$jobData['class']} --- #{$job->getJobId()}\n";

            try {
                $job->trigger();

                // [FIX] this runs after the job has been processed
                // if ($this->memoryExceeded($jobConfig['memory'])) {
                //     exit(12);
                // }

                // at this point, the job has been successfully processed
                $job->removeFromQueue();

                continue;
            } catch (\Throwable $th) {
                echo "  - Job #{$job->getJobId()} failed: {$th->getMessage()}\n";
                echo "  - Retrying job #{$job->getJobId()}...\n";

                $job->retry();

                continue;
            }
        }
    }
}
