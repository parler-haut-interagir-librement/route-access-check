<!-- phil-dev source-sha256=30227e89707122a0110e321ae7e0adf2face686d5828a8a928192095c0eff671 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\RouteAccessCheck\Attribute\PublicRoute` | attribut | Un contrôleur ou une action publique exprès : `reason`, `MAX_REASON_LENGTH` |
| `Phil\RouteAccessCheck\RouteAccessChecker` | `final readonly class` | La vérification : `check(RouteCollection, Baseline)` |
| `Phil\RouteAccessCheck\RouteReader` | `final readonly class` | Une route lue en `AnalysedRoute` : `read()` |
| `Phil\RouteAccessCheck\AnalysedRoute` | `final readonly class` | Une route telle que la voient les détecteurs : `methods`, `controller`, `samplePath`, `sampleHost`, `exactSample`, `otherSamples`, `path()`, `host()`, `acceptsAnyMethod()`, `USUAL_METHODS`, `ANY_OTHER_METHOD` |
| `Phil\RouteAccessCheck\RouteSample` | `final readonly class` | Les chemins et les hôtes d'exemple d'une route : `all()`, `of()` (le premier), `MAX_SAMPLES` |
| `Phil\RouteAccessCheck\ControllerLocator` | `final readonly class` | Le contrôleur d'une valeur par défaut `_controller` : `locate()` |
| `Phil\RouteAccessCheck\Controller` | `final readonly class` | La classe, l'action, le fichier, la ligne et les attributs d'un contrôleur : `attributesNamed()`, `label()` |
| `Phil\RouteAccessCheck\AccessRule` | `final readonly class` | Une règle trouvée pour une route : `source`, `effect`, `scope`, `description`, `methods`, `ruleId`, `appliesTo()` |
| `Phil\RouteAccessCheck\AccessEffect` | enum | `protected`, `public`, `unsecured`, `unchecked`, `conditional` ; `covers()` |
| `Phil\RouteAccessCheck\RuleScope` | enum | `route`, `configuration` |
| `Phil\RouteAccessCheck\IgnoreList` | `final readonly class` | Les routes mises de côté par préfixe : `ignores()`, `FRAMEWORK_ROUTE_NAMES`, `FRAMEWORK_PATHS` |
| `Phil\RouteAccessCheck\Baseline` | `final readonly class` | Les échecs tolérés : `fromJson()`, `fromReport()`, `tolerates()`, `toJson()`, `VERSION`, `MAX_BYTES` |
| `Phil\RouteAccessCheck\Detector\SecurityAttributeDetector` | `final readonly class` | `#[IsGranted]` et les attributs qui ont ses arguments : `IS_GRANTED`, `PUBLIC_ACCESS` |
| `Phil\RouteAccessCheck\Detector\PublicRouteDetector` | `final readonly class` | `#[PublicRoute]` et l'option de route : `DEFAULT_OPTION` |
| `Phil\RouteAccessCheck\Detector\SecurityConfigDetector` | `final readonly class` | Pare-feu et entrées `access_control` : `UNNAMED_HOST`, `UNLISTED_METHOD` |
| `Phil\RouteAccessCheck\Security\SecurityMap` | `final readonly class` | Les pare-feu et les entrées : `fromArray()`, `isEmpty()`, `MAX_RULES` |
| `Phil\RouteAccessCheck\Security\Firewall` | `final readonly class` | Un pare-feu : `name`, `constraints`, `security` |
| `Phil\RouteAccessCheck\Security\AccessControlEntry` | `final readonly class` | Une entrée : `position`, `constraints`, `roles`, `allowIf`, `requiresChannel`, `id()`, `effect()`, `describe()` |
| `Phil\RouteAccessCheck\Security\RequestConstraints` | `final readonly class` | Ce à quoi une règle correspond : `matches()`, `allowsMethod()`, `isConditional()`, `describe()` |
| `Phil\RouteAccessCheck\Report\Report` | `final readonly class` | Le résultat : `verdicts`, `unusedRules`, `staleBaselineEntries`, `failures()`, `hasFailures()`, `inexactSamples()`, `hasWarnings()`, `counts()`, `baselinedCount()` |
| `Phil\RouteAccessCheck\Report\RouteVerdict` | `final readonly class` | La conclusion pour une route : `status`, `rules`, `uncoveredMethods`, `conflicts`, `baselined`, `fails()`, `problem()`, `withBaselined()` |
| `Phil\RouteAccessCheck\Report\RouteStatus` | enum | `protected`, `public`, `mixed`, `uncovered`, `conflict`, `ignored` ; `isFailure()` |
| `Phil\RouteAccessCheck\Report\UnusedRule` | `final readonly class` | Une règle déclarée qu'aucune route n'atteint : `id`, `description` |
| `Phil\RouteAccessCheck\Format\TextFormatter` | `final readonly class` | Le format `text` |
| `Phil\RouteAccessCheck\Format\JsonFormatter` | `final readonly class` | Le format `json` |
| `Phil\RouteAccessCheck\Format\GitHubFormatter` | `final readonly class` | Le format `github`, fichiers relatifs au répertoire du projet |
| `Phil\RouteAccessCheck\Format\JunitFormatter` | `final readonly class` | Le format `junit` |
| `Phil\RouteAccessCheck\Format\Describe` | `final class`, interne | Les formulations que partagent les formats |
| `Phil\RouteAccessCheck\Contract\AccessRuleDetector` | interface | Une source de règles : `detect()` |
| `Phil\RouteAccessCheck\Contract\RuleInventory` | interface | Les règles déclarées avant les routes : `declaredRules()` |
| `Phil\RouteAccessCheck\Contract\ReportFormatter` | interface | Un format : `name()`, `format()` |
| `Phil\RouteAccessCheck\Exception\RouteAccessCheckException` | interface | Toutes les exceptions de la bibliothèque |
| `Phil\RouteAccessCheck\Exception\InvalidBaseline` | `final class` | Une ligne de base que la bibliothèque ne peut pas lire |
| `Phil\RouteAccessCheck\Exception\InvalidSecurityMap` | `final class` | Une table de sécurité que la bibliothèque ne peut pas lire |
| `Phil\RouteAccessCheck\Exception\InvalidConfiguration` | `final class` | Une déclaration ou une option avec laquelle la bibliothèque ne peut pas travailler |
