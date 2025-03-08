<?php

namespace Leaf\Queue\Adapters;

/**
 * Database adapter
 * -----
 * Db adapter for the worker
 */
class Database implements Adapter
{
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

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function pushJobToQueue($job)
    {
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
    public function markJobAsFailed($id)
    {
        return $this->setJobStatus($id, 'failed');
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
     */
    public function getNextJob()
    {
        return $this->db
            ->select($this->config['table'])
            ->where([
                'status' => 'pending',
            ])
            ->orderBy('id', 'asc')
            ->limit(1)
            ->get()[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function retryFailedJob($id, $retryCount)
    {
        $this->db
            ->update($this->config['table'])
            ->params([
                "status" => "pending",
                "retry_count" => (int) $retryCount + 1,
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
}
