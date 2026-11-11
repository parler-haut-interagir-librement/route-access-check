<!-- phil-dev source-sha256=49536353d8f807ce03dc2ede0d1fccb6388f67c0b19307d60e2a6ebdd058b5c6 -->
# Points d'extension

La bibliothèque a trois ports, des interfaces de `Phil\RouteAccessCheck\Contract`. Dans Symfony,
`phil/route-access-check-bundle` étiquette par autoconfiguration un service qui implémente `AccessRuleDetector` ou
`ReportFormatter`.

## `AccessRuleDetector`

Une source de règles d'accès propre à l'application : un voter qui garde tout un espace de noms de contrôleurs, un
écouteur d'URL signées, une passerelle devant certaines routes.

```php
interface AccessRuleDetector
{
    /**
     * @return iterable<AccessRule>
     */
    public function detect(AnalysedRoute $route): iterable;
}
```

- Le détecteur est interrogé une fois par route, dans n'importe quel ordre, et ne garde aucun état d'une route à
  l'autre.
- Il lit `$route->route` (la route Symfony : options, valeurs par défaut, exigences), `$route->methods` (les libellés
  à couvrir), `$route->controller` (sa classe, son action, son fichier, sa ligne et ses attributs, lus par leur nom
  avec `attributesNamed()`), ainsi que le chemin et l'hôte d'exemple.
- Chaque règle donne sa `source`, son `AccessEffect`, sa `RuleScope`, une `description` qu'un lecteur reconnaît, et
  les libellés de méthode auxquels elle s'applique — `null` pour tous. Une règle protégée, publique ou non sécurisée
  couvre ; une règle non vérifiée ou conditionnelle ne fait qu'informer.
- Une règle écrite sur la route (`RuleScope::Route`) et publique entre en conflit avec une vérification sur la même
  méthode ; une règle de la configuration, non.

Un détecteur pour les routes dont l'option `signed` vaut true, vérifiées par un écouteur de l'application :

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

Un détecteur d'attributs sur le contrôleur :

```php
foreach ($route->controller?->attributesNamed(RequiresSubscription::class) ?? [] as [$attribute, $onAction]) {
    yield new AccessRule('subscription', AccessEffect::Protected, RuleScope::Route, '#[RequiresSubscription] on '.($onAction ? $route->controller->label() : $route->controller->class));
}
```

Quand l'attribut prend les arguments de `#[IsGranted]`, `SecurityAttributeDetector` le lit sans détecteur à lui :
donnez-lui le nom de la classe de l'attribut.

## `RuleInventory`

Un détecteur dont les règles sont déclarées avant les routes l'implémente aussi, pour que les règles qu'aucune route
n'atteint soient signalées :

```php
interface RuleInventory
{
    /**
     * @return array<string, string> each declared rule's description, by its ruleId
     */
    public function declaredRules(): array;
}
```

Le détecteur donne à chaque `AccessRule` qu'il retourne le `ruleId` de la règle déclarée qu'il a appliquée.
`SecurityConfigDetector` déclare les entrées `access_control` comme `access_control#<position>`.

## `ReportFormatter`

Un format propre à l'application, sous un nom qu'accepte l'option `--format` de la commande console :

```php
interface ReportFormatter
{
    public function name(): string;

    public function format(Report $report, bool $onlyProblems = false): string;
}
```

Un format qui liste les échecs en CSV pour un tableur :

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

## La liste d'exclusion

`IgnoreList` n'est pas une interface : elle prend les préfixes des noms et des chemins de routes à mettre de côté.
`IgnoreList::FRAMEWORK_ROUTE_NAMES` et `IgnoreList::FRAMEWORK_PATHS` sont ses valeurs par défaut — le profileur, la
barre de débogage Web et les aperçus d'erreur —, à déployer dans une liste propre à l'application. Un préfixe vide est
refusé : il mettrait toutes les routes de côté.
