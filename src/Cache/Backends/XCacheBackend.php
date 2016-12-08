<?php

namespace Spandex\Cache\Backends;

use Spandex\Interfaces\ICacheBackend;
use Spandex\Cache\Providers\FileCacheProvider;

class XCacheBackend
{
    public function get($name)
    {
        if (!xcache_isset($name)) {
            header('X-Spandex-Cache: MISS', false);
            return array(
                'content' => null
            );
        }
        $object = xcache_get($name);
        switch ($object['type']) {
            case 'file':
                if (!array_key_exists('depends', $object)) {
                    $object['depends'] = array();
                }
                $provider = new FileCacheProvider($name, $object['depends']);
                if ($provider->isStale($object['lastModified'])) {
                    $object = $provider->refresh($object['encoding']);
                    $this->set($name, $object, 0);
                    foreach ($object['depends'] as $depend) {
                        if (xcache_isset($depend)) {
                            xcache_unset($depend);
                        }
                    }
                    header('X-Spandex-Cache: AUTO-REFRESH', false);
                } else {
                    header('X-Spandex-Cache: HIT', false);
                }
        }
        return $object;
    }

    public function set($name, $cache, $ttl)
    {
        xcache_set($name, $cache, $ttl);
    }

    public function remove($name)
    {
        xcache_unset($name);
    }
}
