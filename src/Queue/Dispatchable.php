<?php

namespace Leaf\Queue;

/**
 * Contract for dispatchable classes
 */
interface Dispatchable
{
    /**
     * Return configured connection
     */
    public function connection();

    /**
     * Add data to the job
     */
    public static function with($data);

    /**
     * Return job stack if available
     */
    public function stack();

    /**
     * Return config for the job
     * @return array
     */
    public function getConfig();
}
