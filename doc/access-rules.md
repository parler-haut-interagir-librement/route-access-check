# Access rules

A check reads each route, asks every detector for its rules, then decides the route's status from the rules that
cover each of its method labels.

## What a route is read as

`RouteReader` turns a Symfony route into an `AnalysedRoute`:

- **Its method labels.** The route's methods, with `HEAD` added beside `GET`, as Symfony's router does. A route
  without methods accepts any: it gets `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS` and `*`, which stands
  for every other method. Each label must be covered.
- **Its controller.** `ControllerLocator` reads the `_controller` default: `Class::method`, `[Class, 'method']`, an
  invokable `Class`, or a service id that its map resolves to a class. The attributes of the class and of the action
  are kept as reflection attributes, never instantiated. A closure, an object or an unknown class has no controller:
  the route is judged on its options and the configuration only.
- **Its sample requests.** `RouteSample::all()` builds paths, and hosts when the route has one, from the compiled
  route: a placeholder whose requirement is an alternation of literals, such as `en|fr`, takes each of them, its
  default first; any other placeholder takes its default when its requirement accepts it, and beside it the first of a
  few common values — `1`, `a`, a date, a UUID, a ULID… — its requirement accepts. The samples are every combination
  of these values, at most sixteen. A sample is inexact when a placeholder none of these values satisfies keeps `1`,
  when only its default does, or when the combinations go past sixteen: an `access_control` path written for one
  value may then be missed, and the report warns about it.

## The sources of rules

| Detector | Source | Effect | Scope |
|---|---|---|---|
| `SecurityAttributeDetector` | `#[IsGranted]` on the class or the action | protected, for its `methods` or all; public for `PUBLIC_ACCESS` | route |
| `PublicRouteDetector` | `#[PublicRoute]`, the `public` route option | public | route |
| `SecurityConfigDetector` | firewalls and `access_control` entries | protected, public, unsecured, unchecked or conditional | configuration |

### `#[IsGranted]`

Each `#[IsGranted]` is a check that runs before the action: on the class it covers every action. Its `methods`
argument, sixth or named, limits it to those methods, `HEAD` added beside `GET`, as Symfony does. The checked
attribute is described as written: a role, an expression, a closure. `IsGranted('PUBLIC_ACCESS')` is a public
declaration. Other attribute classes with the same arguments — the checked attribute first — are read the same way
when `SecurityAttributeDetector` is given their names.

### Firewalls and `access_control`

`SecurityConfigDetector` tries the security configuration on each sample request of the route, once per method label,
the way Symfony's security tries it on a request:

1. **The first firewall that matches.** A firewall without `pattern`, `host` nor matcher matches every request. When
   none matches, nothing behind a firewall applies: the route gets an *unchecked* rule, "no firewall matches".
2. **A firewall with `security: false`.** No token, no `access_control`: the route is *unsecured*, which covers it —
   it is public through the configuration.
3. **The first `access_control` entry that matches.** Its path, host and attributes (`route` is the `_route`
   attribute) are regular expressions matched with `PathRequestMatcher`, `HostRequestMatcher` and
   `AttributesRequestMatcher`; its methods are compared exactly. An entry of `PUBLIC_ACCESS` alone is *public*; an
   entry with roles or `allow_if` is *protected*; an entry with neither checks nothing, and since it is the first
   match, no later entry applies: it is *unchecked*.
4. **No entry matches.** The route gets an *unchecked* rule naming its firewall.

Each method label keeps the outcome of the sample that guards it least — no covering rule first, then no check — so
that a rule written for one value of a placeholder never stands for the others. With `{_locale}` taking `en|fr` and
an entry on `^/en/admin` only, `/fr/admin/users` matches nothing and the route is uncovered. When the samples disagree,
the rules of the weakest one end with `, on /fr/admin/users`, and the rules of the others follow as *conditional*
rules ending with `, on /en/admin/users only`: reported, and still counted as used, but covering nothing.

The request attributes are the route's string defaults and `_route`. A route without a host is tried on the host
`route-access-check.invalid`, so that an entry restricted to one host never covers a route that answers on all of them.
An entry or a firewall restricted to client addresses (`ips`), a `port` or a custom `request_matcher` depends on the
request rather than on the route: it is reported as *conditional*, covers nothing, and the entries after it are still
tried. A map without firewalls tries its entries on every route.

The path, host and attributes of every rule are matched once per route; the method labels no rule names are tried
once together, as the request method `UNLISTED`.

### The security map

`SecurityMap::fromArray()` reads the configuration as plain arrays, the shape `phil/route-access-check-bundle` reads
from the container:

```php
SecurityMap::fromArray([
    'firewalls' => [
        ['name' => 'dev', 'request' => ['path' => '^/(_profiler|_wdt)'], 'security' => false],
        ['name' => 'api', 'request' => ['host' => '^api\.', 'methods' => ['GET', 'POST']]],
        ['name' => 'main'],
    ],
    'access_control' => [
        ['request' => ['path' => '^/login'], 'roles' => ['PUBLIC_ACCESS']],
        ['request' => ['path' => '^/internal', 'ips' => ['10.0.0.0/8']], 'roles' => ['PUBLIC_ACCESS']],
        ['request' => ['path' => '^/admin'], 'roles' => ['ROLE_ADMIN']],
        ['request' => ['attributes' => ['_route' => '^export_']], 'allow_if' => "is_granted('ROLE_EXPORT')"],
    ],
]);
```

| Key | Type | Meaning |
|---|---|---|
| `firewalls[].name` | non-empty string | The firewall's name, in the report. |
| `firewalls[].security` | bool, default `true` | `false`: nothing is checked behind it. |
| `firewalls[].request` | array, optional | What it matches; absent, every request. |
| `access_control[].request` | array, default `[]` | What the entry matches; empty, every request. |
| `access_control[].roles` | string or list of strings | The roles it requires. |
| `access_control[].allow_if` | string or null | The expression it requires. |
| `access_control[].requires_channel` | string or null | Kept for the record; it requires nothing. |
| `request.path`, `request.host` | regular expression or null | Matched as Symfony's security matches them. |
| `request.methods`, `request.ips` | string or list of strings | Methods in capitals; addresses make the rule conditional. |
| `request.port` | int or null | Makes the rule conditional. |
| `request.attributes` | regular expression by attribute name | `_route` is what `route:` sets. |
| `request.matcher` | service id or null | A custom request matcher: the rule is conditional. |

A malformed map, a regular expression PCRE refuses or more than 10,000 rules throw `InvalidSecurityMap`.

## The status of a route

For each method label, the rules that apply to it and cover it — protected, public or unsecured — are gathered:

| Status | When | Fails |
|---|---|---|
| `protected` | Every method has a protected rule. A check covers a method whatever else does: every check runs. | no |
| `public` | Every method is covered, by public or unsecured rules only. | no |
| `mixed` | Every method is covered; some are protected, the others public. | no |
| `uncovered` | Some methods have no covering rule. | yes |
| `conflict` | A route-level public declaration and a check cover the same method, or a check covers a method behind a firewall without security, where it always denies. | yes |
| `ignored` | The `IgnoreList` leaves the route aside. | no |

A conflict outranks an uncovered method. An `access_control` entry of `PUBLIC_ACCESS` and an `#[IsGranted]` on the
same route are not a conflict: the configuration lets everybody through to the action, whose attribute then checks.

The ignored routes are still given to the detectors, so that a rule written for them — an entry of `PUBLIC_ACCESS` for
the profiler — is not reported as unused.

## Unused rules

A detector that implements `RuleInventory` declares its rules ahead of the routes: `SecurityConfigDetector` declares
each `access_control` entry as `access_control#<position>`. An entry that no route's rule names — it matches no route,
or an earlier entry settles every route it matches — is reported as an `UnusedRule`. It is a warning: the check fails
on it only when asked to be strict.
