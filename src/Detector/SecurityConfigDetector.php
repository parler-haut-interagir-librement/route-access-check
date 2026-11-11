<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Detector;

use Override;
use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\Contract\RuleInventory;
use Phil\RouteAccessCheck\RouteSample;
use Phil\RouteAccessCheck\RuleScope;
use Phil\RouteAccessCheck\Security\AccessControlEntry;
use Phil\RouteAccessCheck\Security\Firewall;
use Phil\RouteAccessCheck\Security\RequestConstraints;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Symfony\Component\HttpFoundation\Request;

/**
 * The firewalls and the access_control entries, tried on each sample request of a route, once per method label, the
 * way Symfony's security tries them on a request: the first firewall that matches; none, and access_control does not
 * apply; a firewall with `security: false`, and nothing is checked behind it; else the first access_control entry
 * that matches. A map without firewalls tries its access_control entries on every route.
 *
 * A route without a host is tried on a host no rule names, so that an entry restricted to one host never covers a
 * route that answers on all of them. An entry or a firewall restricted to some client addresses, a port or a custom
 * matcher is conditional: it is reported, and the entries after it are still tried.
 *
 * Each method label keeps the outcome of the sample that guards it least: a route whose `{_locale}` takes `en|fr` is
 * protected only if both `/en/…` and `/fr/…` are.
 *
 * The path, host and attributes of each rule are matched once per sample; the method labels no rule names are tried
 * once together, as the request method UNLISTED.
 */
final readonly class SecurityConfigDetector implements AccessRuleDetector, RuleInventory
{
    public const string UNNAMED_HOST = 'route-access-check.invalid';

    /** The request method a sample carries for the label `*`: one no rule lists. */
    public const string UNLISTED_METHOD = 'UNLISTED';

    /** @var list<string> the methods some firewall or entry names: the others all behave as the unlisted one */
    private array $namedMethods;

    public function __construct(
        private SecurityMap $map,
    ) {
        $named = [];

        foreach ($map->firewalls as $firewall) {
            $named = [...$named, ...$firewall->constraints->methods ?? []];
        }

        foreach ($map->accessControl as $entry) {
            $named = [...$named, ...$entry->constraints->methods];
        }

        $this->namedMethods = $named;
    }

    #[Override]
    public function detect(AnalysedRoute $route): iterable
    {
        if ($this->map->isEmpty()) {
            return [];
        }

        /** @var array<string, list<string>> $labels the method labels, by the request method they are tried as */
        $labels = [];

        foreach ($route->methods as $method) {
            $labels[\in_array($method, $this->namedMethods, true) ? $method : self::UNLISTED_METHOD][] = $method;
        }

        /** @var list<string> $samples */
        $samples = [];
        /** @var list<array<string, list<AccessRule>>> $outcomes the rules of each sample, by request method */
        $outcomes = [];

        foreach ([[$route->samplePath, $route->sampleHost], ...array_map(static fn (RouteSample $sample): array => [$sample->path, $sample->host], $route->otherSamples)] as [$path, $host]) {
            $request = Request::create($path, 'GET', server: ['HTTP_HOST' => $host ?? self::UNNAMED_HOST]);

            foreach ($route->route->getDefaults() as $key => $value) {
                if (\is_string($value)) {
                    $request->attributes->set((string) $key, $value);
                }
            }

            $request->attributes->set('_route', $route->name);
            $firewalls = array_map(static fn (Firewall $firewall): bool => !$firewall->constraints instanceof RequestConstraints || $firewall->constraints->matches($request), $this->map->firewalls);
            $entries = array_map(static fn (AccessControlEntry $entry): bool => $entry->constraints->matches($request), $this->map->accessControl);
            $outcome = [];

            foreach (array_keys($labels) as $requestMethod) {
                $outcome[$requestMethod] = $this->evaluate($requestMethod, $firewalls, $entries);
            }

            $samples[] = (null === $host ? '' : '//'.$host).$path;
            $outcomes[] = $outcome;
        }

        /** @var array<string, array{AccessRule, list<string>}> $found */
        $found = [];

        foreach ($labels as $requestMethod => $methods) {
            foreach ($this->weakest($samples, $outcomes, $requestMethod) as $rule) {
                $found[$rule->description] ??= [$rule, []];
                $found[$rule->description][1] = [...$found[$rule->description][1], ...$methods];
            }
        }

        $rules = [];

        foreach ($found as [$rule, $methods]) {
            $rules[] = new AccessRule(
                $rule->source,
                $rule->effect,
                $rule->scope,
                $rule->description,
                \count($methods) === \count($route->methods) ? null : $methods,
                $rule->ruleId,
            );
        }

        return $rules;
    }

    #[Override]
    public function declaredRules(): array
    {
        $declared = [];

        foreach ($this->map->accessControl as $entry) {
            $declared[$entry->id()] = $entry->describe();
        }

        return $declared;
    }

    /**
     * The rules of the sample that leaves one request method the least guarded — none covers it, then none checks
     * it — so that a rule written for one value of a placeholder never stands for the others. When the samples
     * disagree, its rules name that sample, and the rules of the other samples follow as conditional ones.
     *
     * @param list<string>                          $samples  the sample requests, as `//host/path`
     * @param list<array<string, list<AccessRule>>> $outcomes the rules of each sample, by request method
     *
     * @return list<AccessRule>
     */
    private function weakest(array $samples, array $outcomes, string $method): array
    {
        $weakest = array_find_key($outcomes, static fn (array $outcome): bool => !array_any($outcome[$method], static fn (AccessRule $rule): bool => $rule->effect->covers()))
            ?? array_find_key($outcomes, static fn (array $outcome): bool => !array_any($outcome[$method], static fn (AccessRule $rule): bool => AccessEffect::Protected === $rule->effect))
            ?? 0;
        $chosen = $outcomes[$weakest][$method];
        $descriptions = array_map(static fn (AccessRule $rule): string => $rule->description, $chosen);
        $agree = array_all($outcomes, static fn (array $outcome): bool => array_map(static fn (AccessRule $rule): string => $rule->description, $outcome[$method]) === $descriptions);

        if ($agree) {
            return $chosen;
        }

        $rules = [];

        foreach ($chosen as $rule) {
            $rules[] = $this->rule($rule->source, $rule->effect, \sprintf('%s, on %s', $rule->description, $samples[$weakest]), $rule->ruleId);
        }

        foreach ($outcomes as $index => $outcome) {
            foreach ($outcome[$method] as $rule) {
                if (!\in_array($rule->description, $descriptions, true)) {
                    $descriptions[] = $rule->description;
                    $rules[] = $this->rule($rule->source, $rule->effect->covers() ? AccessEffect::Conditional : $rule->effect, \sprintf('%s, on %s only', $rule->description, $samples[$index]), $rule->ruleId);
                }
            }
        }

        return $rules;
    }

    /**
     * @param array<int, bool> $firewalls whether each firewall matches the route, its methods aside
     * @param array<int, bool> $entries   whether each entry matches the route, its methods aside
     *
     * @return list<AccessRule>
     */
    private function evaluate(string $method, array $firewalls, array $entries): array
    {
        $rules = [];
        $firewall = null;

        foreach ($this->map->firewalls as $index => $candidate) {
            $constraints = $candidate->constraints;

            if (!$firewalls[$index] || (null !== $constraints && !$constraints->allowsMethod($method))) {
                continue;
            }

            if (null !== $constraints && $constraints->isConditional()) {
                $rules[] = $this->rule('firewall', AccessEffect::Conditional, \sprintf('firewall "%s" (%s) applies to some requests only', $candidate->name, $constraints->describe()));

                continue;
            }

            $firewall = $candidate;

            break;
        }

        if (!$firewall instanceof Firewall && [] !== $this->map->firewalls) {
            $rules[] = $this->rule('firewall', AccessEffect::Unchecked, 'no firewall matches: access_control does not apply');

            return $rules;
        }

        if ($firewall instanceof Firewall && !$firewall->security) {
            $rules[] = $this->rule('firewall', AccessEffect::Unsecured, \sprintf('firewall "%s" has security: false', $firewall->name));

            return $rules;
        }

        foreach ($this->map->accessControl as $index => $entry) {
            if (!$entries[$index] || !$entry->constraints->allowsMethod($method)) {
                continue;
            }

            if ($entry->constraints->isConditional()) {
                $rules[] = $this->rule('access_control', AccessEffect::Conditional, $entry->describe().', for some requests only', $entry->id());

                continue;
            }

            $rules[] = $this->rule('access_control', $entry->effect(), $entry->describe(), $entry->id());

            return $rules;
        }

        $rules[] = $this->rule('access_control', AccessEffect::Unchecked, $firewall instanceof Firewall ? \sprintf('no access_control entry matches behind firewall "%s"', $firewall->name) : 'no access_control entry matches');

        return $rules;
    }

    private function rule(string $source, AccessEffect $effect, string $description, ?string $ruleId = null): AccessRule
    {
        return new AccessRule($source, $effect, RuleScope::Configuration, $description, null, $ruleId);
    }
}
