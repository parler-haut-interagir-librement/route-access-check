<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Format;

use Override;
use Phil\RouteAccessCheck\Contract\ReportFormatter;
use Phil\RouteAccessCheck\Report\Report;

/**
 * The report for a person: each route with its status and the rules it rests on, then the warnings and a summary.
 */
final readonly class TextFormatter implements ReportFormatter
{
    #[Override]
    public function name(): string
    {
        return 'text';
    }

    #[Override]
    public function format(Report $report, bool $onlyProblems = false): string
    {
        $lines = [];

        foreach (Describe::shown($report, $onlyProblems) as $verdict) {
            $lines[] = \sprintf('%-9s %s  %s%s', strtoupper($verdict->status->value), $verdict->route->name, Describe::route($verdict), $verdict->baselined ? '  (baselined)' : '');

            if ($verdict->status->isFailure()) {
                $lines[] = '    ! '.$verdict->problem();
            }

            foreach ($verdict->rules as $rule) {
                $methods = null === $rule->methods ? '' : ' ['.implode(', ', $rule->methods).']';
                $lines[] = \sprintf('    - %s: %s%s', $rule->effect->value, $rule->description, $methods);
            }

            if (!$verdict->route->exactSample) {
                $lines[] = \sprintf('    ~ sample path %s: inexact, a rule may decide on a placeholder value no sample carries', $verdict->route->samplePath);
            }
        }

        $warnings = Describe::warnings($report);

        if ([] !== $warnings) {
            $lines[] = '';
            $lines[] = 'Warnings:';

            foreach ($warnings as $warning) {
                $lines[] = '  - '.$warning;
            }
        }

        $lines[] = '';
        $lines[] = Describe::summary($report);

        return implode("\n", $lines)."\n";
    }
}
