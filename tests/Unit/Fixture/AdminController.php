<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A controller protected as a whole, by Symfony's attribute read by name.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminController
{
    public function dashboard(): string
    {
        return 'dashboard';
    }

    #[IsGranted('ROLE_SUPER_ADMIN', methods: ['DELETE'])]
    public function purge(): string
    {
        return 'purge';
    }
}
