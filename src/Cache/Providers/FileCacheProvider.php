<?php

namespace Spandex\Cache\Providers;

use Spandex\Interfaces\ICacheProvider;

class FileCacheProvider implements ICacheProvider
{
    private $filename;
    private $depends;

    public function __construct($identifier, $depends = array())
    {
        $this->filename = $identifier;
        $this->depends = $depends;
    }
    
    public function isStale($timestamp)
    {
        return filemtime($this->filename) > $timestamp;
    }
    
    public function refresh($encoding)
    {
        $content = file_get_contents($this->filename);
        switch ($encoding) {
            case 'json':
                $content = json_decode($content, true, 512, JSON_BIGINT_AS_STRING);
                break;
        }
        return array(
            'type' => 'file',
            'content' => $content,
            'encoding' => $encoding,
            'lastModified' => filemtime($this->filename),
            'depends' => $this->depends
        );
    }
}
