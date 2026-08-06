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
        'expire' => 3600,
        'delay' => 0,
        'sleep' => 3,
        'tries' => 3,
        'quitOnEmpty' => false,
    ];

    /**
     * Number of loop iterations between stuck-job recovery sweeps
     */
    protected const RECOVERY_INTERVAL = 60;

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

    /**
     * Merge in worker config
     */
    public function config(array $config)
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Send a failed job to crash, if the app has it installed.
     *
     * Reporting must never be able to take the worker down, so every failure
     * here is swallowed: a job that failed is already bad news, and a broken
     * reporter turning that into a dead worker is worse.
     */
    protected function report(\Throwable $exception, $job, array $jobData): void
    {
        if (!function_exists('crash')) {
            return;
        }

        try {
            crash()->capture($exception, [
                // A worker never shuts down, so its shutdown handler never runs.
                // A buffered report would sit unsent and grow the buffer forever.
                'immediately' => true,
                'job' => [
                    'id' => $job->getJobId(),
                    'class' => $jobData['class'] ?? null,
                    'attempt' => ((int) ($jobData['retry_count'] ?? 0)) + 1,
                    'queue' => $jobData['queue'] ?? null,
                ],
            ]);
        } catch (\Throwable $reportingFailed) {
            echo "  - (could not report job #{$job->getJobId()} to crash: {$reportingFailed->getMessage()})\n";
        }
    }

    public function run()
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                echo "Shutting down queue...\n";
                $this->scheduler->disconnect();
                $this->queue->disconnect();
                exit;
            });
        }

        $iterations = 0;

        while (true) {
            if (($iterations++ % static::RECOVERY_INTERVAL) === 0) {
                $this->queue->getAdapter()->resetStuckJobs();
            }

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

            if ($job->hasExpired()) {
                $job->handleExpiry();

                continue;
            }

            if ($job->hasReachedRetryLimit()) {
                echo "  - Job {$job->getJobId()} has reached retry limit, marking as failed\n";
                $job->fail();

                continue;
            }

            echo "Processing job: {$jobData['class']} --- #{$job->getJobId()}\n";

            try {
                $job->trigger();

                // at this point, the job has been successfully processed
                $job->removeFromQueue();

                continue;
            } catch (\Throwable $th) {
                echo "  - Job #{$job->getJobId()} failed: {$th->getMessage()}\n";

                $this->report($th, $job, $jobData);

                $job->retry($th);

                continue;
            }
        }
    }
}
