<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Detector;

use Override;
use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Attribute\PublicRoute;
use Phil\RouteAccessCheck\Contract\AccessRuleDetector;
use Phil\RouteAccessCheck\Controller;
use Phil\RouteAccessCheck\Exception\InvalidConfiguration;
use Phil\RouteAccessCheck\RuleScope;

/**
 * The explicit declarations of a public route: `#[PublicRoute(reason: …)]` on the controller class or its action, and
 * the route option, `public` by default, set to `true` or to the reason itself.
 *
 * A `#[PublicRoute]` whose reason is empty declares nothing: the route stays uncovered, and its rule says why.
 */
final readonly class PublicRouteDetector implements AccessRuleDetector
{
    public const string DEFAULT_OPTION = 'public';

    public function __construct(
        private string $option = self::DEFAULT_OPTION,
    ) {
        if ('' === $option) {
            throw InvalidConfiguration::because('The route option of a public route has a name.');
        }
    }

    #[Override]
    public function detect(AnalysedRoute $route): iterable
    {
        $rules = [];

        if ($route->controller instanceof Controller) {
            foreach ($route->controller->attributesNamed(PublicRoute::class) as [$attribute, $onMethod]) {
                $on = $onMethod ? $route->controller->label() : $route->controller->class;

                try {
                    $declaration = $attribute->newInstance();
                } catch (InvalidConfiguration $invalidConfiguration) {
                    $rules[] = new AccessRule('public_route', AccessEffect::Unchecked, RuleScope::Route, \sprintf('#[PublicRoute] on %s declares nothing: %s', $on, $invalidConfiguration->getMessage()));

                    continue;
                }

                \assert($declaration instanceof PublicRoute);
                $rules[] = new AccessRule('public_route', AccessEffect::Public, RuleScope::Route, \sprintf('#[PublicRoute] on %s: %s', $on, $declaration->reason));
            }
        }

        $option = $route->route->getOption($this->option);

        if (true === $option || (\is_string($option) && '' !== trim($option))) {
            $reason = true === $option ? '' : ': '.trim($option);
            $rules[] = new AccessRule('route_option', AccessEffect::Public, RuleScope::Route, \sprintf('route option %s%s', $this->option, $reason));
        }

        return $rules;
    }
}
