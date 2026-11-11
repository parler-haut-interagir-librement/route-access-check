<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteStatus;
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\Report\UnusedRule;
use Phil\RouteAccessCheck\RuleScope;

/**
 * A report with a route of each kind, for the formats.
 */
final class SampleReport
{
    public static function build(): Report
    {
        $check = new AccessRule('access_control', AccessEffect::Protected, RuleScope::Configuration, 'access_control #1 (path ^/admin): ROLE_ADMIN', null, 'access_control#1');
        $partial = new AccessRule('attribute', AccessEffect::Protected, RuleScope::Route, "#[IsGranted('ROLE_USER')] on App\\Export for POST", ['POST']);

        return new Report(
            [
                new RouteVerdict(Routes::read('admin', '/admin', AdminController::class.'::dashboard', ['GET']), RouteStatus::Protected, [$check]),
                new RouteVerdict(Routes::read('export', '/export/{code}', AccountController::class.'::open', ['GET', 'POST'], 'shop.example.com'), RouteStatus::Uncovered, [$partial], ['GET', 'HEAD']),
                new RouteVerdict(Routes::read('legacy', '/legacy'), RouteStatus::Uncovered, [], ['*'], baselined: true),
                new RouteVerdict(Routes::read('_wdt', '/_wdt'), RouteStatus::Ignored),
                new RouteVerdict(Routes::read('closure', '/c', static fn (): string => 'x'), RouteStatus::Conflict, [], [], ["declared public, but\nprotected: 100%"]),
            ],
            [new UnusedRule('access_control#2', 'access_control #2 (path ^/old): ROLE_OLD')],
            ['gone'],
        );
    }
}
