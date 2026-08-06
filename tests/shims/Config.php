<?php

/*
| Minimal stand-in for leafs/config (\Leaf\Config), used by queue()
*/

namespace Leaf;

if (!class_exists('Leaf\Config')) {
    class Config
    {
        protected static array $items = [];

        public static function getStatic($key)
        {
            return static::$items[$key] ?? null;
        }

        public static function singleton($key, $resolver)
        {
            static::$items[$key] = $resolver();
        }

        public static function get($key)
        {
            return static::$items[$key] ?? null;
        }
    }
}
