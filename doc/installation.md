# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `new` without parentheses, closures in attributes. |
| `ext-mbstring` | any | The length of a public route's reason, in characters. |
| `symfony/routing` | `^8.2` | The routes, their compiled tokens and requirements. |
| `symfony/http-foundation` | `^8.2` | The request matchers Symfony's security uses, and the sample request they match. |

`symfony/security-http` is not required: `#[IsGranted]` is read by name. The library is a development dependency.

## Installing

```sh
composer require --dev phil/route-access-check
```

In a Symfony application, `phil/route-access-check-bundle` adds the console command, reads the security
configuration from the container and registers nothing in production.

## Running a check by hand

A check needs the routes, the detectors, and optionally an ignore list and a baseline:

```php
use Phil\RouteAccessCheck\Baseline;
use Phil\RouteAccessCheck\ControllerLocator;
use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Detector\SecurityAttributeDetector;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\IgnoreList;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\RouteReader;
use Phil\RouteAccessCheck\Security\SecurityMap;

$checker = new RouteAccessChecker(
    [new SecurityAttributeDetector(), new PublicRouteDetector(), new SecurityConfigDetector(SecurityMap::fromArray($security))],
    new IgnoreList([...IgnoreList::FRAMEWORK_ROUTE_NAMES, 'health_'], [...IgnoreList::FRAMEWORK_PATHS, '/.well-known/']),
    new RouteReader(new ControllerLocator(['app.admin_controller' => AdminController::class])),
);

$report = $checker->check($routes, Baseline::fromJson(file_get_contents('route-access-baseline.json')));
```

`$security` is the description of the firewalls and `access_control` entries: see
[Access rules](access-rules.md#the-security-map). The map given to `ControllerLocator` names the class of each
controller service whose id is not its class.

The checker keeps no state between checks: one instance checks any number of collections.
