<!-- phil-dev source-sha256=166ac73cf4bc1b00985d3cd94d116f687b9f3a93a329d2dcd2119805c161f7ef -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\RouteAccessCheck\Exception\RouteAccessCheckException`, qui étend
`Throwable` : un seul `catch` les attrape toutes.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidBaseline` | `InvalidArgumentException` | Une ligne de base n'est pas le document JSON versionné qu'écrit la bibliothèque, dépasse 5 Mo, ou tolère un état qui n'est pas un échec. |
| `InvalidSecurityMap` | `InvalidArgumentException` | La table de sécurité n'a pas la forme que lit `SecurityMap::fromArray()`, contient une expression régulière que PCRE refuse, ou plus de 10 000 règles. |
| `InvalidConfiguration` | `InvalidArgumentException` | La raison d'un `#[PublicRoute]` est vide ou dépasse 500 caractères, l'option de route de `PublicRouteDetector` n'a pas de nom, ou un préfixe ignoré est vide. |

Leurs messages nomment la partie fautive — `access_control[2].request.port is an integer.` — et jamais le contenu
d'un fichier au-delà. Un `#[PublicRoute]` dont la raison est vide n'arrête pas une vérification : la route reste non
couverte, et sa règle porte le message.
