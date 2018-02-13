<?php

namespace Spandex;

use Spandex\Cache\Providers\FileCacheProvider;
use Spandex\Cache\Backends\XCacheBackend;
use Spandex\Interfaces\ICacheProvider;

class Cache
{
    private $backend;
    private $cacheLock;
    private $locks = array();

    public static function hashName($prefix, $object)
    {
        ksort($object);
        return sprintf('%s-%s', $prefix, md5(serialize($object)));
    }

    public function __construct($config)
    {
        $type = $config['CacheType'];
        switch ($type) {
            case 'xcache':
                $this->backend = new XCacheBackend;
                break;
            case 'memcached':
                $options = [
                    'username' => $config['MemcachedUsername'],
                    'password' => $config['MemcachedPassword'],
                    'servers' => $config['MemcachedServers']
                ];
                $this->backend = new MemcachedBackend($options);
                break;
            default:
                throw new \DomainException('Unknown cache type: \'' . $type . '\'');
        }
        $this->cacheLock = sys_get_temp_dir() . '/spandex/cache';
        if (!file_exists($this->cacheLock)) {
            mkdir($this->cacheLock, 0777, true);
        }
    }

    public function __destruct()
    {
        foreach ($this->locks as $name => $value) {
            $this->unlock($name);
        }
    }
    
    public function set($name, $object, $ttl = 0, $provider = null, $encoding = false, $depends = false)
    {
        if ($provider !== null && $provider instanceof ICacheProvider) {
            $cache = $provider->refresh($encoding);
        } else {
            switch ($encoding) {
                case 'json':
                    $object = json_decode($object, true, 512, JSON_BIGINT_AS_STRING);
                    break;
            }
            $cache = array(
                'type' => 'none',
                'content' => $object,
                'encoding' => $encoding
            );
        }
        $this->lock($this->lockName($name));
        $this->backend->set($name, $cache, $ttl);
        $this->unlock($this->lockName($name));
    }

    public function get($name)
    {
        $this->lock($this->lockName($name));
        $output = $this->backend->get($name)['content'];
        if ($output) {
            $this->unlock($this->lockName($name));
        }
        return $output;
    }

    public function remove($name)
    {
        $this->lock($this->lockName($name));
        $this->backend->remove($name);
        $this->unlock($this->lockName($name));
    }

    private function lock($name)
    {
        $this->locks[$name] = fopen($name, 'c+b');
        flock($this->locks[$name], LOCK_EX);
        return $this->locks[$name];
    }
    
    private function unlock($name)
    {
        if (array_key_exists($name, $this->locks)) {
            flock($this->locks[$name], LOCK_UN);
            fclose($this->locks[$name]);
            unset($this->locks[$name]);
        }
    }

    private function lockName($name)
    {
        $lockFile = str_replace('/', '-', trim($name, " \t\n\r\0\x0B/"));
        return "{$this->cacheLock}/{$lockFile}.lock";
    }
}
