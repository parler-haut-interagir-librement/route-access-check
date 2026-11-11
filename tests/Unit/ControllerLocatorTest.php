<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use Phil\RouteAccessCheck\Attribute\PublicRoute;
use Phil\RouteAccessCheck\Controller;
use Phil\RouteAccessCheck\ControllerLocator;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AdminController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\PlainController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\PublicController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[CoversClass(ControllerLocator::class)]
#[CoversClass(Controller::class)]
final class ControllerLocatorTest extends TestCase
{
    public function testItReadsClassAndMethod(): void
    {
        $controller = new ControllerLocator()->locate(AdminController::class.'::purge');

        self::assertInstanceOf(Controller::class, $controller);
        self::assertSame(AdminController::class, $controller->class);
        self::assertSame('purge', $controller->method);
        self::assertSame(new ReflectionClass(AdminController::class)->getFileName(), $controller->file);
        self::assertSame(new ReflectionClass(AdminController::class)->getMethod('purge')->getStartLine(), $controller->line);
        self::assertSame(AdminController::class.'::purge', $controller->label());
        self::assertCount(1, $controller->classAttributes);
        self::assertCount(1, $controller->methodAttributes);

        $found = $controller->attributesNamed(IsGranted::class);
        self::assertCount(2, $found);
        self::assertFalse($found[0][1]);
        self::assertTrue($found[1][1]);
        self::assertSame(['ROLE_ADMIN'], $found[0][0]->getArguments());
        self::assertSame([], $controller->attributesNamed(PublicRoute::class));
    }

    public function testItReadsAnArrayAndAnInvokable(): void
    {
        $locator = new ControllerLocator();

        $array = $locator->locate([AdminController::class, 'dashboard']);
        self::assertInstanceOf(Controller::class, $array);
        self::assertSame('dashboard', $array->method);
        self::assertSame([], $array->methodAttributes);

        $invokable = $locator->locate(PublicController::class);
        self::assertInstanceOf(Controller::class, $invokable);
        self::assertSame('__invoke', $invokable->method);
        self::assertSame(PublicController::class.'::__invoke', $invokable->label());
        self::assertCount(1, $invokable->classAttributes);
    }

    public function testAClassWithoutTheMethodKeepsTheClassLine(): void
    {
        $plain = new ControllerLocator()->locate(PlainController::class);

        self::assertInstanceOf(Controller::class, $plain);
        self::assertNull($plain->method);
        self::assertSame(PlainController::class, $plain->label());
        self::assertSame(new ReflectionClass(PlainController::class)->getStartLine(), $plain->line);

        $missing = new ControllerLocator()->locate(PlainController::class.'::nowhere');
        self::assertInstanceOf(Controller::class, $missing);
        self::assertSame('nowhere', $missing->method);
        self::assertSame([], $missing->methodAttributes);
        self::assertSame(new ReflectionClass(PlainController::class)->getStartLine(), $missing->line);
    }

    public function testAServiceIdIsResolvedByTheMap(): void
    {
        $locator = new ControllerLocator(['app.admin' => AdminController::class]);

        self::assertSame(AdminController::class, $locator->locate('app.admin::dashboard')?->class);
        self::assertNull(new ControllerLocator()->locate('app.admin::dashboard'));
    }

    public function testWhatCannotBeReflectedIsLeftOut(): void
    {
        $locator = new ControllerLocator();

        foreach ([null, '', 42, new stdClass(), static fn (): string => 'x', ['only-one'], [AdminController::class, 3], [42, 'dashboard'], '::dashboard', 'Missing\Controller', 'Missing\Controller::x'] as $controller) {
            self::assertNull($locator->locate($controller), get_debug_type($controller));
        }

        self::assertNull($locator->locate(AdminController::class.'::')?->method);
        self::assertSame('purge::x', $locator->locate(AdminController::class.'::purge::x')?->method);
    }
}
