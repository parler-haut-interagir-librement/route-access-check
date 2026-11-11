# Extension points

The library has three ports, interfaces of `Phil\RouteAccessCheck\Contract`. In Symfony,
`phil/route-access-check-bundle` tags a service implementing `AccessRuleDetector` or `ReportFormatter` by
autoconfiguration.

## `AccessRuleDetector`

A source of access rules of the application's own: a voter that guards a whole controller namespace, a signed-URL
listener, a gateway in front of some routes.

```php
interface AccessRuleDetector
{
    /**
     * @return iterable<AccessRule>
     */
    public function detect(AnalysedRoute $route): iterable;
}
```

- The detector is asked once per route, in any order, and keeps no state between routes.
- It reads `$route->route` (the Symfony route: options, defaults, requirements), `$route->methods` (the labels to
  cover), `$route->controller` (its class, action, file, line and attributes, read by name with `attributesNamed()`),
  and the sample path and host.
- Each rule says its `source`, its `AccessEffect`, its `RuleScope`, a `description` a reader recognises, and the
  method labels it applies to — `null` for all of them. A protected, public or unsecured rule covers; an unchecked or
  conditional one only informs.
- A rule written on the route (`RuleScope::Route`) that is public conflicts with a check on the same method; a rule of
  the configuration does not.

A detector for the routes whose option `signed` is true, checked by a listener of the application:

```php
use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\RuleScope;

final readonly class SignedUrlDetector implements AccessRuleDetector
{
    public function detect(AnalysedRoute $route): iterable
    {
        if (true === $route->route->getOption('signed')) {
            yield new AccessRule('signed_url', AccessEffect::Protected, RuleScope::Route, 'a signed URL, checked by SignedUrlListener');
        }
    }
}
```

A detector of attributes on the controller:

```php
foreach ($route->controller?->attributesNamed(RequiresSubscription::class) ?? [] as [$attribute, $onAction]) {
    yield new AccessRule('subscription', AccessEffect::Protected, RuleScope::Route, '#[RequiresSubscription] on '.($onAction ? $route->controller->label() : $route->controller->class));
}
```

When the attribute takes the arguments of `#[IsGranted]`, `SecurityAttributeDetector` reads it without a detector of
its own: give it the attribute's class name.

## `RuleInventory`

A detector whose rules are declared ahead of the routes implements it too, so that the rules no route reaches are
reported:

```php
interface RuleInventory
{
    /**
     * @return array<string, string> each declared rule's description, by its ruleId
     */
    public function declaredRules(): array;
}
```

The detector gives each `AccessRule` it returns the `ruleId` of the declared rule it applied. `SecurityConfigDetector`
declares the `access_control` entries as `access_control#<position>`.

## `ReportFormatter`

A format of the application's own, under a name the console command's `--format` takes:

```php
interface ReportFormatter
{
    public function name(): string;

    public function format(Report $report, bool $onlyProblems = false): string;
}
```

A format that lists the failures as CSV for a spreadsheet:

```php
final readonly class CsvFormatter implements ReportFormatter
{
    public function name(): string
    {
        return 'csv';
    }

    public function format(Report $report, bool $onlyProblems = false): string
    {
        $lines = "route,status,problem\n";

        foreach ($report->failures() as $verdict) {
            $lines .= sprintf("%s,%s,\"%s\"\n", $verdict->route->name, $verdict->status->value, str_replace('"', '""', $verdict->problem()));
        }

        return $lines;
    }
}
```

## The ignore list

`IgnoreList` is not an interface: it takes the prefixes of the route names and paths to leave aside.
`IgnoreList::FRAMEWORK_ROUTE_NAMES` and `IgnoreList::FRAMEWORK_PATHS` are its defaults — the profiler, the web debug
toolbar and the error previews —, to spread into a list of the application's own. An empty prefix is refused: it
would leave every route aside.
