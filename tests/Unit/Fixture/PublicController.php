<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Phil\RouteAccessCheck\Attribute\PublicRoute;

/**
 * An invokable controller public as a whole.
 */
#[PublicRoute(reason: 'The landing page.')]
final class PublicController
{
    public function __invoke(): string
    {
        return 'landing';
    }
}
