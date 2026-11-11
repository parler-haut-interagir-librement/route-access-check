# Changelog

All notable changes to `phil/route-access-check` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `#[PublicRoute(reason: …)]`: a controller or an action public on purpose, its reason trimmed, required and at most
  500 characters.
- `RouteReader`, `AnalysedRoute`, `RouteSample` and `ControllerLocator`: each route read with its method labels —
  `HEAD` beside `GET`, the usual methods and `*` for a route without methods —, its controller and the attributes of
  its class and action read by name, and sample paths and hosts its requirements accept: each literal of an
  alternation, the default and another value, at most sixteen combinations, inexact when a value may be missed.
- `AccessRule`, `AccessEffect` and `RuleScope`: a rule's source, effect — protected, public, unsecured, unchecked or
  conditional —, scope, description, methods and declared identifier.
- `AccessRuleDetector` and three detectors: `SecurityAttributeDetector` (`#[IsGranted]` and attributes with its
  arguments, its `methods`, `PUBLIC_ACCESS`), `PublicRouteDetector` (the attribute and the `public` route option),
  `SecurityConfigDetector` (the firewalls and `access_control` entries of a `SecurityMap`, matched with the request
  matchers of symfony/http-foundation, the first match per method label on each sample, the least guarded sample deciding, client addresses,
  ports and matcher services conditional).
- `SecurityMap`, `Firewall`, `AccessControlEntry` and `RequestConstraints`: the security configuration read from plain
  arrays, validated, at most 10,000 rules, each regular expression checked.
- `RuleInventory`: the declared rules no route reaches, reported as `UnusedRule`.
- `IgnoreList`: the routes left aside by name or path prefix, the profiler, toolbar and error previews by default.
- `RouteAccessChecker`, `Report`, `RouteVerdict` and `RouteStatus`: a verdict per route — protected, public, mixed,
  uncovered, conflict or ignored —, the uncovered methods and the conflicts; inexact samples reported as
  warnings.
- `Baseline`: the tolerated failures by route name, as a versioned JSON document of at most 5 MB, sorted, its stale
  entries reported.
- `ReportFormatter` and four formats: `TextFormatter`, `JsonFormatter`, `GitHubFormatter` (workflow commands on the
  controller's file and line) and `JunitFormatter`.
- `RouteAccessCheckException`, implemented by `InvalidBaseline`, `InvalidSecurityMap` and `InvalidConfiguration`.
- The fuzz target of the baseline reader, and the benchmark of a check of 2,000 routes and of its JSON report.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
