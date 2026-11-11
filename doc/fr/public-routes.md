<!-- phil-dev source-sha256=c035daf8c09f61afa3884d06038cc043bb820e36ede9305acb018639015b0212 -->
# Routes publiques

Une route publique se déclare elle-même, pour qu'aucune route ne soit publique par omission. La déclaration porte sa
raison, et le rapport l'affiche à côté de la route.

## `#[PublicRoute]`

```php
use Phil\RouteAccessCheck\Attribute\PublicRoute;

final class SecurityController
{
    #[Route('/login', name: 'login')]
    #[PublicRoute(reason: 'The sign-in form is reached before signing in.')]
    public function login(): Response
    {
        // …
    }
}

#[PublicRoute(reason: 'The marketing pages.')]
final class PagesController
{
    // every action of the class is public
}
```

La raison est obligatoire : une fois rognée, elle n'est pas vide et compte au plus 500 caractères. Un
`#[PublicRoute]` dont la raison est vide ne déclare rien : la route reste non couverte, et sa règle dit pourquoi.
L'attribut ne change rien à l'exécution ; seule la vérification le lit.

## L'option de route

Une route sans classe de contrôleur — une redirection, une route de gabarit, une route d'un bundle — se déclare par
son option `public`, réglée à `true` ou à la raison :

```yaml
# config/routes.yaml
health:
    path: /health
    controller: App\Controller\HealthController
    options: { public: 'Probed by the load balancer.' }
```

```php
$routes->add('legal', '/legal')->controller(TemplateController::class)->options(['public' => true]);
```

`PublicRouteDetector` accepte un autre nom d'option quand l'application utilise déjà `public`.

## Publique par la configuration

Deux règles de la configuration de sécurité couvrent aussi une route pour tout le monde :

- une entrée `access_control` dont les rôles se limitent à `PUBLIC_ACCESS` ;
- un pare-feu avec `security: false`.

Elles sont écrites pour un ensemble de chemins plutôt que pour une route, si bien qu'une déclaration au niveau de la
route reste la plus claire : la vérification accepte les deux.

## Contradictions

Une route déclarée publique au niveau de la route — par `#[PublicRoute]`, par l'option de route ou par
`IsGranted('PUBLIC_ACCESS')` — et protégée pour la même méthode est un conflit. La vérification s'exécute quand même :
la déclaration est donc fausse, et la route n'est pas accessible comme le croit son auteur. La cause habituelle est une
entrée `access_control` pour tout un préfixe, comme `^/admin`, qui correspond aussi à une page d'aide déclarée
publique. Restreignez l'entrée, ou donnez à la page sa propre entrée `PUBLIC_ACCESS` placée avant.

Une route protégée par `#[IsGranted]` derrière un pare-feu avec `security: false` est aussi un conflit : sans jeton,
la vérification refuse toujours.
