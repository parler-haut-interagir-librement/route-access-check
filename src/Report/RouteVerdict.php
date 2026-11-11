<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Report;

use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;

/**
 * The conclusion for one route, with the rules it rests on: the methods without a rule, the conflicts, and whether
 * the baseline tolerates its failure.
 */
final readonly class RouteVerdict
{
    /**
     * @param list<AccessRule> $rules
     * @param list<string>     $uncoveredMethods
     * @param list<string>     $conflicts
     */
    public function __construct(
        public AnalysedRoute $route,
        public RouteStatus $status,
        public array $rules = [],
        public array $uncoveredMethods = [],
        public array $conflicts = [],
        public bool $baselined = false,
    ) {
    }

    public function fails(): bool
    {
        return $this->status->isFailure() && !$this->baselined;
    }

    public function withBaselined(bool $baselined): self
    {
        return new self($this->route, $this->status, $this->rules, $this->uncoveredMethods, $this->conflicts, $baselined);
    }

    /**
     * Why the route fails, in one sentence; empty when it does not.
     */
    public function problem(): string
    {
        return match ($this->status) {
            RouteStatus::Uncovered => 'no access rule for '.implode(', ', $this->uncoveredMethods),
            RouteStatus::Conflict => implode('; ', $this->conflicts),
            default => '',
        };
    }
}
