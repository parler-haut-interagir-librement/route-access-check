<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Attribute\PublicRoute;
use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Exception\InvalidConfiguration;
use Phil\RouteAccessCheck\RuleScope;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AccountController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\MixedController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\PublicController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublicRouteDetector::class)]
#[CoversClass(PublicRoute::class)]
final class PublicRouteDetectorTest extends TestCase
{
    public function testTheAttributeOnTheActionOrTheClassDeclaresTheRoutePublic(): void
    {
        self::assertEquals(
            [new AccessRule('public_route', AccessEffect::Public, RuleScope::Route, '#[PublicRoute] on '.AccountController::class.'::signIn: The sign-in form is reached before signing in.')],
            $this->detect(Routes::read('r', '/r', AccountController::class.'::signIn')),
        );
        self::assertEquals(
            [new AccessRule('public_route', AccessEffect::Public, RuleScope::Route, '#[PublicRoute] on '.PublicController::class.': The landing page.')],
            $this->detect(Routes::read('r', '/r', PublicController::class)),
        );
    }

    public function testABlankReasonDeclaresNothing(): void
    {
        $rules = $this->detect(Routes::read('r', '/r', AccountController::class.'::blank'));

        self::assertCount(1, $rules);
        self::assertSame(AccessEffect::Unchecked, $rules[0]->effect);
        self::assertSame('#[PublicRoute] on '.AccountController::class.'::blank declares nothing: A public route says why it is public: its reason is empty.', $rules[0]->description);
    }

    public function testAnInvalidDeclarationDoesNotHideTheNextOne(): void
    {
        $rules = $this->detect(Routes::read('r', '/r', MixedController::class.'::action'));

        self::assertSame([AccessEffect::Unchecked, AccessEffect::Public], array_map(static fn (AccessRule $rule): AccessEffect => $rule->effect, $rules));
        self::assertSame('#[PublicRoute] on '.MixedController::class.' declares nothing: A public route says why it is public: its reason is empty.', $rules[0]->description);
    }

    public function testTheRouteOption(): void
    {
        self::assertEquals([new AccessRule('route_option', AccessEffect::Public, RuleScope::Route, 'route option public')], $this->detect(Routes::read('r', '/r', options: ['public' => true])));
        self::assertEquals([new AccessRule('route_option', AccessEffect::Public, RuleScope::Route, 'route option public: Health probe.')], $this->detect(Routes::read('r', '/r', options: ['public' => ' Health probe. '])));

        foreach ([false, null, '', '  ', 1, 0] as $value) {
            self::assertSame([], $this->detect(Routes::read('r', '/r', options: ['public' => $value])), var_export($value, true));
        }

        self::assertSame([], $this->detect(Routes::read('r', '/r', AccountController::class.'::open')));
        self::assertEquals(
            [new AccessRule('route_option', AccessEffect::Public, RuleScope::Route, 'route option anonymous')],
            [...new PublicRouteDetector('anonymous')->detect(Routes::read('r', '/r', options: ['anonymous' => true, 'public' => true]))],
        );
    }

    public function testTheOptionHasAName(): void
    {
        $this->expectExceptionObject(InvalidConfiguration::because('The route option of a public route has a name.'));

        new PublicRouteDetector('');
    }

    public function testTheReasonIsTrimmedAndBounded(): void
    {
        self::assertSame('Why.', new PublicRoute("  Why.\n")->reason);
        self::assertSame(str_repeat('é', 500), new PublicRoute(str_repeat('é', 500))->reason);

        $this->expectExceptionObject(InvalidConfiguration::because('The reason of a public route is at most 500 characters.'));

        new PublicRoute(str_repeat('é', 501));
    }

    /**
     * @return list<AccessRule>
     */
    private function detect(AnalysedRoute $route): array
    {
        return array_values([...new PublicRouteDetector()->detect($route)]);
    }
}
