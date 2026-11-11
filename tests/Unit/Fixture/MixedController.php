<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Phil\RouteAccessCheck\Attribute\PublicRoute;

/**
 * A class-level declaration without a reason, an action declared properly, and attributes of an application's own read
 * by name: their class does not exist.
 */
#[PublicRoute(reason: '')]
final class MixedController
{
    #[PublicRoute(reason: 'The action is public.')]
    public function action(): string
    {
        return 'action';
    }

    #[Gate('ROLE_POSITIONAL', null, null, null, null, 'DELETE', attribute: 'ROLE_NAMED', methods: 'PUT')]
    public function both(): string
    {
        return 'both';
    }

    #[Gate('ROLE_A', methods: ['GET', 'get', 'HEAD', ''])]
    public function repeated(): string
    {
        return 'repeated';
    }
}
