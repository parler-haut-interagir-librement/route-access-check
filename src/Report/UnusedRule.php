<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Report;

/**
 * A declared rule that no route reaches: it matches no route, or only routes that an earlier rule already settles.
 */
final readonly class UnusedRule
{
    public function __construct(
        public string $id,
        public string $description,
    ) {
    }
}
