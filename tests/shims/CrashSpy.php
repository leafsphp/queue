<?php

/*
| A stand-in for \Leaf\Crash\Hub.
|
| The worker only asks function_exists('crash'), so this proves the reporting
| call is made and carries what it should, and can be told to throw so the
| "reporting must never kill the worker" guarantee is genuinely exercised.
*/

namespace Tests;

class CrashSpy
{
    /** @var array<int, array{0: mixed, 1: array}> */
    public array $captures = [];

    public bool $explode = false;

    /** Every attempt, including ones that threw — so a guard cannot pass vacuously */
    public int $attempts = 0;

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function reset(): void
    {
        $this->captures = [];
        $this->crumbs = [];
        $this->attempts = 0;
        $this->explode = false;
    }

    /** @var array<int, array{0: string, 1: string, 2: array}> */
    public array $crumbs = [];

    public function breadcrumbs(): self
    {
        return $this;
    }

    /** Breadcrumbs::clear() */
    public function clear(): self
    {
        $this->crumbs = [];

        return $this;
    }

    public function leaveCrumb(string $message, string $type = 'action', array $meta = [], bool $withOrigin = true): self
    {
        $this->crumbs[] = [$message, $type, $meta];

        return $this;
    }

    public function capture($subject, array $options = [])
    {
        $this->attempts++;

        if ($this->explode) {
            throw new \RuntimeException('reporter is down');
        }

        $this->captures[] = [$subject, $options];

        return $subject;
    }
}
