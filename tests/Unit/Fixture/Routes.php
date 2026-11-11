<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\ControllerLocator;
use Phil\RouteAccessCheck\RouteReader;
use Symfony\Component\Routing\Route;

/**
 * Routes read the way the checker reads them.
 */
final class Routes
{
    /**
     * @param list<string>         $methods
     * @param array<string, mixed> $options
     */
    public static function read(string $name, string $path, mixed $controller = null, array $methods = [], string $host = '', array $options = []): AnalysedRoute
    {
        $defaults = null === $controller ? [] : ['_controller' => $controller];

        return new RouteReader(new ControllerLocator())->read($name, new Route($path, $defaults, [], $options, $host, [], $methods));
    }
}
