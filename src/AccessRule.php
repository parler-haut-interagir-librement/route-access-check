<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

/**
 * One rule a detector found for a route: its source, what it does, where it is written, what it says, and the
 * methods of the route it applies to.
 */
final readonly class AccessRule
{
    /**
     * @param string            $source      what found it, such as is_granted, access_control or a custom detector's name
     * @param string            $description the rule as a reader recognises it, such as `#[IsGranted('ROLE_ADMIN')] on App\Admin`
     * @param list<string>|null $methods     the method labels of the route it applies to; null, all of them
     * @param string|null       $ruleId      the identifier of a declared rule (see RuleInventory), to find the rules no route uses
     */
    public function __construct(
        public string $source,
        public AccessEffect $effect,
        public RuleScope $scope,
        public string $description,
        public ?array $methods = null,
        public ?string $ruleId = null,
    ) {
    }

    public function appliesTo(string $method): bool
    {
        return null === $this->methods || \in_array($method, $this->methods, true);
    }
}
