<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use ReflectionClass;

/**
 * Finds the controller of a route from its `_controller` default: `Class::method`, an invokable `Class`, the array
 * `[Class, 'method']`, or a service id that the map of service classes resolves. A closure or an object is not
 * reflected: such a route is judged from its configuration and route options only.
 */
final readonly class ControllerLocator
{
    /**
     * @param array<string, string> $serviceClasses the class of each controller service whose id is not its class
     */
    public function __construct(
        private array $serviceClasses = [],
    ) {
    }

    public function locate(mixed $controller): ?Controller
    {
        [$id, $method] = $this->split($controller);

        if (null === $id) {
            return null;
        }

        $class = $this->serviceClasses[$id] ?? $id;

        if (!class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        $method ??= $reflection->hasMethod('__invoke') ? '__invoke' : null;
        $file = $reflection->getFileName();
        $line = $reflection->getStartLine();
        $methodAttributes = [];

        if (null !== $method && $reflection->hasMethod($method)) {
            $action = $reflection->getMethod($method);
            $methodAttributes = $action->getAttributes();
            $line = $action->getStartLine();
        }

        return new Controller(
            $class,
            $method,
            false === $file ? null : $file,
            false === $line ? null : $line,
            $reflection->getAttributes(),
            $methodAttributes,
        );
    }

    /**
     * @return array{?string, ?string}
     */
    private function split(mixed $controller): array
    {
        if (\is_array($controller) && \is_string($controller[0] ?? null) && \is_string($controller[1] ?? null)) {
            return [$controller[0], $controller[1]];
        }

        if (!\is_string($controller) || '' === $controller) {
            return [null, null];
        }

        if (str_contains($controller, '::')) {
            [$id, $method] = explode('::', $controller, 2);

            return ['' === $id ? null : $id, '' === $method ? null : $method];
        }

        return [$controller, null];
    }
}
