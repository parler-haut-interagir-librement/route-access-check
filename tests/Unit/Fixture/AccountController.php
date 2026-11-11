<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Phil\RouteAccessCheck\Attribute\PublicRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions declared one by one: public, protected for some methods, public through IsGranted, or wrongly declared.
 */
final class AccountController
{
    #[PublicRoute(reason: 'The sign-in form is reached before signing in.')]
    public function signIn(): string
    {
        return 'sign-in';
    }

    #[PublicRoute(reason: 'Anyone reads a profile.')]
    #[IsGranted('ROLE_USER', null, null, null, null, 'post')]
    public function profile(): string
    {
        return 'profile';
    }

    #[IsGranted('PUBLIC_ACCESS')]
    public function home(): string
    {
        return 'home';
    }

    #[PublicRoute(reason: ' ')]
    public function blank(): string
    {
        return 'blank';
    }

    #[IsGranted(attribute: new GrantExpression('is_granted("ROLE_EDITOR")'), methods: 'GET')]
    public function edit(): string
    {
        return 'edit';
    }

    #[IsGranted(static function (): bool {
        return true;
    })]
    public function closure(): string
    {
        return 'closure';
    }

    #[IsGranted(new MissingExpression())]
    public function missing(): string
    {
        return 'missing';
    }

    #[IsGranted(new PlainController())]
    public function object(): string
    {
        return 'object';
    }

    public function open(): string
    {
        return 'open';
    }
}
