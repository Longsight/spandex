<?php

namespace Spandex;

use Spandex\Spandex;

abstract class Hook
{
    abstract public function getRender(array $params, Spandex $spandex);
}
