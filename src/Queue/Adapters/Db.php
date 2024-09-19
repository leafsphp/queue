<?php

namespace Leaf\Queue\Adapters;

/**
 * Database adapter
 * -----
 * Db adapter for the worker
 */
class Db implements Adapter
{
    /** @var \Leaf\Db */
    protected $db;

    protected $errors;

    protected array $config = [];

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->db = new \Leaf\Db();
    }

    /**
     * @inheritDoc
     */
    public function connect($connection)
    {
        $this->db->connect([
            'dbtype' => $connection['dbtype'] ?? 'mysql',
            'charset' => $connection['charset'] ?? null,
            'port' => $connection['port'] ?? null,
            'unixSocket' => $connection['unixSocket'] ?? null,
            'host' => $connection['host'] ?? '127.0.0.1',
            'username' => $connection['username'] ?? 'root',
            'password' => $connection['password'] ?? '',
            'dbname' => $connection['database'] ?? '',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function pushJobToQueue($job)
    {
        if (!$this->db->tableExists($this->config['table'])) {
            $this->setupAdapterStorage();
        }

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
                "status" => $status,
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
     */
    public function markJobAsFailed($id)
    {
        $this->db
            ->update($this->config['table'])
            ->params([
                "status" => "failed",
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
     * Setup storage for the adapter
     */
    protected function setupAdapterStorage()
    {
        $this->db
            ->createTable($this->config['table'], [
                'id' => 'INT NOT NULL AUTO_INCREMENT',
                'class' => 'VARCHAR(255)',
                'config' => 'TEXT',
                'status' => 'VARCHAR(50)',
                'retry_count' => 'INT',
                'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'PRIMARY KEY' => '(ID)',
            ])
            ->execute();
    }

    /**
     * @inheritDoc
     */
    public function getJobs()
    {
        return [];
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
