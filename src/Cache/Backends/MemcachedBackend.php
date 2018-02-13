<?php

namespace Spandex\Cache\Backends;

use Spandex\Interfaces\ICacheBackend;
use Spandex\Cache\Providers\FileCacheProvider;

class MemcachedBackend implements ICacheBackend
{
    private $instance;

    public function __construct($options) {
        $m = new \Memcached('memcached_pool');
        $m->setOption(\Memcached::OPT_BINARY_PROTOCOL, TRUE);

        // some nicer default options
        // - nicer TCP options
        $m->setOption(\Memcached::OPT_TCP_NODELAY, TRUE);
        $m->setOption(\Memcached::OPT_NO_BLOCK, FALSE);
        // - timeouts
        $m->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 2000);    // ms
        $m->setOption(\Memcached::OPT_POLL_TIMEOUT, 2000);       // ms
        $m->setOption(\Memcached::OPT_RECV_TIMEOUT, 750 * 1000); // us
        $m->setOption(\Memcached::OPT_SEND_TIMEOUT, 750 * 1000); // us
        // - better failover
        $m->setOption(\Memcached::OPT_DISTRIBUTION, \Memcached::DISTRIBUTION_CONSISTENT);
        $m->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, TRUE);
        $m->setOption(\Memcached::OPT_RETRY_TIMEOUT, 2);
        $m->setOption(\Memcached::OPT_SERVER_FAILURE_LIMIT, 1);
        $m->setOption(\Memcached::OPT_AUTO_EJECT_HOSTS, TRUE);
        
        // setup authentication
        $m->setSaslAuthData($options['username'], $options['password']);
        
        // We use a consistent connection to memcached, so only add in the
        // servers first time through otherwise we end up duplicating our
        // connections to the server.
        if (!$m->getServerList()) {
            // parse server config
            $servers = explode(",", $options['servers']);
            foreach ($servers as $s) {
                $parts = explode(":", $s);
                $m->addServer($parts[0], $parts[1]);
            }
        }
        $this->instance = $m;
    }

    public function get($name)
    {
        if (!($object = $this->instance->get($name))) {
            header('X-Spandex-Cache: MISS', false);
            return array(
                'content' => null
            );
        }
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
                        if ($this->instance->get($depend)) {
                            $this->instance->delete($depend);
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
        $this->instance->set($name, $cache, $ttl);
    }

    public function remove($name)
    {
        $this->instance->delete($name);
    }
}
