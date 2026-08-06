<?php

/*
|--------------------------------------------------------------------------
| Test harness for leafs/queue
|--------------------------------------------------------------------------
| The queue module is normally booted by Leaf MVC (mvc-core), which
| provides the MvcConfig(), AppPaths() and DatabasePath() globals plus
| \Leaf\Config from leafs/config. Here we reproduce that wiring with
| class_exists/function_exists-guarded shims and a per-test sandbox with
| an sqlite database that \Leaf\Db connects to directly.
*/

define('SANDBOX', '/tmp/queuetestsandbox' . (getenv('TEST_TOKEN') ? '-' . getenv('TEST_TOKEN') : ''));

require __DIR__ . '/shims/Globals.php';
require __DIR__ . '/shims/CrashSpy.php';

if (!function_exists('crash')) {
    function crash()
    {
        return \Tests\CrashSpy::instance();
    }
}

require __DIR__ . '/shims/Config.php';
require __DIR__ . '/jobs.php';

function setupQueueEnv(): void
{
    $GLOBALS['queueTestPdo'] = null; // drop the stale handle to the deleted db file

    if (is_dir(SANDBOX)) {
        exec('rm -rf ' . escapeshellarg(SANDBOX));
    }

    mkdir(SANDBOX . '/app/jobs', 0777, true);
    mkdir(SANDBOX . '/app/database', 0777, true);

    touch(SANDBOX . '/db.sqlite');

    testDb()->exec('
        CREATE TABLE leaf_php_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT,
            config TEXT,
            retry_count INTEGER DEFAULT 0,
            status TEXT,
            available_at INTEGER DEFAULT 0,
            created_at INTEGER,
            updated_at INTEGER,
            failed_at INTEGER,
            exception TEXT
        )
    ');

    $GLOBALS['queueTestConfig'] = [
        'queue' => [
            'default' => 'default',
            'connections' => [
                'default' => [
                    'driver' => 'database',
                    'connection' => 'sqlite',
                    'table' => 'leaf_php_jobs',
                ],
            ],
        ],
        'database' => [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => SANDBOX . '/db.sqlite',
                ],
            ],
        ],
    ];
}

/** Raw PDO handle on the sandbox sqlite database (for assertions/fixtures) */
function testDb(): \PDO
{
    if (!($GLOBALS['queueTestPdo'] ?? null)) {
        $GLOBALS['queueTestPdo'] = new \PDO('sqlite:' . SANDBOX . '/db.sqlite');
        $GLOBALS['queueTestPdo']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    return $GLOBALS['queueTestPdo'];
}

function jobRows(): array
{
    return testDb()
        ->query('SELECT * FROM leaf_php_jobs ORDER BY id')
        ->fetchAll(\PDO::FETCH_ASSOC);
}

/** Run the worker against the sandbox queue until the queue is empty */
function runWorker(): void
{
    $connection = $GLOBALS['queueTestConfig']['queue']['connections']['default'];

    ob_start();

    (new \Leaf\Worker())
        ->queue($connection)
        ->scheduler($connection)
        ->config(['quitOnEmpty' => true])
        ->run();

    ob_end_clean();
}

function queueConnection(): array
{
    return $GLOBALS['queueTestConfig']['queue']['connections']['default'];
}

uses()->beforeEach(fn () => setupQueueEnv())->in(__DIR__);
