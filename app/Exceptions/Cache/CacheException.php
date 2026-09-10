<?php

namespace App\Exceptions\Cache;

use Psr\Cache\CacheException as PsrCacheException;
use RuntimeException;

class CacheException extends RuntimeException implements PsrCacheException
{
}
