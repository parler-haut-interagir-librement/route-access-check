<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use Symfony\Component\Routing\Route;

/**
 * A route as the check reads it: its name and definition, the method labels its requests can carry, its controller,
 * and its sample requests — paths and hosts its own requirements accept, one per value of an alternation such as
 * `en|fr` — against which the request matchers of the configuration are tried.
 *
 * The method labels are the route's methods, `HEAD` added beside `GET`; a route without methods gets the usual
 * methods and `*`, which stands for every other method.
 */
final readonly class AnalysedRoute
{
    public const string ANY_OTHER_METHOD = '*';

    /** @var list<string> */
    public const array USUAL_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * @param list<string>      $methods      the method labels
     * @param bool              $exactSample  whether every placeholder got values its requirement accepts, and every one of them was tried
     * @param list<RouteSample> $otherSamples the sample requests after the first, each tried as well
     */
    public function __construct(
        public string $name,
        public Route $route,
        public array $methods,
        public ?Controller $controller,
        public string $samplePath,
        public ?string $sampleHost,
        public bool $exactSample = true,
        public array $otherSamples = [],
    ) {
    }

    public function path(): string
    {
        return $this->route->getPath();
    }

    public function host(): ?string
    {
        $host = $this->route->getHost();

        return '' === $host ? null : $host;
    }

    public function acceptsAnyMethod(): bool
    {
        return \in_array(self::ANY_OTHER_METHOD, $this->methods, true);
    }
}
