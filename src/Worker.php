<?php

namespace Leaf;

class Worker
{
    /** @var \Leaf\Queue */
    protected $queue = null;

    /**
     * @var \Leaf\Queue\Scheduler
     */
    protected $scheduler = null;

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

    public function queue($connection)
    {
        $this->queue = (new Queue())->connect($connection);

        return $this;
    }

    public function scheduler($connection)
    {
        $this->scheduler = (new Queue\Scheduler())->connect($connection);

        return $this;
    }

    public function memoryExceeded($memory)
    {
        return memory_get_usage(true) >= $this->config['memory'] * 1024 * 1024;
    }

    public function run()
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () {
            echo "Shutting down queue...\n";
            $this->scheduler->disconnect();
            $this->queue->disconnect();
            exit;
        });

        while (true) {
            if ($this->scheduler->isEnabled()) {
                $this->scheduler->writeDueSchedulesToQueue();
            }

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
            $job = (new $jobData['class']())->fromQueue($jobData, $jobConfig, $this->queue);

            if (function_exists('crash')) {
                // workers are long-running: each job starts a fresh journey
                crash()->breadcrumbs()->clear();
                crash()->leaveCrumb('job: ' . $jobData['class'], 'job', [
                    'id' => $job->getJobId(),
                ], false);
            }

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
