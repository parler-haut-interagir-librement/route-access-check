<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use Symfony\Component\Routing\Route;

/**
 * Reads a route into what the detectors look at: its method labels, its controller and its sample requests.
 */
final readonly class RouteReader
{
    public function __construct(
        private ControllerLocator $controllers = new ControllerLocator(),
    ) {
    }

    public function read(string $name, Route $route): AnalysedRoute
    {
        $samples = RouteSample::all($route);
        $sample = $samples[0];

        return new AnalysedRoute(
            $name,
            $route,
            $this->methodsOf($route),
            $this->controllers->locate($route->getDefault('_controller')),
            $sample->path,
            $sample->host,
            $sample->exact,
            \array_slice($samples, 1),
        );
    }

    /**
     * @return list<string>
     */
    private function methodsOf(Route $route): array
    {
        $methods = $route->getMethods();

        if ([] === $methods) {
            return [...AnalysedRoute::USUAL_METHODS, AnalysedRoute::ANY_OTHER_METHOD];
        }

        if (\in_array('GET', $methods, true) && !\in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }

        return array_values(array_unique($methods));
    }
}
