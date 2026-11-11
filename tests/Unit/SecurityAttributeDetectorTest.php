<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\Attribute\PublicRoute;
use Phil\RouteAccessCheck\Detector\SecurityAttributeDetector;
use Phil\RouteAccessCheck\RuleScope;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AccountController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AdminController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\MixedController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityAttributeDetector::class)]
#[CoversClass(AccessRule::class)]
final class SecurityAttributeDetectorTest extends TestCase
{
    public function testTheClassAttributeAndTheActionAttributeAreBothRules(): void
    {
        $rules = $this->detect(AdminController::class.'::purge');

        self::assertCount(2, $rules);
        self::assertEquals(new AccessRule('attribute', AccessEffect::Protected, RuleScope::Route, "#[IsGranted('ROLE_ADMIN')] on ".AdminController::class), $rules[0]);
        self::assertEquals(new AccessRule('attribute', AccessEffect::Protected, RuleScope::Route, "#[IsGranted('ROLE_SUPER_ADMIN')] on ".AdminController::class.'::purge for DELETE', ['DELETE']), $rules[1]);
        self::assertTrue($rules[0]->appliesTo('GET'));
        self::assertTrue($rules[1]->appliesTo('DELETE'));
        self::assertFalse($rules[1]->appliesTo('GET'));
    }

    public function testMethodsArePositionalOrNamedAndGetBringsHead(): void
    {
        self::assertSame(['POST'], $this->detect(AccountController::class.'::profile')[0]->methods);
        self::assertSame(['GET', 'HEAD'], $this->detect(AccountController::class.'::edit')[0]->methods);
    }

    public function testPublicAccessIsAPublicDeclaration(): void
    {
        $rules = $this->detect(AccountController::class.'::home');

        self::assertSame(AccessEffect::Public, $rules[0]->effect);
        self::assertSame("#[IsGranted('PUBLIC_ACCESS')] on ".AccountController::class.'::home', $rules[0]->description);
    }

    public function testExpressionsAndClosuresAreDescribed(): void
    {
        self::assertSame('#[IsGranted(expression "is_granted("ROLE_EDITOR")")] on '.AccountController::class.'::edit for GET, HEAD', $this->detect(AccountController::class.'::edit')[0]->description);
        self::assertSame('#[IsGranted(a closure)] on '.AccountController::class.'::closure', $this->detect(AccountController::class.'::closure')[0]->description);
        self::assertSame('#[IsGranted(arguments that cannot be read)] on '.AccountController::class.'::missing', $this->detect(AccountController::class.'::missing')[0]->description);
        self::assertSame(AccessEffect::Protected, $this->detect(AccountController::class.'::missing')[0]->effect);
    }

    public function testNothingWithoutControllerOrAttribute(): void
    {
        self::assertSame([], $this->detect(null));
        self::assertSame([], $this->detect(AccountController::class.'::open'));
        self::assertSame([], $this->detect(AdminController::class.'::dashboard', []));
    }

    public function testOtherAttributeClassesAreReadTheSameWay(): void
    {
        $rules = $this->detect(AccountController::class.'::signIn', [PublicRoute::class]);

        self::assertSame(AccessEffect::Protected, $rules[0]->effect);
        self::assertSame('#[PublicRoute()] on '.AccountController::class.'::signIn', $rules[0]->description);
        self::assertSame('#[IsGranted(an object)] on '.AccountController::class.'::object', $this->detect(AccountController::class.'::object')[0]->description);
    }

    public function testNamedArgumentsWinAndMethodsAreListedOnce(): void
    {
        $gate = 'Phil\RouteAccessCheck\Tests\Unit\Fixture\Gate';
        $both = $this->detect(MixedController::class.'::both', [$gate]);

        self::assertSame("#[Gate('ROLE_NAMED')] on ".MixedController::class.'::both for PUT', $both[0]->description);
        self::assertSame(['PUT'], $both[0]->methods);
        self::assertSame(['GET', 'HEAD'], $this->detect(MixedController::class.'::repeated', [$gate])[0]->methods);
    }

    /**
     * @param list<string>|null $classes
     *
     * @return list<AccessRule>
     */
    private function detect(?string $controller, ?array $classes = null): array
    {
        $detector = null === $classes ? new SecurityAttributeDetector() : new SecurityAttributeDetector($classes);

        return array_values([...$detector->detect(Routes::read('r', '/r', $controller))]);
    }
}
