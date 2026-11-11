<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Contract;

/**
 * A detector whose rules are declared ahead of the routes, such as the access_control entries: the checker reports the
 * declared rules that no route's AccessRule names by its ruleId.
 */
interface RuleInventory
{
    /**
     * @return array<string, string> each declared rule's description, by its ruleId
     */
    public function declaredRules(): array;
}
