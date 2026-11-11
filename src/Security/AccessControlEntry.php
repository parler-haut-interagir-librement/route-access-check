<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Security;

use Phil\RouteAccessCheck\AccessEffect;

/**
 * An entry of `access_control`, at its position in the list, from 1: what it matches and what it requires.
 *
 * Symfony's security applies the first entry that matches a request and no other. An entry of `PUBLIC_ACCESS` alone
 * lets everybody in; an entry without roles nor `allow_if` matches and checks nothing, which stops the entries after it.
 */
final readonly class AccessControlEntry
{
    public const string PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    private string $description;

    private AccessEffect $effect;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        public int $position,
        public RequestConstraints $constraints,
        public array $roles = [],
        public ?string $allowIf = null,
        public ?string $requiresChannel = null,
    ) {
        $requires = $roles;

        if (null !== $allowIf) {
            $requires[] = 'allow_if "'.$allowIf.'"';
        }

        $this->description = \sprintf(
            'access_control #%d (%s): %s',
            $position,
            $constraints->describe(),
            [] === $requires ? 'no roles, nothing is checked' : implode(', ', $requires),
        );

        $this->effect = match (true) {
            null !== $allowIf => AccessEffect::Protected,
            [self::PUBLIC_ACCESS] === $roles => AccessEffect::Public,
            [] === $roles => AccessEffect::Unchecked,
            default => AccessEffect::Protected,
        };
    }

    public function id(): string
    {
        return 'access_control#'.$this->position;
    }

    public function effect(): AccessEffect
    {
        return $this->effect;
    }

    /**
     * `access_control #2 (path ^/admin): ROLE_ADMIN`.
     */
    public function describe(): string
    {
        return $this->description;
    }
}
