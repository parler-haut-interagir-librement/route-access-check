<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Exception;

use InvalidArgumentException;

/**
 * A declaration or an option of the check was given a value it cannot work with: an empty reason, an empty prefix, an
 * unknown format.
 */
final class InvalidConfiguration extends InvalidArgumentException implements RouteAccessCheckException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
