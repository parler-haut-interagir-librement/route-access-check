<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A baseline file could not be read: it is not the JSON document the library writes, or it is too large.
 */
final class InvalidBaseline extends InvalidArgumentException implements RouteAccessCheckException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self('The baseline is invalid: '.$reason, 0, $previous);
    }
}
