<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\Contract\RuleInventory;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteStatus;
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\Report\UnusedRule;
use Symfony\Component\Routing\RouteCollection;

/**
 * Checks that every route of a collection has an access rule: each detector is asked for the rules of each route,
 * and each method label of the route must be covered by a protected, public or unsecured rule.
 *
 * A method is protected as soon as one check covers it, since every check runs; it is public when only public or
 * unsecured rules cover it. A route conflicts when a route-level public declaration and a check cover the same method
 * — the check wins, and the declaration is wrong — or when a check sits behind a firewall without security, where it
 * always denies.
 *
 * The ignored routes are still given to the detectors, so that a rule written for them is not reported as unused.
 */
final readonly class RouteAccessChecker
{
    /** @var array<AccessRuleDetector> */
    private array $detectors;

    /**
     * @param iterable<AccessRuleDetector> $detectors
     */
    public function __construct(
        iterable $detectors,
        private IgnoreList $ignoreList = new IgnoreList(),
        private RouteReader $reader = new RouteReader(),
    ) {
        $this->detectors = [...$detectors];
    }

    public function check(RouteCollection $routes, Baseline $baseline = new Baseline()): Report
    {
        $verdicts = [];
        $used = [];
        $tolerated = [];

        foreach ($routes->all() as $name => $route) {
            $analysed = $this->reader->read($name, $route);
            $rules = [];

            foreach ($this->detectors as $detector) {
                foreach ($detector->detect($analysed) as $rule) {
                    $rules[] = $rule;

                    if (null !== $rule->ruleId) {
                        $used[$rule->ruleId] = $rule->ruleId;
                    }
                }
            }

            $verdict = $this->ignoreList->ignores($analysed)
                ? new RouteVerdict($analysed, RouteStatus::Ignored, $rules)
                : $this->judge($analysed, $rules);

            if ($baseline->tolerates($verdict)) {
                $verdict = $verdict->withBaselined(true);
                $tolerated[$name] = $name;
            }

            $verdicts[] = $verdict;
        }

        return new Report(
            $verdicts,
            $this->unusedRules($used),
            array_map(strval(...), array_keys(array_diff_key($baseline->entries, $tolerated))),
        );
    }

    /**
     * @param list<AccessRule> $rules
     */
    private function judge(AnalysedRoute $route, array $rules): RouteVerdict
    {
        $uncovered = [];
        $conflicts = [];
        $protected = 0;

        foreach ($route->methods as $method) {
            $covering = array_filter($rules, static fn (AccessRule $rule): bool => $rule->effect->covers() && $rule->appliesTo($method));

            if ([] === $covering) {
                $uncovered[] = $method;

                continue;
            }

            $checks = array_filter($covering, static fn (AccessRule $rule): bool => AccessEffect::Protected === $rule->effect);

            if ([] !== $checks) {
                ++$protected;
            }

            foreach ($this->conflictsOf($covering, $checks) as $conflict) {
                $conflicts[$conflict] = $conflict;
            }
        }

        $status = match (true) {
            [] !== $conflicts => RouteStatus::Conflict,
            [] !== $uncovered => RouteStatus::Uncovered,
            \count($route->methods) === $protected => RouteStatus::Protected,
            0 === $protected => RouteStatus::Public,
            default => RouteStatus::Mixed,
        };

        return new RouteVerdict($route, $status, $rules, $uncovered, array_values($conflicts));
    }

    /**
     * @param array<int, AccessRule> $covering the rules that cover one method
     * @param array<int, AccessRule> $checks   the protected ones among them
     *
     * @return list<string>
     */
    private function conflictsOf(array $covering, array $checks): array
    {
        $conflicts = [];

        foreach ($covering as $rule) {
            $declared = AccessEffect::Public === $rule->effect && RuleScope::Route === $rule->scope;

            if (!$declared && AccessEffect::Unsecured !== $rule->effect) {
                continue;
            }

            foreach ($checks as $check) {
                $conflicts[] = $declared
                    ? \sprintf('declared public by %s, but protected by %s', $rule->description, $check->description)
                    : \sprintf('protected by %s behind %s, where the check always denies', $check->description, $rule->description);
            }
        }

        return $conflicts;
    }

    /**
     * @param array<string, string> $used
     *
     * @return list<UnusedRule>
     */
    private function unusedRules(array $used): array
    {
        $unused = [];

        foreach ($this->detectors as $detector) {
            if (!$detector instanceof RuleInventory) {
                continue;
            }

            foreach ($detector->declaredRules() as $id => $description) {
                if (!isset($used[$id])) {
                    $unused[] = new UnusedRule($id, $description);
                }
            }
        }

        return $unused;
    }
}
