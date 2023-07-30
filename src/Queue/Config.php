<?php

namespace Leaf\Queue;

/**
 * COnfig for queue and worker
 */
class Config
{
    protected static $queues = [
        'default' => null,
    ];

    /**
     * Set queue config
     */
    public static function set($name, $config)
    {
        self::$queues[$name] = $config;
    }

    /**
     * Get queue config
     * @return \Leaf\Queue
     */
    public static function get(string $name)
    {
        return self::$queues[$name] ?? null;
    }
}
