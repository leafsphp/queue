<?php

/*
| Shims for globals normally provided by Leaf MVC (mvc-core)
*/

if (!function_exists('MvcConfig')) {
    function MvcConfig(string $key)
    {
        return $GLOBALS['queueTestConfig'][$key] ?? null;
    }
}

if (!function_exists('AppPaths')) {
    function AppPaths(string $path)
    {
        return SANDBOX . '/app/' . $path;
    }
}

if (!function_exists('DatabasePath')) {
    function DatabasePath(string $path = '')
    {
        return SANDBOX . '/app/database/' . $path;
    }
}
