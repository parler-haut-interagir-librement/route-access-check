# Reports and baseline

`RouteAccessChecker::check()` returns a `Report`: a `RouteVerdict` per route, in the order of the route collection,
then the warnings. A format writes it as text; `$onlyProblems` leaves out the routes that pass.

## The report

| Member | What it holds |
|---|---|
| `verdicts` | Each route's status, rules, uncovered methods, conflicts, and whether the baseline tolerates it. |
| `unusedRules` | The declared rules no route reaches. |
| `staleBaselineEntries` | The route names of the baseline entries that no longer match a failing route. |
| `failures()`, `hasFailures()` | The uncovered and conflicting routes the baseline does not tolerate. |
| `inexactSamples()` | The verdicts of the routes, ignored ones aside, whose sample requests are inexact. |
| `hasWarnings()` | Whether unused rules, stale baseline entries or inexact samples remain. |
| `counts()`, `baselinedCount()` | The number of routes of each status, and of tolerated failures. |

A check fails on `hasFailures()`. Being strict — failing on `hasWarnings()` too — keeps the configuration free of dead
`access_control` entries, the baseline free of entries already fixed, and every verdict resting on samples that carry
each value a rule may decide on.

## The formats

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

Each route shows its status, name, methods, host and path, then `!` its problem, `-` each rule with its effect and the
methods it applies to when not all, and `~` an inexact sample path.

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

GitHub Actions workflow commands: an `::error` per failing route, on its controller's file and line when they are
known, a `::warning` per warning, then the summary line. `GitHubFormatter` takes the project directory, so that the
files are given relative to the repository, as GitHub expects. Messages and properties are escaped as the workflow
commands require.

```text
::error file=src/Controller/ReportController.php,line=14,title=Route access::Route "reports" (ANY /reports): no access rule for GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS, *.
```

### `junit`

A JUnit XML document, which continuous integration servers display as tests: a test case per route, a failure per
failing route, a skipped case per ignored or baselined route, and the warnings in the suite's `system-out`.

## The baseline

A large application adopts the check without fixing every route first: the baseline lists the failures it tolerates,
each route name with the status it failed with.

```json
{
    "version": 1,
    "routes": {
        "legacy_export": "uncovered",
        "admin_help": "conflict"
    }
}
```

- A route keeps failing when its status changes: an uncovered route that becomes a conflict is a new failure.
- An entry that no longer matches a failing route — the route was fixed, renamed or removed — is reported as stale.
- `Baseline::fromReport()` takes every failure of a report, tolerated or not; `toJson()` writes the routes sorted by
  name, so that a change reads as a small diff.
- `Baseline::fromJson()` reads at most 5 MB, at a depth of 4, and refuses any other document with `InvalidBaseline`:
  only `uncovered` and `conflict` are tolerated.

A numeric route name is kept as an integer key of `entries`, the way PHP keys arrays; `tolerates()` finds it from the
route's name all the same.
