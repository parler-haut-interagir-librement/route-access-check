<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Override;
use Stringable;

/**
 * An expression given to an attribute, as Symfony's Expression is.
 */
final readonly class GrantExpression implements Stringable
{
    public function __construct(
        private string $expression,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->expression;
    }
}
