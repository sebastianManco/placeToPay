<?php

namespace App\Exceptions\Cache;

use InvalidArgumentException as BaseInvalidArgumentException;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;

class CacheInvalidArgumentException extends BaseInvalidArgumentException implements PsrInvalidArgumentException
{
}
