<?php

namespace Spandex\Interfaces;

interface ICacheBackend
{
    public function get($name);
    public function set($name, $cache, $ttl);
}
