<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Report;

/**
 * The result of a check: a verdict per route, in the order of the route collection, and the warnings — the declared
 * rules no route reaches, the baseline entries that no longer match a failing route, and the routes not ignored whose
 * sample requests are inexact, so that their verdict may rest on a value no sample carries.
 */
final readonly class Report
{
    /**
     * @param list<RouteVerdict> $verdicts
     * @param list<UnusedRule>   $unusedRules
     * @param list<string>       $staleBaselineEntries the route names of the baseline entries nothing matches
     */
    public function __construct(
        public array $verdicts,
        public array $unusedRules = [],
        public array $staleBaselineEntries = [],
    ) {
    }

    /**
     * @return list<RouteVerdict>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->verdicts, static fn (RouteVerdict $verdict): bool => $verdict->fails()));
    }

    public function hasFailures(): bool
    {
        return [] !== $this->failures();
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->unusedRules || [] !== $this->staleBaselineEntries || [] !== $this->inexactSamples();
    }

    /**
     * The verdicts of the routes, ignored ones aside, whose sample requests are inexact.
     *
     * @return list<RouteVerdict>
     */
    public function inexactSamples(): array
    {
        return array_values(array_filter($this->verdicts, static fn (RouteVerdict $verdict): bool => !$verdict->route->exactSample && RouteStatus::Ignored !== $verdict->status));
    }

    /**
     * The number of routes of each status, every status present.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = array_fill_keys(array_map(static fn (RouteStatus $status): string => $status->value, RouteStatus::cases()), 0);

        foreach ($this->verdicts as $verdict) {
            ++$counts[$verdict->status->value];
        }

        return $counts;
    }

    public function baselinedCount(): int
    {
        return \count(array_filter($this->verdicts, static fn (RouteVerdict $verdict): bool => $verdict->baselined));
    }
}
