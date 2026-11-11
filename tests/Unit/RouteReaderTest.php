<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\RouteReader;
use Phil\RouteAccessCheck\RouteSample;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AdminController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

#[CoversClass(RouteReader::class)]
#[CoversClass(AnalysedRoute::class)]
final class RouteReaderTest extends TestCase
{
    public function testARouteWithoutMethodsTakesTheUsualOnesAndTheRest(): void
    {
        $route = new RouteReader()->read('home', new Route('/'));

        self::assertSame(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', '*'], $route->methods);
        self::assertTrue($route->acceptsAnyMethod());
        self::assertNull($route->controller);
        self::assertSame('home', $route->name);
        self::assertSame('/', $route->path());
        self::assertNull($route->host());
    }

    public function testGetBringsHead(): void
    {
        self::assertSame(['GET', 'POST', 'HEAD'], new RouteReader()->read('a', new Route('/a', methods: ['GET', 'POST']))->methods);
        self::assertSame(['HEAD', 'GET'], new RouteReader()->read('a', new Route('/a', methods: ['HEAD', 'GET']))->methods);
        self::assertSame(['POST'], new RouteReader()->read('a', new Route('/a', methods: ['POST']))->methods);
        self::assertSame(['GET', 'POST', 'HEAD'], new RouteReader()->read('a', new Route('/a', methods: ['GET', 'get', 'POST']))->methods);
        self::assertFalse(new RouteReader()->read('a', new Route('/a', methods: ['POST']))->acceptsAnyMethod());
    }

    public function testItReadsTheControllerAndTheSample(): void
    {
        $route = new RouteReader()->read('admin', new Route('/admin/{id}', ['_controller' => AdminController::class.'::dashboard'], ['id' => '[A-Z]{9}'], [], 'admin.example.com'));

        self::assertSame(AdminController::class, $route->controller?->class);
        self::assertSame('/admin/1', $route->samplePath);
        self::assertSame('admin.example.com', $route->sampleHost);
        self::assertSame('admin.example.com', $route->host());
        self::assertFalse($route->exactSample);
        self::assertSame([], $route->otherSamples);

        $locales = new RouteReader()->read('users', new Route('/{_locale}/users', [], ['_locale' => 'en|fr|de']));

        self::assertSame('/en/users', $locales->samplePath);
        self::assertSame(['/fr/users', '/de/users'], array_map(static fn (RouteSample $sample): string => $sample->path, $locales->otherSamples));
        self::assertTrue(new RouteReader()->read('a', new Route('/a'))->exactSample);
        self::assertTrue(new AnalysedRoute('a', new Route('/a'), ['GET'], null, '/a', null)->exactSample);
    }
}
