<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Exception;

use InvalidArgumentException;

/**
 * The description of the firewalls and of the access_control entries is not in the shape the library reads.
 */
final class InvalidSecurityMap extends InvalidArgumentException implements RouteAccessCheckException
{
    public static function because(string $reason): self
    {
        return new self('The security map is invalid: '.$reason);
    }
}
