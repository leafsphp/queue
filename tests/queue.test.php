<?php

use Leaf\Queue\Commands\QueueWorkCommand;

test('dispatch writes a well-formed row to the jobs table', function () {
    dispatch(QueueTestSuccessJob::with('hello'));

    $rows = jobRows();

    expect($rows)->toHaveCount(1);

    $row = $rows[0];

    expect($row['class'])->toBe(QueueTestSuccessJob::class);
    expect($row['status'])->toBe('pending');
    expect((int) $row['retry_count'])->toBe(0);
    expect((int) $row['available_at'])->toBeLessThanOrEqual(time());
    expect((int) $row['created_at'])->toBeGreaterThan(0);

    $config = json_decode($row['config'], true);

    expect($config['data'])->toBe(['hello']);
    expect($config['tries'])->toBe(3);
});

test('worker processes a pending job and removes it from the queue', function () {
    dispatch(QueueTestSuccessJob::with('payload'));

    runWorker();

    expect(file_get_contents(SANDBOX . '/out.txt'))->toContain('ran:payload');
    expect(jobRows())->toHaveCount(0);
});

// LEAF-113 regression: two with() dispatches must carry separate payloads
test('two with() dispatches carry separate payloads', function () {
    $first = QueueTestSuccessJob::with('first');
    $second = QueueTestSuccessJob::with('second');

    expect($first->getConfig()['data'])->toBe(['first']);
    expect($second->getConfig()['data'])->toBe(['second']);

    dispatch($first);
    dispatch($second);

    $rows = jobRows();

    expect(json_decode($rows[0]['config'], true)['data'])->toBe(['first']);
    expect(json_decode($rows[1]['config'], true)['data'])->toBe(['second']);

    runWorker();

    expect(file_get_contents(SANDBOX . '/out.txt'))->toBe("ran:first\nran:second\n");
});

// LEAF-117: final failure records status, failed_at and exception
test('failing job is retried, then marked failed with the exception recorded', function () {
    dispatch(new QueueTestFailJob()); // tries = 2

    runWorker();

    $rows = jobRows();

    expect($rows)->toHaveCount(1);

    $row = $rows[0];

    expect($row['status'])->toBe('failed');
    expect((int) $row['retry_count'])->toBe(1); // one retry after the first attempt
    expect((int) $row['failed_at'])->toBeGreaterThan(0);
    expect($row['exception'])->toContain('boom from QueueTestFailJob');
});

// LEAF-114: available_at delay is honored without sleeping
test('job with a future available_at is not picked up', function () {
    dispatch(QueueTestSuccessJob::with('later'));

    testDb()->exec('UPDATE leaf_php_jobs SET available_at = ' . (time() + 3600));

    runWorker();

    expect(file_exists(SANDBOX . '/out.txt'))->toBeFalse();
    expect(jobRows()[0]['status'])->toBe('pending');
});

test('job with a past available_at is picked up', function () {
    dispatch(QueueTestSuccessJob::with('due'));

    testDb()->exec('UPDATE leaf_php_jobs SET available_at = ' . (time() - 3600));

    runWorker();

    expect(file_get_contents(SANDBOX . '/out.txt'))->toContain('ran:due');
    expect(jobRows())->toHaveCount(0);
});

// LEAF-115: expired jobs are kept with status 'expired', not deleted
test('expired job is marked expired and kept in the table', function () {
    dispatch(new QueueTestExpiringJob()); // expire = 10

    testDb()->exec('UPDATE leaf_php_jobs SET created_at = ' . (time() - 100));

    runWorker();

    $rows = jobRows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe('expired');
    expect(file_exists(SANDBOX . '/expired-ran.txt'))->toBeFalse();
});

test('jobs with expire 0 never expire', function () {
    dispatch(QueueTestSuccessJob::with('immortal'));

    testDb()->exec("UPDATE leaf_php_jobs SET created_at = " . (time() - 999999) . ", config = json_set(config, '$.expire', 0)");

    runWorker();

    expect(file_get_contents(SANDBOX . '/out.txt'))->toContain('ran:immortal');
});

// LEAF-116: stuck 'processing' jobs are reset to pending at loop start
test('stuck processing jobs are reset and re-run by the worker', function () {
    dispatch(QueueTestSuccessJob::with('recovered'));

    testDb()->exec("UPDATE leaf_php_jobs SET status = 'processing', updated_at = " . (time() - 10000));

    runWorker();

    expect(file_get_contents(SANDBOX . '/out.txt'))->toContain('ran:recovered');
    expect(jobRows())->toHaveCount(0);
});

// LEAF-120 regression: queue:work config resolution
test('queue:work resolves the default queue from full config', function () {
    [$queue, $connection] = QueueWorkCommand::resolveQueueConnection(null, $GLOBALS['queueTestConfig']['queue']);

    expect($queue)->toBe('default');
    expect($connection)->toBeArray();
    expect($connection['driver'])->toBe('database');
});

test('queue:work resolves an explicitly requested queue', function () {
    [$queue, $connection] = QueueWorkCommand::resolveQueueConnection('default', $GLOBALS['queueTestConfig']['queue']);

    expect($queue)->toBe('default');
    expect($connection['table'])->toBe('leaf_php_jobs');
});

test('queue:work errors on an unknown queue instead of crashing', function () {
    [$queue, $error] = QueueWorkCommand::resolveQueueConnection('missing', $GLOBALS['queueTestConfig']['queue']);

    expect($queue)->toBeNull();
    expect($error)->toContain("Queue 'missing' is not defined");
});

test('queue:work errors when no queue and no default is configured', function () {
    [$queue, $error] = QueueWorkCommand::resolveQueueConnection(null, []);

    expect($queue)->toBeNull();
    expect($error)->toContain('No queue specified');
});
