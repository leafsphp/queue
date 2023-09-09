<?php

namespace Leaf\Queue\Adapters;

/**
 * Redis adapter
 * -----
 * Redis adapter for the worker
 */
class Redis implements Adapter
{
    /** @var \Leaf\Redis */
    protected $redis;

    protected $errors;

    protected array $config = [];

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->redis = new \Leaf\Redis();
    }

    /**
     * @inheritDoc
     */
    public function connect($connection)
    {
        $this->redis->init($connection);

        if (!$this->redis->get($this->config['table'])) {
            $this->redis->set($this->config['table'], json_encode([]));
        }
    }

    /**
     * @inheritDoc
     */
    public function pushJobToQueue($job)
    {
        $job = array_merge($job, [
            'id' => self::v4(),
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $data = $this->redis->get($this->config['table']) ?? [];
        $data = json_decode($data, true);

        $this->redis->set($this->config['table'], json_encode(array_merge($data, [$job])));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function popJobFromQueue($id)
    {
        $jobs = $this->redis->get($this->config['table']) ?? [];
        $jobs = json_decode($jobs, true);

        foreach ($jobs as $key => $job) {
            if ($job['id'] === $id) {
                unset($jobs[$key]);
                $this->redis->set($this->config['table'], json_encode($jobs));

                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function setJobStatus($id, $status)
    {
        $jobs = $this->redis->get($this->config['table']) ?? [];
        $jobs = json_decode($jobs, true);

        foreach ($jobs as $key => $job) {
            if ($job['id'] === $id) {
                $jobs[$key]['status'] = $status;
                $this->redis->set($this->config['table'], json_encode($jobs));

                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function markJobAsFailed($id)
    {
        return $this->setJobStatus($id, 'failed');
    }

    /**
     * @inheritDoc
     */
    public function getJobs()
    {
        $jobs = $this->redis->get($this->config['table']) ?? [];

        return json_decode($jobs, true);
    }

    /**
     * @inheritDoc
     */
    public function getNextJob()
    {
        $jobs = $this->redis->get($this->config['table']) ?? [];
        $jobs = json_decode($jobs, true);

        $job = array_values(array_filter($jobs, function ($job) {
            return $job['status'] === 'pending';
        }))[0] ?? null;

        return $job;
    }

    /**
     * @inheritDoc
     */
    public function retryFailedJob($id, $retryCount)
    {
        $jobs = $this->redis->get($this->config['table']) ?? [];
        $jobs = json_decode($jobs, true);

        foreach ($jobs as $key => $job) {
            if ($job['id'] === $id) {
                $jobs[$key]['status'] = 'pending';
                $jobs[$key]['retry_count'] = (int) $retryCount + 1;
                $this->redis->set($this->config['table'], json_encode($jobs));

                return true;
            }
        }

        return false;
    }

    /**
     * Generate unique id
     * @author Andrew Moore<https://www.php.net/manual/en/function.uniqid.php#94959>
     */
    public static function v4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            \mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            \mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            \mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff)
        );
    }
}
