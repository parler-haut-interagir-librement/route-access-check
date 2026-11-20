<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A check on the class comes before the vote on a validated argument of the action; an invokable controller.
 */
#[IsGranted('ROLE_USER')]
final class GuardedPayloadController
{
    /**
     * @param array<string, mixed> $order
     */
    #[IsGranted('ORDER_EDIT', 'order')]
    public function __invoke(#[MapRequestPayload] array $order): string
    {
        return 'invoked '.\count($order);
    }
}
