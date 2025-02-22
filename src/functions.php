<?php

/**
 * Return queue object
 *
 * @return \Leaf\Queue
 */
function queue()
{
    if (!(\Leaf\Config::getStatic('queue'))) {
        \Leaf\Config::singleton('queue', function () {
            return new \Leaf\Queue();
        });
    }

    return \Leaf\Config::get('queue');
}

/**
 * Dispatch a job, batch or a group of jobs
 *
 * @param array|\Leaf\Queue\Dispatchable|string $dispatchable The job, batch or group of jobs to dispatch
 * @param array $data The data to pass to the job
 */
function dispatch($dispatchable)
{
    if (is_array($dispatchable)) {
        foreach ($dispatchable as $item) {
            dispatch($item);
        }

        return;
    }

    if (is_string($dispatchable)) {
        $dispatchable = new $dispatchable();
    }

    $queueModuleConfig = MvcConfig('queue') ?? [];
    $jobConnection = $dispatchable->connection();

    $defaultConnection = $queueModuleConfig['connections'][$queueModuleConfig['default'] ?? 'database'];

    // if (\Leaf\Config::getStatic('queue')) {
    //     return queue()->push([
    //         'class' => $jobOrBatch,
    //         'config' => $jobOrBatch->getConfig(),
    //         'status' => 'pending',
    //         'retry_count' => 0,
    //     ]);
    // }

    // can optimize by saving a cached version of this instance
    queue()->connect($queueModuleConfig['connections'][$jobConnection] ?? $defaultConnection);

    return queue()->push([
        'class' => $dispatchable::class,
        'config' => json_encode($dispatchable->getConfig()),
        'status' => 'pending',
        'retry_count' => 0,
    ]);
}
