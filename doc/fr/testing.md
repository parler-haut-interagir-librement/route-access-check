<!-- phil-dev source-sha256=3b0fae5505fed09cf6f0011b102960f3ce0af7b3595700e66f16e25c01e0b5cf -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste la lecture des routes — libellés de méthode, contrôleurs sous toutes leurs formes,
attributs lus par leur nom, chemins et hôtes d'exemple pour chaque genre d'exigence, exemples inexacts —; chaque
détecteur — `#[IsGranted]` sur la classe et l'action, ses méthodes positionnelles ou nommées, les expressions, les
fermetures et les arguments illisibles, `#[PublicRoute]` et l'option de route, les pare-feu et les entrées
`access_control` par libellé de méthode, les entrées et les pare-feu conditionnels, les entrées sans rôles, les
entrées limitées à un hôte, les attributs de route —; la table de sécurité et sa validation ; les états, les conflits
et les règles inutilisées du vérificateur ; la ligne de base et ses limites ; et chaque format au caractère près.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/route-access-check/tests
```

La bibliothèque est tenue à un score de mutation couverte de 100 % par Infection.

## La cible de fuzzing

`tests/Fuzz/baseline.php` donne au lecteur de ligne de base du texte que personne n'a écrit. Quoi qu'il lise, il
retourne une ligne de base dont le propre document se relit avec les mêmes entrées, ou il le refuse avec
`InvalidBaseline`, et il ne lance rien d'autre. `composer fuzz baseline`, depuis la racine du monodépôt, la lance à
partir des germes de `tests/Fuzz/corpus/baseline/`.

## Le banc d'essai

`tests/Benchmark/RouteAccessCheckBench.php` vérifie 2 000 routes — statiques et avec paramètres, hôtes, méthodes,
contrôleurs avec et sans attributs — contre deux pare-feu et vingt-quatre entrées `access_control`, et écrit le
rapport JSON du résultat. `composer bench`, depuis la racine du monodépôt, le lance avec les autres bancs d'essai. Sur
la machine de développement, sans OPcache, une vérification des 2 000 routes prend environ 0,14 s et son rapport JSON
9 ms.

## Tester les routes d'une application

Lancez la vérification comme un test de l'application, pour qu'une route sans règle fasse échouer la suite autant que
l'étape d'intégration continue :

```php
use Phil\RouteAccessCheck\RouteAccessChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RouteAccessTest extends KernelTestCase
{
    public function testEveryRouteHasAnAccessRule(): void
    {
        $container = self::getContainer();
        $report = $container->get(RouteAccessChecker::class)->check($container->get('router')->getRouteCollection());

        self::assertSame([], array_map(static fn ($verdict): string => $verdict->route->name.': '.$verdict->problem(), $report->failures()));
    }
}
```

Avec `phil/route-access-check-bundle`, `RouteAccessChecker` est un service de l'environnement `test`.
