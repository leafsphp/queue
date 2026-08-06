<?php

/*
| Redis adapter tests — only run when a redis server is reachable locally.
*/

function redisReachable(): bool
{
    $socket = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 0.2);

    if ($socket) {
        fclose($socket);

        return true;
    }

    return false;
}

test('redis adapter pushes, claims and pops jobs through real redis structures', function () {
    $GLOBALS['queueTestConfig']['redis'] = ['host' => '127.0.0.1', 'port' => 6379];

    $adapter = (new \Leaf\Queue\Adapters\Redis())->connect([
        'driver' => 'redis',
        'table' => 'queue_test_jobs',
    ]);

    // clean slate
    foreach ($adapter->getJobs() as $job) {
        $adapter->popJobFromQueue($job['id']);
    }

    $adapter->pushJobToQueue([
        'class' => QueueTestSuccessJob::class,
        'config' => json_encode(['data' => ['redis']]),
        'status' => 'pending',
        'retry_count' => 0,
    ]);

    $job = $adapter->getNextJob();

    expect($job)->not->toBeNull();
    expect($job['class'])->toBe(QueueTestSuccessJob::class);

    // retryFailedJob must not fatal (LEAF-112 regression) and re-queues the job
    expect($adapter->retryFailedJob($job['id'], 0))->toBeTrue();

    $retried = $adapter->getNextJob();

    expect($retried['id'])->toBe($job['id']);
    expect((int) $retried['retry_count'])->toBe(1);

    // delayed jobs go to the zset and are not claimable until due
    $adapter->pushJobToQueue([
        'class' => QueueTestSuccessJob::class,
        'config' => '{}',
        'status' => 'pending',
        'retry_count' => 0,
        'available_at' => time() + 3600,
    ]);

    expect($adapter->getNextJob())->toBeNull();

    foreach ($adapter->getJobs() as $job) {
        $adapter->popJobFromQueue($job['id']);
    }
})->skip(!redisReachable(), 'redis server not reachable on 127.0.0.1:6379');
