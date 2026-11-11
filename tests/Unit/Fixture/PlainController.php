<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

/**
 * A controller without attributes and without __invoke.
 */
final class PlainController
{
    public function index(): string
    {
        return 'index';
    }
}
