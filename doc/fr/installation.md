<!-- phil-dev source-sha256=a3566dbd047be3fcb440b4a2e8ac381ca5727d6501ec78c02e74aba3730c4b99 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses, fermetures dans les attributs. |
| `ext-mbstring` | toute | La longueur de la raison d'une route publique, en caractères. |
| `symfony/routing` | `^8.2` | Les routes, leurs jetons compilés et leurs exigences. |
| `symfony/http-foundation` | `^8.2` | Les correspondants de requête qu'utilise la sécurité de Symfony, et la requête d'exemple qu'ils comparent. |

`symfony/security-http` n'est pas requis : `#[IsGranted]` est lu par son nom. La bibliothèque est une dépendance de
développement.

## Installer

```sh
composer require --dev phil/route-access-check
```

Dans une application Symfony, `phil/route-access-check-bundle` ajoute la commande console, lit la configuration de
sécurité dans le conteneur et n'enregistre rien en production.

## Lancer une vérification à la main

Une vérification a besoin des routes, des détecteurs et, au choix, d'une liste d'exclusion et d'une ligne de base :

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

`$security` est la description des pare-feu et des entrées `access_control` : voyez
[Règles d'accès](access-rules.md#la-table-de-sécurité). La table donnée à `ControllerLocator` nomme la classe de
chaque service de contrôleur dont l'identifiant n'est pas sa classe.

Le vérificateur ne garde aucun état entre deux vérifications : une seule instance vérifie autant de collections que
vous voulez.
