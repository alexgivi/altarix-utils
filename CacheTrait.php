<?php

namespace altarix\utils;

use ReflectionFunction;

/**
 * Трейт, который описывает кеширующие фукнции уровня класса и объекта.
 * @package app\core
 */
trait CacheTrait
{
    protected static $staticCache = [];

    /**
     * @param callable        $createFunction
     * @param string|int|null $id
     * @return mixed
     * @throws \ReflectionException
     */
    protected static function staticCache($createFunction, $id = null)
    {
        if ($id === null) {
            $funcInfo = new ReflectionFunction($createFunction);
            $source = file($funcInfo->getFileName());
            $body = implode("", array_slice($source, $funcInfo->getStartLine(), $funcInfo->getEndLine()));
            $id = md5($body);
        }

        if (array_key_exists($id, static::$staticCache)) {
            return static::$staticCache[$id];
        }

        $object = $createFunction();
        static::$staticCache[$id] = $object;

        return $object;
    }

    protected static function releaseStaticCache($id = null)
    {
        if ($id === null) {
            static::$staticCache = [];
        } else {
            unset(static::$staticCache[$id]);
        }
    }

    protected $cache = [];

    /**
     * @param callable        $createFunction
     * @param string|int|null $id
     * @return mixed
     * @throws \ReflectionException
     */
    protected function cache($createFunction, $id = null)
    {
        if ($id === null) {
            $funcInfo = new ReflectionFunction($createFunction);
            $source = file($funcInfo->getFileName());
            $body = implode("", array_slice($source, $funcInfo->getStartLine(), $funcInfo->getEndLine()));
            $id = md5($body);
        }

        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        $object = $createFunction();
        $this->cache[$id] = $object;

        return $object;
    }

    protected function releaseCache($id = null)
    {
        if ($id === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$id]);
        }
    }
}
