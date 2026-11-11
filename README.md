# phil/route-access-check

**Every route has an access rule, or says why it is public: checked in continuous integration, before it ships.**

phil/route-access-check reads the routes of an application and, for each one, finds the rule that decides who may
reach it: a `#[IsGranted]` on its controller or action, the first `access_control` entry that matches it, a firewall
with `security: false`, or an explicit public declaration — `#[PublicRoute(reason: …)]` or a route option. A route
none of them covers fails the check. So does a route that contradicts itself: declared public but protected, or
protected behind a firewall without security, where the check always denies.

It is for any Symfony application whose routes multiply faster than anyone reviews them: a new controller without
an attribute, an `access_control` path that no longer matches after a prefix changed, a debug route left open. The
report lists every route with its resolved rule, so that a reviewer sees what protects it, and the `access_control`
entries that reach no route, so that dead rules are removed. A baseline tolerates the failures of a large application
while it adopts the check, and only ever shrinks.

It is a plain PHP library, written from scratch, on `symfony/routing` and `symfony/http-foundation`. The
`access_control` entries and the firewalls are matched with the request matchers of symfony/http-foundation, the ones
Symfony's security uses, on a sample request built for each route. The attributes are read by name through
reflection: symfony/security-http is not a dependency. Nothing runs in production; the check is a dev and CI tool.

## Status

| Part | State |
|---|---|
| `#[IsGranted]` on class and action, with its `methods`; `IsGranted('PUBLIC_ACCESS')` | Implemented and tested |
| `#[PublicRoute(reason: …)]` and the `public` route option | Implemented and tested |
| Firewalls and `access_control` entries matched per method label, first match wins | Implemented and tested |
| Conflicts, uncovered methods, unused `access_control` entries | Implemented and tested |
| Baseline, text, JSON, GitHub annotations and JUnit reports | Implemented and tested |
| Custom detectors and formats through interfaces | Implemented and tested |
| A Symfony console command over the router and the security configuration | Implemented in `phil/route-access-check-bundle` |
| Evaluating `allow_if` expressions and voters | Not planned: a check is a check, whatever it decides |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, running a check without Symfony's kernel. |
| [Access rules](doc/access-rules.md) | Each source of rules, how it is read, and how a route's status is decided. |
| [Public routes](doc/public-routes.md) | `#[PublicRoute]`, the route option, and when a route is public without them. |
| [Reports and baseline](doc/reports.md) | The four formats, the warnings, the baseline file. |
| [Extension points](doc/extension-points.md) | Detectors and formats of the application's own. |
| [Exceptions](doc/exceptions.md) | `RouteAccessCheckException` and its three exceptions. |
| [Testing](doc/testing.md) | The library's tests, mutation bar, fuzz target and benchmark. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require --dev phil/route-access-check
```

In a Symfony application, install `phil/route-access-check-bundle` and run `bin/console phil:route-access:check`.
Without it:

```php
use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Detector\SecurityAttributeDetector;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\Format\TextFormatter;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\Security\SecurityMap;

$map = SecurityMap::fromArray([
    'firewalls' => [['name' => 'dev', 'request' => ['path' => '^/(_profiler|_wdt)'], 'security' => false], ['name' => 'main']],
    'access_control' => [['request' => ['path' => '^/admin'], 'roles' => ['ROLE_ADMIN']]],
]);

$checker = new RouteAccessChecker([new SecurityAttributeDetector(), new PublicRouteDetector(), new SecurityConfigDetector($map)]);
$report = $checker->check($router->getRouteCollection());

echo new TextFormatter()->format($report);
exit($report->hasFailures() ? 1 : 0);
```

```php
use Phil\RouteAccessCheck\Attribute\PublicRoute;

#[PublicRoute(reason: 'The sign-in form is reached before signing in.')]
public function signIn(): Response
```

## Requirements

PHP 8.5 or later with `ext-mbstring`, `symfony/routing` and `symfony/http-foundation`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
