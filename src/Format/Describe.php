<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Format;

use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteVerdict;

/**
 * The phrases the formats share.
 *
 * @internal
 */
final class Describe
{
    /**
     * `GET|POST`, or `ANY` for a route without methods.
     */
    private static function methods(RouteVerdict $verdict): string
    {
        $methods = $verdict->route->route->getMethods();

        return [] === $methods ? 'ANY' : implode('|', $methods);
    }

    /**
     * `GET /admin/{id}`, with its host when it has one.
     */
    public static function route(RouteVerdict $verdict): string
    {
        $host = $verdict->route->host();

        return self::methods($verdict).' '.(null === $host ? '' : '//'.$host).$verdict->route->path();
    }

    /**
     * @return list<string>
     */
    public static function warnings(Report $report): array
    {
        $warnings = [];

        foreach ($report->unusedRules as $rule) {
            $warnings[] = \sprintf('%s reaches no route: no route matches it, or an earlier rule settles every route it matches.', $rule->description);
        }

        foreach ($report->staleBaselineEntries as $name) {
            $warnings[] = \sprintf('The baseline entry of route "%s" matches no failing route: remove it.', $name);
        }

        foreach ($report->inexactSamples() as $verdict) {
            $warnings[] = \sprintf('The sample path %s of route "%s" is inexact: a rule may decide on a placeholder value no sample carries.', $verdict->route->samplePath, $verdict->route->name);
        }

        return $warnings;
    }

    public static function summary(Report $report): string
    {
        $counts = $report->counts();
        $parts = [];

        foreach ($counts as $status => $count) {
            $parts[] = $count.' '.$status;
        }

        return \sprintf('%d routes: %s; %d baselined.', \count($report->verdicts), implode(', ', $parts), $report->baselinedCount());
    }

    /**
     * @return list<RouteVerdict>
     */
    public static function shown(Report $report, bool $onlyProblems): array
    {
        return $onlyProblems ? $report->failures() : $report->verdicts;
    }
}
