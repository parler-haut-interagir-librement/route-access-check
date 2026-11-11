<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Override;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\Contract\RuleInventory;

/**
 * Rules given by route name, and declared rules.
 */
final readonly class StubDetector implements AccessRuleDetector, RuleInventory
{
    /**
     * @param array<string, list<AccessRule>> $rules
     * @param array<string, string>           $declared
     */
    public function __construct(
        private array $rules,
        private array $declared = [],
    ) {
    }

    #[Override]
    public function detect(AnalysedRoute $route): iterable
    {
        yield from $this->rules[$route->name] ?? [];
    }

    #[Override]
    public function declaredRules(): array
    {
        return $this->declared;
    }
}
