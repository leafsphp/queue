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
        'force' => false
    ];

    /**
     * Configuration for the worker
     */
    public function config($config)
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function queue($queue)
    {
        $this->queue = $queue;
        return $this;
    }

    public function memoryExceeded($memory)
    {
        return memory_get_usage(true) >= $this->config['memory'] * 1024 * 1024;
    }

    public function run()
    {
        if (!$this->queue->getAdapter()) {
            $this->queue->connect();
        }

        $shouldLoop = true;

        while ($shouldLoop) {
            $jobData = $this->queue->getNextJob();

            if (!$jobData) {
                if (!$this->config['quitOnEmpty']) {
                    sleep($this->config['sleep']);
                } else {
                    $shouldLoop = false;
                }

                continue;
            }

            $jobConfig = json_decode($jobData['config'] ?? "{}", true);
            
            try {
                /** @var \Leaf\Queue\Job */
                $job = new $jobData['class']($jobData, $jobConfig, $this->queue);

                $job->handleDelay();

                if ($job->hasExpired()) {
                    $job->handleExpiry();
                    continue;
                }

                if ($job->hasReachedRetryLimit()) {
                    $job->handleRetryLimit();
                    continue;
                }

                $job->trigger();

                if ($this->memoryExceeded($jobConfig['memory'])) {
                    exit(12);
                }

                // at this point, the job has been successfully processed
                $job->removeFromQueue();
                continue;
            } catch (\Throwable $th) {
                $job->retry();
                continue;
            }
        }
    }
}
