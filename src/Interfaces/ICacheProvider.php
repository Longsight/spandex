<?php

namespace Spandex\Interfaces;

interface ICacheProvider
{
    public function isStale($timestamp);
    public function refresh($encoding);
}
