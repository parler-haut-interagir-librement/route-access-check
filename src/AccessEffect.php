<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

/**
 * What an access rule does to the requests of a route.
 */
enum AccessEffect: string
{
    /** A check runs: a role, a permission or an expression must be granted. */
    case Protected = 'protected';

    /** Everybody is let in, on purpose: a public declaration, or an access_control entry of PUBLIC_ACCESS alone. */
    case Public = 'public';

    /** The request is behind a firewall whose security is off: no token, and no access_control entry applies. */
    case Unsecured = 'unsecured';

    /** A rule applies but checks nothing: an access_control entry without roles, a request outside every firewall. */
    case Unchecked = 'unchecked';

    /** A rule applies to some requests only — some client addresses, ports or a custom matcher — and covers none alone. */
    case Conditional = 'conditional';

    /**
     * Whether the rule settles who may reach the route: a protected, public or unsecured route has an access rule.
     */
    public function covers(): bool
    {
        return match ($this) {
            self::Protected, self::Public, self::Unsecured => true,
            self::Unchecked, self::Conditional => false,
        };
    }
}
