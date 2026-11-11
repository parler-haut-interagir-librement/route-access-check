<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Security;

/**
 * A firewall of the application, in its order: what it matches — every request when it has no constraints — and
 * whether its security is on.
 */
final readonly class Firewall
{
    public function __construct(
        public string $name,
        public ?RequestConstraints $constraints = null,
        public bool $security = true,
    ) {
    }
}
