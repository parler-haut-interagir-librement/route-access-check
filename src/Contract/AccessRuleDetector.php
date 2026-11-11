<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Contract;

use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;

/**
 * Finds the access rules of a route from one source: the attributes of its controller, its options, the security
 * configuration, or a source of the application's own.
 *
 * A detector keeps no state between routes: the checker asks it once per route, in any order.
 */
interface AccessRuleDetector
{
    /**
     * @return iterable<AccessRule> the rules found, none when the source says nothing about the route
     */
    public function detect(AnalysedRoute $route): iterable;
}
