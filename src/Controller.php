<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use ReflectionAttribute;

/**
 * The controller of a route, as reflection reads it: its class, its action, where they are written, and the attributes
 * of both. The attributes are read by name and never instantiated here, so that an attribute whose class is not
 * installed is still found.
 */
final readonly class Controller
{
    /**
     * @param class-string                      $class
     * @param list<ReflectionAttribute<object>> $classAttributes
     * @param list<ReflectionAttribute<object>> $methodAttributes
     */
    public function __construct(
        public string $class,
        public ?string $method,
        public ?string $file,
        public ?int $line,
        public array $classAttributes = [],
        public array $methodAttributes = [],
    ) {
    }

    /**
     * The attributes of that name, the class's first, each with whether it is written on the action.
     *
     * @return list<array{ReflectionAttribute<object>, bool}>
     */
    public function attributesNamed(string $name): array
    {
        $found = [];

        foreach ($this->classAttributes as $attribute) {
            if ($name === $attribute->getName()) {
                $found[] = [$attribute, false];
            }
        }

        foreach ($this->methodAttributes as $attribute) {
            if ($name === $attribute->getName()) {
                $found[] = [$attribute, true];
            }
        }

        return $found;
    }

    /**
     * `App\Controller\Admin::show`, or the class alone.
     */
    public function label(): string
    {
        return null === $this->method ? $this->class : $this->class.'::'.$this->method;
    }
}
