<!-- phil-dev source-sha256=87d1ca5fdfca17e50001900cb2f6969cfdb6979d29be94e10a181ae3052e7a3f -->
# phil/route-access-check

**Chaque route a une règle d'accès, ou dit pourquoi elle est publique : c'est vérifié en intégration continue, avant
la mise en production.**

phil/route-access-check lit les routes d'une application et trouve, pour chacune, la règle qui décide qui peut
l'atteindre : un `#[IsGranted]` sur son contrôleur ou son action, la première entrée `access_control` qui lui
correspond, un pare-feu avec `security: false`, ou une déclaration publique explicite — `#[PublicRoute(reason: …)]`
ou une option de route. Une route qu'aucune d'elles ne couvre fait échouer la vérification. Une route qui se
contredit aussi : déclarée publique mais protégée, ou protégée derrière un pare-feu sans sécurité, où la vérification
refuse toujours.

Il s'adresse à toute application Symfony dont les routes se multiplient plus vite que quiconque ne les relit : un
nouveau contrôleur sans attribut, un chemin `access_control` qui ne correspond plus depuis le changement d'un préfixe,
une route de débogage laissée ouverte. Le rapport liste chaque route avec la règle trouvée, pour qu'un réviseur voie ce
qui la protège. Il liste aussi les entrées `access_control` qui n'atteignent aucune route, pour qu'on retire les
règles mortes. Une ligne de base tolère les échecs d'une grosse application pendant qu'elle adopte la vérification, et
elle ne fait que rétrécir.

C'est une bibliothèque PHP simple sur `symfony/routing` et `symfony/http-foundation`. Les entrées
`access_control` et les pare-feu sont comparés avec les correspondants de requête de symfony/http-foundation, ceux
qu'utilise la sécurité de Symfony, sur une requête d'exemple construite pour chaque route. Les attributs sont lus par
leur nom au moyen de la réflexion : symfony/security-http n'est pas une dépendance. Rien ne s'exécute en production ;
la vérification est un outil de développement et d'intégration continue.

## État

| Partie | État |
|---|---|
| `#[IsGranted]` sur la classe et l'action, avec ses `methods` ; `IsGranted('PUBLIC_ACCESS')` | Implémenté et testé |
| `#[PublicRoute(reason: …)]` et l'option de route `public` | Implémenté et testé |
| Pare-feu et entrées `access_control` comparés par libellé de méthode, la première correspondance l'emporte | Implémenté et testé |
| Conflits, méthodes non couvertes, entrées `access_control` inutilisées | Implémenté et testé |
| Ligne de base, rapports texte, JSON, annotations GitHub et JUnit | Implémenté et testé |
| Détecteurs et formats sur mesure par des interfaces | Implémenté et testé |
| Une commande console Symfony sur le routeur et la configuration de sécurité | Implémenté dans `phil/route-access-check-bundle` |
| Évaluer les expressions `allow_if` et les voters | Non prévu : une vérification est une vérification, quoi qu'elle décide |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, lancer une vérification sans le noyau de Symfony. |
| [Règles d'accès](doc/fr/access-rules.md) | Chaque source de règles, sa lecture, et comment l'état d'une route est décidé. |
| [Routes publiques](doc/fr/public-routes.md) | `#[PublicRoute]`, l'option de route, et quand une route est publique sans eux. |
| [Rapports et ligne de base](doc/fr/reports.md) | Les quatre formats, les avertissements, le fichier de ligne de base. |
| [Points d'extension](doc/fr/extension-points.md) | Les détecteurs et les formats propres à l'application. |
| [Exceptions](doc/fr/exceptions.md) | `RouteAccessCheckException` et ses trois exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, son seuil de mutation, sa cible de fuzzing et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require --dev phil/route-access-check
```

Dans une application Symfony, installez `phil/route-access-check-bundle` et lancez
`bin/console phil:route-access:check`. Sans lui :

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

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`, `symfony/routing` et `symfony/http-foundation`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
