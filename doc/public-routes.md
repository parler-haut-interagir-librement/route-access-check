# Public routes

A public route declares itself, so that no route is public by omission. The declaration carries its reason, and the
report shows it beside the route.

## `#[PublicRoute]`

```php
use Phil\RouteAccessCheck\Attribute\PublicRoute;

final class SecurityController
{
    #[Route('/login', name: 'login')]
    #[PublicRoute(reason: 'The sign-in form is reached before signing in.')]
    public function login(): Response
    {
        // …
    }
}

#[PublicRoute(reason: 'The marketing pages.')]
final class PagesController
{
    // every action of the class is public
}
```

The reason is required: trimmed, it is not empty and holds at most 500 characters. A `#[PublicRoute]` whose reason is
empty declares nothing: the route stays uncovered, and its rule says why. The attribute changes nothing at runtime;
only the check reads it.

## The route option

A route without a controller class — a redirect, a template route, a route of a bundle — declares itself through its
`public` option, set to `true` or to the reason:

```yaml
# config/routes.yaml
health:
    path: /health
    controller: App\Controller\HealthController
    options: { public: 'Probed by the load balancer.' }
```

```php
$routes->add('legal', '/legal')->controller(TemplateController::class)->options(['public' => true]);
```

`PublicRouteDetector` takes another option name when `public` is already used by the application.

## Public through the configuration

Two rules of the security configuration also cover a route for everybody:

- an `access_control` entry whose roles are `PUBLIC_ACCESS` alone;
- a firewall with `security: false`.

They are written for a set of paths rather than for one route, so a route-level declaration remains the clearest:
the check accepts both.

## Contradictions

A route declared public at route level — by `#[PublicRoute]`, the route option or `IsGranted('PUBLIC_ACCESS')` — and
protected for the same method is a conflict: the check still runs, so the declaration is wrong, and the route is not
reachable as its author thinks. The usual cause is an `access_control` entry for a whole prefix, such as `^/admin`,
that also matches a help page declared public. Narrow the entry, or give the page its own `PUBLIC_ACCESS` entry before
it.

A route protected by `#[IsGranted]` behind a firewall with `security: false` is a conflict too: without a token, the
check always denies.
