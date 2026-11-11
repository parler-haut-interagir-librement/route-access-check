<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Detector;

use Closure;
use Override;
use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\Controller;
use Phil\RouteAccessCheck\RuleScope;
use ReflectionAttribute;
use Stringable;
use Throwable;

/**
 * The `#[IsGranted]` attributes of Symfony's security, on the controller class and on its action: each one is a check
 * that runs before the action, on the methods its `methods` argument names, or on all of them. `IsGranted('PUBLIC_ACCESS')`
 * declares the route public.
 *
 * The attributes are read by name, without loading their class: the library does not depend on symfony/security-http.
 * Other attributes with the same arguments — the attribute first, the methods sixth or named `methods` — are read
 * the same way when their class names are given.
 */
final readonly class SecurityAttributeDetector implements AccessRuleDetector
{
    public const string IS_GRANTED = 'Symfony\Component\Security\Http\Attribute\IsGranted';

    public const string PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    /**
     * @param list<string> $attributeClasses
     */
    public function __construct(
        private array $attributeClasses = [self::IS_GRANTED],
    ) {
    }

    #[Override]
    public function detect(AnalysedRoute $route): iterable
    {
        $controller = $route->controller;

        if (!$controller instanceof Controller) {
            return [];
        }

        $rules = [];

        foreach ($this->attributeClasses as $class) {
            foreach ($controller->attributesNamed($class) as [$attribute, $onMethod]) {
                $rules[] = $this->ruleOf($attribute, $onMethod ? $controller->label() : $controller->class);
            }
        }

        return $rules;
    }

    /**
     * @param ReflectionAttribute<object> $attribute
     */
    private function ruleOf(ReflectionAttribute $attribute, string $on): AccessRule
    {
        try {
            $arguments = $attribute->getArguments();
            $checked = $arguments['attribute'] ?? $arguments[0] ?? null;
            $described = $this->describe($checked);
        } catch (Throwable) {
            $arguments = [];
            $checked = null;
            $described = 'arguments that cannot be read';
        }

        $methods = $this->methodsOf($arguments['methods'] ?? $arguments[5] ?? []);
        $short = basename(str_replace('\\', '/', $attribute->getName()));
        $description = \sprintf('#[%s(%s)] on %s', $short, $described, $on);

        if (null !== $methods) {
            $description .= ' for '.implode(', ', $methods);
        }

        return new AccessRule(
            'attribute',
            self::PUBLIC_ACCESS === $checked ? AccessEffect::Public : AccessEffect::Protected,
            RuleScope::Route,
            $description,
            $methods,
        );
    }

    /**
     * @return list<string>|null
     */
    private function methodsOf(mixed $methods): ?array
    {
        $listed = [];

        foreach (\is_array($methods) ? $methods : [$methods] as $method) {
            if (\is_string($method) && '' !== $method) {
                $listed[] = strtoupper($method);
            }
        }

        if ([] === $listed) {
            return null;
        }

        if (\in_array('GET', $listed, true)) {
            $listed[] = 'HEAD';
        }

        return array_values(array_unique($listed));
    }

    private function describe(mixed $checked): string
    {
        return match (true) {
            \is_string($checked) => "'".$checked."'",
            $checked instanceof Closure => 'a closure',
            $checked instanceof Stringable => 'expression "'.$checked.'"',
            null === $checked => '',
            default => 'an object',
        };
    }
}
