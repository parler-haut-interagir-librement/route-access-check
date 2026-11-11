<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Report;

/**
 * What the check concludes for a route.
 */
enum RouteStatus: string
{
    /** Every method of the route has a check. */
    case Protected = 'protected';

    /** Every method of the route is public on purpose, or behind a firewall without security. */
    case Public = 'public';

    /** Some methods have a check and the others are public. */
    case Mixed = 'mixed';

    /** Some methods have no access rule. A failure. */
    case Uncovered = 'uncovered';

    /** A route declared public and protected at once, or protected behind a firewall without security. A failure. */
    case Conflict = 'conflict';

    /** Left aside by the ignore list. */
    case Ignored = 'ignored';

    public function isFailure(): bool
    {
        return self::Uncovered === $this || self::Conflict === $this;
    }
}
