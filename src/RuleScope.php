<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

/**
 * Where an access rule is written.
 */
enum RuleScope: string
{
    /** On the route or its controller: an attribute, a route option. */
    case Route = 'route';

    /** In the configuration of the application: a firewall, an access_control entry. */
    case Configuration = 'configuration';
}
