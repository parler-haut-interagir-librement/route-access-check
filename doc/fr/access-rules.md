<!-- phil-dev source-sha256=f2109ecca2fe2065d67888fec6bd47bf2c719c96c5a7681242f469abd1e68c23 -->
# Règles d'accès

Une vérification lit chaque route et demande ses règles à chaque détecteur. Elle décide ensuite de l'état de la route
d'après les règles qui couvrent chacun de ses libellés de méthode.

## Comment une route est lue

`RouteReader` transforme une route Symfony en `AnalysedRoute` :

- **Ses libellés de méthode.** Les méthodes de la route, avec `HEAD` ajouté à côté de `GET`, comme le fait le routeur
  de Symfony. Une route sans méthodes les accepte toutes : elle reçoit `GET`, `HEAD`, `POST`, `PUT`, `PATCH`,
  `DELETE`, `OPTIONS` et `*`, qui représente toute autre méthode. Chaque libellé doit être couvert.
- **Son contrôleur.** `ControllerLocator` lit la valeur par défaut `_controller` : `Class::method`,
  `[Class, 'method']`, une `Class` invocable, ou un identifiant de service que sa table résout en classe. Les attributs
  de la classe et de l'action sont gardés comme attributs de réflexion, jamais instanciés. Une fermeture, un objet ou
  une classe inconnue n'a pas de contrôleur : la route est jugée sur ses options et sur la configuration seulement.
- **Ses requêtes d'exemple.** `RouteSample::all()` construit des chemins, et des hôtes quand la route en a un, à
  partir de la route compilée. Un paramètre dont l'exigence est une alternance de littéraux, comme `en|fr`, prend
  chacun d'eux, sa valeur par défaut d'abord ; tout autre paramètre prend sa valeur par défaut quand son exigence
  l'accepte, et à côté la première de quelques valeurs courantes — `1`, `a`, une date, un UUID, un ULID… — qu'accepte
  son exigence. Les exemples sont toutes les combinaisons de ces valeurs, seize au plus. Un exemple est inexact quand
  un paramètre qu'aucune de ces valeurs ne satisfait garde `1`, quand seule sa valeur par défaut le satisfait, ou
  quand les combinaisons dépassent seize : un chemin `access_control` écrit pour une seule valeur peut alors être
  manqué, et le rapport en avertit.

## Les sources de règles

| Détecteur | Source | Effet | Portée |
|---|---|---|---|
| `SecurityAttributeDetector` | `#[IsGranted]` sur la classe ou l'action | protégée, pour ses `methods` ou toutes ; publique pour `PUBLIC_ACCESS` | route |
| `PublicRouteDetector` | `#[PublicRoute]`, l'option de route `public` | publique | route |
| `SecurityConfigDetector` | pare-feu et entrées `access_control` | protégée, publique, non sécurisée, non vérifiée ou conditionnelle | configuration |

### `#[IsGranted]`

Chaque `#[IsGranted]` est une vérification qui s'exécute avant l'action : sur la classe, il couvre toutes les
actions. Son argument `methods`, sixième ou nommé, le limite à ces méthodes, `HEAD` ajouté à côté de `GET`, comme le
fait Symfony. L'attribut vérifié est décrit tel qu'il est écrit : un rôle, une expression, une fermeture.
`IsGranted('PUBLIC_ACCESS')` est une déclaration publique. D'autres classes d'attributs aux mêmes arguments —
l'attribut vérifié en premier — sont lues de la même façon quand on donne leurs noms à `SecurityAttributeDetector`.

### Pare-feu et `access_control`

`SecurityConfigDetector` essaie la configuration de sécurité sur chaque requête d'exemple de la route, une fois par
libellé de méthode, comme la sécurité de Symfony l'essaie sur une requête :

1. **Le premier pare-feu qui correspond.** Un pare-feu sans `pattern`, `host` ni correspondant correspond à toutes les
   requêtes. Quand aucun ne correspond, rien de ce qui est derrière un pare-feu ne s'applique : la route reçoit une
   règle *non vérifiée*, « aucun pare-feu ne correspond ».
2. **Un pare-feu avec `security: false`.** Pas de jeton, pas d'`access_control` : la route est *non sécurisée*, ce
   qui la couvre — elle est publique par la configuration.
3. **La première entrée `access_control` qui correspond.** Son chemin, son hôte et ses attributs (`route` est
   l'attribut `_route`) sont des expressions régulières comparées avec `PathRequestMatcher`, `HostRequestMatcher` et
   `AttributesRequestMatcher` ; ses méthodes sont comparées exactement. Une entrée de `PUBLIC_ACCESS` seul est
   *publique* ; une entrée avec des rôles ou `allow_if` est *protégée* ; une entrée sans l'un ni l'autre ne vérifie
   rien et, comme c'est la première correspondance, aucune entrée suivante ne s'applique : elle est *non vérifiée*.
4. **Aucune entrée ne correspond.** La route reçoit une règle *non vérifiée* qui nomme son pare-feu.

Chaque libellé de méthode garde le résultat de l'exemple qui le protège le moins — aucune règle qui couvre d'abord,
puis aucune vérification —, pour qu'une règle écrite pour une valeur d'un paramètre ne vaille jamais pour les autres.
Avec `{_locale}` qui prend `en|fr` et une entrée sur `^/en/admin` seulement, `/fr/admin/users` ne correspond à rien et
la route n'est pas couverte. Quand les exemples divergent, les règles du plus faible finissent par
`, on /fr/admin/users`, et celles des autres suivent comme règles *conditionnelles* qui finissent par
`, on /en/admin/users only` : signalées, et toujours comptées comme utilisées, mais sans rien couvrir.

Les attributs de la requête sont les valeurs par défaut de la route de type chaîne et `_route`. Une route sans hôte
est essayée sur l'hôte `route-access-check.invalid`, pour qu'une entrée limitée à un hôte ne couvre jamais une route
qui répond sur tous. Une entrée ou un pare-feu limité à des adresses de clients (`ips`), à un `port` ou à un
`request_matcher` sur mesure dépend de la requête plutôt que de la route. Il est signalé comme *conditionnel*, ne
couvre rien, et les entrées qui le suivent sont quand même essayées. Une table sans pare-feu essaie ses entrées sur
chaque route.

Le chemin, l'hôte et les attributs de chaque règle sont comparés une fois par route ; les libellés de méthode
qu'aucune règle ne nomme sont essayés une seule fois ensemble, comme la méthode de requête `UNLISTED`.

### La table de sécurité

`SecurityMap::fromArray()` lit la configuration sous forme de simples tableaux, la forme que
`phil/route-access-check-bundle` lit dans le conteneur :

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

| Clé | Type | Sens |
|---|---|---|
| `firewalls[].name` | chaîne non vide | Le nom du pare-feu, dans le rapport. |
| `firewalls[].security` | booléen, `true` par défaut | `false` : rien n'est vérifié derrière lui. |
| `firewalls[].request` | tableau, facultatif | Les requêtes auxquelles il correspond ; absent, toutes. |
| `access_control[].request` | tableau, `[]` par défaut | Les requêtes auxquelles l'entrée correspond ; vide, toutes. |
| `access_control[].roles` | chaîne ou liste de chaînes | Les rôles qu'elle exige. |
| `access_control[].allow_if` | chaîne ou null | L'expression qu'elle exige. |
| `access_control[].requires_channel` | chaîne ou null | Gardé pour mémoire ; il n'exige rien. |
| `request.path`, `request.host` | expression régulière ou null | Comparés comme la sécurité de Symfony les compare. |
| `request.methods`, `request.ips` | chaîne ou liste de chaînes | Méthodes en majuscules ; des adresses rendent la règle conditionnelle. |
| `request.port` | entier ou null | Rend la règle conditionnelle. |
| `request.attributes` | expression régulière par nom d'attribut | `_route` est ce que fixe `route:`. |
| `request.matcher` | identifiant de service ou null | Un correspondant de requête sur mesure : la règle est conditionnelle. |

Une table mal formée, une expression régulière que PCRE refuse ou plus de 10 000 règles lancent `InvalidSecurityMap`.

## L'état d'une route

Pour chaque libellé de méthode, on rassemble les règles qui s'y appliquent et le couvrent — protégées, publiques ou
non sécurisées :

| État | Quand | Échoue |
|---|---|---|
| `protected` | Chaque méthode a une règle protégée. Une vérification couvre une méthode quoi que fasse le reste : chaque vérification s'exécute. | non |
| `public` | Chaque méthode est couverte, par des règles publiques ou non sécurisées seulement. | non |
| `mixed` | Chaque méthode est couverte ; certaines sont protégées, les autres publiques. | non |
| `uncovered` | Certaines méthodes n'ont aucune règle qui les couvre. | oui |
| `conflict` | Une déclaration publique au niveau de la route et une vérification couvrent la même méthode, ou une vérification couvre une méthode derrière un pare-feu sans sécurité, où elle refuse toujours. | oui |
| `ignored` | La `IgnoreList` met la route de côté. | non |

Un conflit l'emporte sur une méthode non couverte. Une entrée `access_control` de `PUBLIC_ACCESS` et un
`#[IsGranted]` sur la même route ne sont pas un conflit : la configuration laisse passer tout le monde jusqu'à
l'action, dont l'attribut vérifie ensuite.

Les routes ignorées sont quand même données aux détecteurs, pour qu'une règle écrite pour elles — une entrée de
`PUBLIC_ACCESS` pour le profileur — ne soit pas signalée comme inutilisée.

## Les règles inutilisées

Un détecteur qui implémente `RuleInventory` déclare ses règles avant les routes : `SecurityConfigDetector` déclare
chaque entrée `access_control` comme `access_control#<position>`. Une entrée qu'aucune règle de route ne nomme — elle
ne correspond à aucune route, ou une entrée précédente règle chaque route à laquelle elle correspond — est signalée
comme `UnusedRule`. C'est un avertissement : la vérification n'échoue à cause de lui que si on lui demande d'être
stricte.
