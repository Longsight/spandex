<?php

namespace Spandex;

class Route
{
    private $pattern;
    private $path;
    private $namespace;

    public function __construct($pattern, $path, $namespace)
    {
        $this->pattern = $pattern;
        $this->path = $path;
        $this->namespace = $namespace;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function testRoute($test)
    {
        return substr($test, 0, strlen($this->pattern)) === $this->pattern;
    }
}
