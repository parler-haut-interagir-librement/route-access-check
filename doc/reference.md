# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\RouteAccessCheck\Attribute\PublicRoute` | attribute | A controller or action public on purpose: `reason`, `MAX_REASON_LENGTH` |
| `Phil\RouteAccessCheck\RouteAccessChecker` | `final readonly class` | The check: `check(RouteCollection, Baseline)` |
| `Phil\RouteAccessCheck\RouteReader` | `final readonly class` | A route read into an `AnalysedRoute`: `read()` |
| `Phil\RouteAccessCheck\AnalysedRoute` | `final readonly class` | A route as the detectors see it: `methods`, `controller`, `samplePath`, `sampleHost`, `exactSample`, `otherSamples`, `path()`, `host()`, `acceptsAnyMethod()`, `USUAL_METHODS`, `ANY_OTHER_METHOD` |
| `Phil\RouteAccessCheck\RouteSample` | `final readonly class` | The sample paths and hosts of a route: `all()`, `of()` (the first), `MAX_SAMPLES` |
| `Phil\RouteAccessCheck\ControllerLocator` | `final readonly class` | The controller of a `_controller` default: `locate()` |
| `Phil\RouteAccessCheck\Controller` | `final readonly class` | A controller's class, action, file, line and attributes: `attributesNamed()`, `label()` |
| `Phil\RouteAccessCheck\AccessRule` | `final readonly class` | A rule found for a route: `source`, `effect`, `scope`, `description`, `methods`, `ruleId`, `appliesTo()` |
| `Phil\RouteAccessCheck\AccessEffect` | enum | `protected`, `public`, `unsecured`, `unchecked`, `conditional`; `covers()` |
| `Phil\RouteAccessCheck\RuleScope` | enum | `route`, `configuration` |
| `Phil\RouteAccessCheck\IgnoreList` | `final readonly class` | The routes left aside by prefix: `ignores()`, `FRAMEWORK_ROUTE_NAMES`, `FRAMEWORK_PATHS` |
| `Phil\RouteAccessCheck\Baseline` | `final readonly class` | The tolerated failures: `fromJson()`, `fromReport()`, `tolerates()`, `toJson()`, `VERSION`, `MAX_BYTES` |
| `Phil\RouteAccessCheck\Detector\SecurityAttributeDetector` | `final readonly class` | `#[IsGranted]` and attributes with its arguments: `IS_GRANTED`, `PUBLIC_ACCESS` |
| `Phil\RouteAccessCheck\Detector\PublicRouteDetector` | `final readonly class` | `#[PublicRoute]` and the route option: `DEFAULT_OPTION` |
| `Phil\RouteAccessCheck\Detector\SecurityConfigDetector` | `final readonly class` | Firewalls and `access_control` entries: `UNNAMED_HOST`, `UNLISTED_METHOD` |
| `Phil\RouteAccessCheck\Security\SecurityMap` | `final readonly class` | The firewalls and entries: `fromArray()`, `isEmpty()`, `MAX_RULES` |
| `Phil\RouteAccessCheck\Security\Firewall` | `final readonly class` | A firewall: `name`, `constraints`, `security` |
| `Phil\RouteAccessCheck\Security\AccessControlEntry` | `final readonly class` | An entry: `position`, `constraints`, `roles`, `allowIf`, `requiresChannel`, `id()`, `effect()`, `describe()` |
| `Phil\RouteAccessCheck\Security\RequestConstraints` | `final readonly class` | What a rule matches: `matches()`, `allowsMethod()`, `isConditional()`, `describe()` |
| `Phil\RouteAccessCheck\Report\Report` | `final readonly class` | The result: `verdicts`, `unusedRules`, `staleBaselineEntries`, `failures()`, `hasFailures()`, `inexactSamples()`, `hasWarnings()`, `counts()`, `baselinedCount()` |
| `Phil\RouteAccessCheck\Report\RouteVerdict` | `final readonly class` | A route's conclusion: `status`, `rules`, `uncoveredMethods`, `conflicts`, `baselined`, `fails()`, `problem()`, `withBaselined()` |
| `Phil\RouteAccessCheck\Report\RouteStatus` | enum | `protected`, `public`, `mixed`, `uncovered`, `conflict`, `ignored`; `isFailure()` |
| `Phil\RouteAccessCheck\Report\UnusedRule` | `final readonly class` | A declared rule no route reaches: `id`, `description` |
| `Phil\RouteAccessCheck\Format\TextFormatter` | `final readonly class` | The `text` format |
| `Phil\RouteAccessCheck\Format\JsonFormatter` | `final readonly class` | The `json` format |
| `Phil\RouteAccessCheck\Format\GitHubFormatter` | `final readonly class` | The `github` format, files relative to the project directory |
| `Phil\RouteAccessCheck\Format\JunitFormatter` | `final readonly class` | The `junit` format |
| `Phil\RouteAccessCheck\Format\Describe` | `final class`, internal | The phrases the formats share |
| `Phil\RouteAccessCheck\Contract\AccessRuleDetector` | interface | A source of rules: `detect()` |
| `Phil\RouteAccessCheck\Contract\RuleInventory` | interface | The rules declared ahead of the routes: `declaredRules()` |
| `Phil\RouteAccessCheck\Contract\ReportFormatter` | interface | A format: `name()`, `format()` |
| `Phil\RouteAccessCheck\Exception\RouteAccessCheckException` | interface | Every exception of the library |
| `Phil\RouteAccessCheck\Exception\InvalidBaseline` | `final class` | A baseline the library cannot read |
| `Phil\RouteAccessCheck\Exception\InvalidSecurityMap` | `final class` | A security map the library cannot read |
| `Phil\RouteAccessCheck\Exception\InvalidConfiguration` | `final class` | A declaration or an option the library cannot work with |
