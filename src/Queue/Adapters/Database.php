<?php

namespace Leaf\Queue\Adapters;

/**
 * Database adapter
 * -----
 * Db adapter for the worker
 */
class Database implements Adapter
{
    /**
     * Seconds a job can sit in 'processing' before it is considered stuck
     */
    protected const STUCK_JOB_TIMEOUT = 300;

    /** @var \Leaf\Db */
    protected $db;

    protected $errors;

    protected array $config = [];

    public function __construct()
    {
        $this->db = new \Leaf\Db();
    }

    /**
     * @inheritDoc
     */
    public function connect($connection)
    {
        $appDbConfig = MvcConfig('database');
        $dbConnection = $appDbConfig['connections'][$connection['connection']] ?? $appDbConfig['connections'][$appDbConfig['default']];

        $this->db->connect([
            'dbtype' => $dbConnection['driver'] ?? 'mysql',
            'charset' => $dbConnection['charset'] ?? null,
            'port' => $dbConnection['port'] ?? null,
            'unixSocket' => $dbConnection['unixSocket'] ?? null,
            'host' => $dbConnection['host'] ?? '127.0.0.1',
            'username' => $dbConnection['username'] ?? 'root',
            'password' => $dbConnection['password'] ?? '',
            'dbname' => $dbConnection['database'] ?? '',
        ]);

        $this->config['table'] = $connection['table'] ?? 'leaf_php_jobs';
        $this->config['schedule.table'] = $connection['schedule.table'] ?? 'leaf_php_schedules';

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function pushJobToQueue($job)
    {
        $job['available_at'] = $job['available_at'] ?? time();
        $job['created_at'] = $job['created_at'] ?? time();
        $job['updated_at'] = $job['updated_at'] ?? time();

        $this->db
            ->insert($this->config['table'])
            ->params($job)
            ->execute();

        if ($this->db->errors()) {
            $this->errors = $this->db->errors();

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function popJobFromQueue($id)
    {
        $this->db
            ->delete($this->config['table'])
            ->where([
                "id" => $id,
            ])
            ->execute();

        if ($this->db->errors()) {
            $this->errors = $this->db->errors();

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function setJobStatus($id, $status)
    {
        $this->db
            ->update($this->config['table'])
            ->params([
                'status' => $status,
                'updated_at' => time(),
            ])
            ->where([
                'id' => $id,
            ])
            ->execute();

        if ($this->db->errors()) {
            $this->errors = $this->db->errors();

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function markJobAsFailed($id, $exception = null)
    {
        $this->db
            ->update($this->config['table'])
            ->params([
                'status' => 'failed',
                'failed_at' => time(),
                'exception' => $exception,
                'updated_at' => time(),
            ])
            ->where([
                'id' => $id,
            ])
            ->execute();

        if ($this->db->errors()) {
            $this->errors = $this->db->errors();

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getJobs()
    {
        return $this->db
            ->select($this->config['table'])
            ->get();
    }

    /**
     * @inheritDoc
     *
     * Claims the next job atomically: a raw UPDATE tags exactly one
     * pending + available row with a unique claim token, then the tagged
     * row is fetched by that token. This prevents two workers from
     * picking the same job. The derived-table subquery works on both
     * sqlite and mysql (mysql cannot subquery the update target directly).
     */
    public function getNextJob()
    {
        $table = $this->config['table'];
        $token = 'claimed-' . uniqid('', true);

        $this->db
            ->query(
                "UPDATE {$table} SET status = ?, updated_at = " . time() . " WHERE id = (
                    SELECT id FROM (
                        SELECT id FROM {$table}
                        WHERE status = 'pending' AND available_at <= ?
                        ORDER BY id ASC LIMIT 1
                    ) AS next_job
                )"
            )
            ->bind($token, time())
            ->execute();

        if ($this->db->errors()) {
            $this->errors = $this->db->errors();

            return null;
        }

        return $this->db
            ->select($table)
            ->where([
                'status' => $token,
            ])
            ->limit(1)
            ->get()[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function retryFailedJob($id, $retryCount, $delay = 0)
    {
        $this->db
            ->update($this->config['table'])
            ->params([
                "status" => "pending",
                "retry_count" => (int) $retryCount + 1,
                "available_at" => time() + (int) $delay,
                "updated_at" => time(),
            ])
            ->where([
                "id" => $id,
            ])
            ->execute();

        if ($this->db->errors()) {
            $this->errors = $this->db->errors();

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * Rows stuck in 'processing' or holding a stale claim token (worker
     * crashed mid-job) are reset to 'pending' so they can run again.
     */
    public function resetStuckJobs()
    {
        $table = $this->config['table'];

        $this->db
            ->query(
                "UPDATE {$table} SET status = 'pending', updated_at = ?
                WHERE (status = 'processing' OR status LIKE 'claimed-%')
                AND updated_at < ?"
            )
            ->bind(time(), time() - static::STUCK_JOB_TIMEOUT)
            ->execute();

        if ($this->db->errors()) {
            $this->errors = $this->db->errors();

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function disconnect()
    {
        $this->db->close();
    }
}
