<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Benchmark;

use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Detector\SecurityAttributeDetector;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\Format\JsonFormatter;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AccountController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AdminController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\PublicController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The check of a large application in continuous integration: 2,000 routes — static paths, placeholders with
 * requirements, hosts, methods, controllers with attributes and without — against two firewalls and twenty-four
 * access_control entries, each route tried once per method label. And the JSON report of the result.
 */
final readonly class RouteAccessCheckBench
{
    private RouteCollection $routes;

    private RouteAccessChecker $checker;

    private Report $report;

    public function __construct()
    {
        $controllers = [AdminController::class.'::dashboard', AccountController::class.'::signIn', AccountController::class.'::profile', PublicController::class, null];
        $sections = ['admin', 'api', 'shop', 'blog', 'account', 'reports', 'help', 'legacy'];
        $this->routes = new RouteCollection();

        for ($i = 0; $i < 2_000; ++$i) {
            $section = $sections[$i % \count($sections)];
            $controller = $controllers[$i % \count($controllers)];
            $route = new Route(
                \sprintf('/%s/item%d/{id}/{slug}', $section, $i),
                null === $controller ? ['slug' => 'x'] : ['_controller' => $controller, 'slug' => 'x'],
                ['id' => '\d+', 'slug' => '[a-z0-9-]+'],
                0 === $i % 7 ? ['public' => true] : [],
                0 === $i % 11 ? '{tenant}.example.com' : '',
                [],
                0 === $i % 3 ? ['GET', 'POST'] : [],
            );
            $this->routes->add($section.'_'.$i, $route);
        }

        $entries = [];

        foreach ($sections as $index => $section) {
            $entries[] = ['request' => ['path' => '^/'.$section.'/item\d+/\d+/internal', 'ips' => ['10.0.0.0/8']], 'roles' => 'PUBLIC_ACCESS'];
            $entries[] = ['request' => ['path' => '^/'.$section.'/', 'methods' => ['POST']], 'roles' => 'ROLE_WRITER_'.$index];
            $entries[] = ['request' => ['path' => '^/'.$section.'/'], 'roles' => 0 === $index % 2 ? 'ROLE_USER' : 'PUBLIC_ACCESS'];
        }

        $map = SecurityMap::fromArray([
            'firewalls' => [
                ['name' => 'dev', 'request' => ['path' => '^/(_profiler|_wdt)'], 'security' => false],
                ['name' => 'main'],
            ],
            'access_control' => $entries,
        ]);

        $this->checker = new RouteAccessChecker([new SecurityAttributeDetector(), new PublicRouteDetector(), new SecurityConfigDetector($map)]);
        $this->report = $this->checker->check($this->routes);
    }

    public function benchCheckTwoThousandRoutes(): Report
    {
        return $this->checker->check($this->routes);
    }

    public function benchJsonReportOfTwoThousandRoutes(): string
    {
        return new JsonFormatter()->format($this->report);
    }
}
