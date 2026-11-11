<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Format;

use Override;
use Phil\RouteAccessCheck\Contract\ReportFormatter;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteVerdict;

/**
 * The report for a tool: the counts, each route with its resolved rules, the unused rules and the stale baseline
 * entries. The rules and the unused rules are written with their public properties, enums as their values.
 */
final readonly class JsonFormatter implements ReportFormatter
{
    #[Override]
    public function name(): string
    {
        return 'json';
    }

    #[Override]
    public function format(Report $report, bool $onlyProblems = false): string
    {
        return json_encode([
            'passed' => !$report->hasFailures(),
            'counts' => $report->counts(),
            'baselined' => $report->baselinedCount(),
            'routes' => array_map($this->verdict(...), Describe::shown($report, $onlyProblems)),
            'unusedRules' => $report->unusedRules,
            'staleBaselineEntries' => $report->staleBaselineEntries,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function verdict(RouteVerdict $verdict): array
    {
        $route = $verdict->route;

        return [
            'name' => $route->name,
            'path' => $route->path(),
            'host' => $route->host(),
            'methods' => $route->route->getMethods(),
            'controller' => $route->controller?->label(),
            'status' => $verdict->status->value,
            'baselined' => $verdict->baselined,
            'uncoveredMethods' => $verdict->uncoveredMethods,
            'conflicts' => $verdict->conflicts,
            'rules' => $verdict->rules,
            'samplePath' => $route->samplePath,
            'exactSample' => $route->exactSample,
        ];
    }
}
