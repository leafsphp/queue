<?php

namespace Leaf\Queue\Adapters;

/**
 * Redis adapter
 * -----
 * Redis adapter for the worker.
 *
 * Storage layout (real redis structures, no json blob):
 * - {table}:pending — LIST of job ids ready to run (RPUSH to enqueue, LPOP to claim)
 * - {table}:delayed — ZSET of job ids scored by available_at
 * - {table}:job:{id} — HASH holding the job payload/status
 *
 * Raw redis commands (rpush, lpop, hset, zadd, ...) are issued through
 * \Leaf\Redis::connection(), which proxies to the underlying phpredis or
 * predis client via __call.
 */
class Redis implements Adapter
{
    /**
     * Seconds a job can sit in 'processing' before it is considered stuck
     */
    protected const STUCK_JOB_TIMEOUT = 300;

    /** @var \Leaf\Redis */
    protected $redis;

    protected $errors;

    protected array $config = [];

    public function __construct()
    {
        $this->redis = new \Leaf\Redis();
    }

    /**
     * @inheritDoc
     */
    public function connect($connection)
    {
        $this->config['table'] = $connection['table'] ?? 'leaf_php_jobs';
        $this->config['schedule.table'] = $connection['schedule.table'] ?? 'leaf_php_schedules';

        if (redis()->ping()) {
            $this->redis = redis();
        } else {
            $this->redis->connect(MvcConfig('redis'));
        }

        return $this;
    }

    /**
     * The raw redis client (phpredis or predis)
     */
    protected function client()
    {
        return $this->redis->connection();
    }

    protected function pendingKey(): string
    {
        return "{$this->config['table']}:pending";
    }

    protected function delayedKey(): string
    {
        return "{$this->config['table']}:delayed";
    }

    protected function jobKey($id): string
    {
        return "{$this->config['table']}:job:{$id}";
    }

    /**
     * @inheritDoc
     */
    public function pushJobToQueue($job)
    {
        $job = array_merge([
            'id' => self::v4(),
            'available_at' => time(),
            'created_at' => time(),
            'updated_at' => time(),
        ], $job);

        $this->client()->hmset($this->jobKey($job['id']), $job);

        if ($job['available_at'] > time()) {
            $this->client()->zadd($this->delayedKey(), $job['available_at'], $job['id']);
        } else {
            $this->client()->rpush($this->pendingKey(), $job['id']);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function popJobFromQueue($id)
    {
        // phpredis and predis disagree on lrem() argument order
        if ($this->redis->connection() instanceof \Leaf\Redis\Predis) {
            $this->client()->lrem($this->pendingKey(), 0, $id);
        } else {
            $this->client()->lrem($this->pendingKey(), $id, 0);
        }
        $this->client()->zrem($this->delayedKey(), $id);

        return (bool) $this->client()->del($this->jobKey($id));
    }

    /**
     * @inheritDoc
     */
    public function setJobStatus($id, $status)
    {
        if (!$this->client()->exists($this->jobKey($id))) {
            return false;
        }

        $this->client()->hmset($this->jobKey($id), [
            'status' => $status,
            'updated_at' => time(),
        ]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function markJobAsFailed($id, $exception = null)
    {
        if (!$this->client()->exists($this->jobKey($id))) {
            return false;
        }

        $this->client()->hmset($this->jobKey($id), [
            'status' => 'failed',
            'failed_at' => time(),
            'exception' => (string) $exception,
            'updated_at' => time(),
        ]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getJobs()
    {
        $jobs = [];

        foreach ($this->client()->keys("{$this->config['table']}:job:*") as $key) {
            $job = $this->client()->hgetall($key);

            if ($job) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @inheritDoc
     */
    public function getNextJob()
    {
        // move due delayed jobs onto the pending list
        $dueJobs = $this->client()->zrangebyscore($this->delayedKey(), '-inf', time());

        foreach ($dueJobs as $dueJobId) {
            $this->client()->zrem($this->delayedKey(), $dueJobId);
            $this->client()->rpush($this->pendingKey(), $dueJobId);
        }

        while (($id = $this->client()->lpop($this->pendingKey()))) {
            $job = $this->client()->hgetall($this->jobKey($id));

            if (!$job) {
                continue; // job hash was deleted, skip the orphaned id
            }

            if (($job['status'] ?? null) !== 'pending') {
                continue;
            }

            return $job;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function retryFailedJob($id, $retryCount, $delay = 0)
    {
        if (!$this->client()->exists($this->jobKey($id))) {
            return false;
        }

        $availableAt = time() + (int) $delay;

        $this->client()->hmset($this->jobKey($id), [
            'status' => 'pending',
            'retry_count' => (int) $retryCount + 1,
            'available_at' => $availableAt,
            'updated_at' => time(),
        ]);

        if ($availableAt > time()) {
            $this->client()->zadd($this->delayedKey(), $availableAt, $id);
        } else {
            $this->client()->rpush($this->pendingKey(), $id);
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * Jobs stuck in 'processing' (worker crashed mid-job) are reset to
     * 'pending' and pushed back onto the pending list.
     */
    public function resetStuckJobs()
    {
        foreach ($this->getJobs() as $job) {
            if (
                ($job['status'] ?? null) === 'processing'
                && ((int) ($job['updated_at'] ?? 0)) < (time() - static::STUCK_JOB_TIMEOUT)
            ) {
                $this->client()->hmset($this->jobKey($job['id']), [
                    'status' => 'pending',
                    'updated_at' => time(),
                ]);

                $this->client()->rpush($this->pendingKey(), $job['id']);
            }
        }

        return true;
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

    /**
     * @inheritDoc
     */
    public function disconnect()
    {
        $this->redis->close();
    }
}
