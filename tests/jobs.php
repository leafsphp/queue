<?php

/*
| Job classes used by the test suite
*/

if (!class_exists('QueueTestSuccessJob')) {
    class QueueTestSuccessJob extends \Leaf\Job
    {
        public function handle($marker = null)
        {
            file_put_contents(SANDBOX . '/out.txt', 'ran:' . $marker . "\n", FILE_APPEND);
        }
    }
}

if (!class_exists('QueueTestFailJob')) {
    class QueueTestFailJob extends \Leaf\Job
    {
        protected $tries = 2;

        public function handle()
        {
            throw new \Exception('boom from QueueTestFailJob');
        }
    }
}

if (!class_exists('QueueTestExpiringJob')) {
    class QueueTestExpiringJob extends \Leaf\Job
    {
        protected $expire = 10;

        public function handle()
        {
            file_put_contents(SANDBOX . '/expired-ran.txt', 'should not run');
        }
    }
}
