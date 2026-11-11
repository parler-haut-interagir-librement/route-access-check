<!-- phil-dev source-sha256=e6a5f9e45a3b7b9f83d3422a3db35219a870c1cab25bc0bf19fecc34d20ae7f9 -->
# Rapports et ligne de base

`RouteAccessChecker::check()` retourne un `Report` : un `RouteVerdict` par route, dans l'ordre de la collection de
routes, puis les avertissements. Un format l'écrit en texte ; `$onlyProblems` omet les routes qui passent.

## Le rapport

| Membre | Ce qu'il contient |
|---|---|
| `verdicts` | L'état, les règles, les méthodes non couvertes et les conflits de chaque route, et si la ligne de base la tolère. |
| `unusedRules` | Les règles déclarées qu'aucune route n'atteint. |
| `staleBaselineEntries` | Les noms de route des entrées de la ligne de base qui ne correspondent plus à une route en échec. |
| `failures()`, `hasFailures()` | Les routes non couvertes et en conflit que la ligne de base ne tolère pas. |
| `inexactSamples()` | Les verdicts des routes, ignorées à part, dont les requêtes d'exemple sont inexactes. |
| `hasWarnings()` | S'il reste des règles inutilisées, des entrées périmées dans la ligne de base ou des exemples inexacts. |
| `counts()`, `baselinedCount()` | Le nombre de routes de chaque état, et d'échecs tolérés. |

Une vérification échoue sur `hasFailures()`. Être strict — échouer aussi sur `hasWarnings()` — garde la
configuration libre d'entrées `access_control` mortes, la ligne de base libre d'entrées déjà corrigées, et chaque
verdict appuyé sur des exemples qui portent chaque valeur sur laquelle une règle peut décider.

## Les formats

### `text`

```text
PROTECTED admin_dashboard  GET /admin/dashboard
    - protected: #[IsGranted('ROLE_ADMIN')] on App\Controller\AdminController
    - protected: access_control #2 (path ^/admin): ROLE_ADMIN
UNCOVERED api_items  ANY /api/items
    ! no access rule for GET, HEAD, PATCH, DELETE, OPTIONS, *
    - unchecked: no access_control entry matches behind firewall "main" [GET, HEAD, PATCH, DELETE, OPTIONS, *]
    - protected: access_control #3 (path ^/api, methods POST|PUT): allow_if "is_granted('ROLE_API')" [POST, PUT]

Warnings:
  - access_control #4 (path ^/never): ROLE_NOBODY reaches no route: no route matches it, or an earlier rule settles every route it matches.

9 routes: 2 protected, 2 public, 0 mixed, 2 uncovered, 2 conflict, 1 ignored; 0 baselined.
```

Chaque route affiche son état, son nom, ses méthodes, son hôte et son chemin. Suivent `!` et son problème, `-` et
chaque règle avec son effet et les méthodes auxquelles elle s'applique quand ce n'est pas toutes, puis `~` pour un
chemin d'exemple inexact.

### `json`

```json
{
    "passed": false,
    "counts": {"protected": 2, "public": 2, "mixed": 0, "uncovered": 2, "conflict": 2, "ignored": 1},
    "baselined": 0,
    "routes": [
        {
            "name": "api_items", "path": "/api/items", "host": null, "methods": [],
            "controller": "App\\Controller\\ApiController::items", "status": "uncovered", "baselined": false,
            "uncoveredMethods": ["GET", "HEAD", "PATCH", "DELETE", "OPTIONS", "*"], "conflicts": [],
            "rules": [{"source": "access_control", "effect": "protected", "scope": "configuration", "description": "…", "methods": ["POST", "PUT"], "ruleId": "access_control#3"}],
            "samplePath": "/api/items", "exactSample": true
        }
    ],
    "unusedRules": [{"id": "access_control#4", "description": "access_control #4 (path ^/never): ROLE_NOBODY"}],
    "staleBaselineEntries": []
}
```

### `github`

Des commandes de workflow de GitHub Actions : un `::error` par route en échec, sur le fichier et la ligne de son
contrôleur quand on les connaît, un `::warning` par avertissement, puis la ligne de sommaire. `GitHubFormatter` prend
le répertoire du projet, pour que les fichiers soient donnés relativement au dépôt, comme GitHub l'attend. Les messages
et les propriétés sont échappés comme l'exigent les commandes de workflow.

```text
::error file=src/Controller/ReportController.php,line=14,title=Route access::Route "reports" (ANY /reports): no access rule for GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS, *.
```

### `junit`

Un document XML JUnit, que les serveurs d'intégration continue affichent comme des tests : un cas de test par route,
un échec par route en échec, un cas sauté par route ignorée ou tolérée par la ligne de base, et les avertissements dans
le `system-out` de la suite.

## La ligne de base

Une grosse application adopte la vérification sans d'abord corriger chaque route : la ligne de base liste les échecs
qu'elle tolère, chaque nom de route avec l'état qui l'a fait échouer.

```json
{
    "version": 1,
    "routes": {
        "legacy_export": "uncovered",
        "admin_help": "conflict"
    }
}
```

- Une route continue d'échouer quand son état change : une route non couverte qui devient un conflit est un nouvel
  échec.
- Une entrée qui ne correspond plus à une route en échec — la route a été corrigée, renommée ou retirée — est signalée
  comme périmée.
- `Baseline::fromReport()` prend chaque échec d'un rapport, toléré ou non ; `toJson()` écrit les routes triées par
  nom, pour qu'un changement se lise comme un petit diff.
- `Baseline::fromJson()` lit au plus 5 Mo, à une profondeur de 4, et refuse tout autre document avec
  `InvalidBaseline` : seuls `uncovered` et `conflict` sont tolérés.

Un nom de route numérique est gardé comme clé entière de `entries`, comme PHP indexe les tableaux ; `tolerates()` le
retrouve tout de même à partir du nom de la route.
