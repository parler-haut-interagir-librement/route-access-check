<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use Phil\RouteAccessCheck\Exception\InvalidConfiguration;

/**
 * The routes the check leaves aside, by the prefix of their name or of their path: the profiler, the web debug
 * toolbar and the error previews of the framework by default, and whatever the application adds — a health probe,
 * `/.well-known/`.
 *
 * Prefixes rather than patterns: a list anyone can read, and a match in linear time.
 */
final readonly class IgnoreList
{
    /** @var list<string> */
    public const array FRAMEWORK_ROUTE_NAMES = ['_profiler', '_wdt', '_preview_error'];

    /** @var list<string> */
    public const array FRAMEWORK_PATHS = ['/_profiler', '/_wdt', '/_error'];

    /**
     * @param list<string> $routeNamePrefixes
     * @param list<string> $pathPrefixes
     */
    public function __construct(
        public array $routeNamePrefixes = self::FRAMEWORK_ROUTE_NAMES,
        public array $pathPrefixes = self::FRAMEWORK_PATHS,
    ) {
        foreach ([...$routeNamePrefixes, ...$pathPrefixes] as $prefix) {
            if ('' === $prefix) {
                throw InvalidConfiguration::because('An ignored prefix is not empty: it would leave every route aside.');
            }
        }
    }

    public function ignores(AnalysedRoute $route): bool
    {
        foreach ($this->routeNamePrefixes as $prefix) {
            if (str_starts_with($route->name, $prefix)) {
                return true;
            }
        }

        return array_any($this->pathPrefixes, static fn (string $prefix): bool => str_starts_with($route->path(), $prefix));
    }
}
