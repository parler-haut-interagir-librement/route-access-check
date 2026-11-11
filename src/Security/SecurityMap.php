<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Security;

use Phil\RouteAccessCheck\Exception\InvalidSecurityMap;

/**
 * The firewalls and the access_control entries of an application, in their order, read from plain arrays:
 *
 * ```php
 * SecurityMap::fromArray([
 *     'firewalls' => [
 *         ['name' => 'dev', 'request' => ['path' => '^/(_profiler|_wdt)'], 'security' => false],
 *         ['name' => 'main'],
 *     ],
 *     'access_control' => [
 *         ['request' => ['path' => '^/admin'], 'roles' => ['ROLE_ADMIN']],
 *     ],
 * ]);
 * ```
 *
 * A firewall without `request` matches every request. A `request` takes `path`, `host`, `methods`, `port`, `ips`,
 * `attributes` and `matcher` — the id of a custom request matcher service. An entry takes `request`, `roles`,
 * `allow_if` and `requires_channel`.
 */
final readonly class SecurityMap
{
    public const int MAX_RULES = 10_000;

    /**
     * @param list<Firewall>           $firewalls
     * @param list<AccessControlEntry> $accessControl
     */
    public function __construct(
        public array $firewalls = [],
        public array $accessControl = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $map
     */
    public static function fromArray(array $map): self
    {
        $firewalls = self::listAt($map, 'firewalls');
        $entries = self::listAt($map, 'access_control');

        if (\count($firewalls) + \count($entries) > self::MAX_RULES) {
            throw InvalidSecurityMap::because(\sprintf('it holds more than %d firewalls and entries.', self::MAX_RULES));
        }

        $read = [];

        foreach ($firewalls as $index => $firewall) {
            $at = 'firewalls['.$index.']';
            $name = $firewall['name'] ?? null;
            $security = $firewall['security'] ?? true;

            if (!\is_string($name) || '' === $name || !\is_bool($security)) {
                throw InvalidSecurityMap::because($at.' needs a name and a boolean security.');
            }

            $request = $firewall['request'] ?? null;
            $read[] = new Firewall($name, null === $request ? null : self::constraints($request, $at), $security);
        }

        $accessControl = [];

        foreach ($entries as $index => $entry) {
            $at = 'access_control['.$index.']';
            $accessControl[] = new AccessControlEntry(
                $index + 1,
                self::constraints($entry['request'] ?? [], $at),
                self::strings($entry['roles'] ?? [], $at.'.roles'),
                self::nullableString($entry['allow_if'] ?? null, $at.'.allow_if'),
                self::nullableString($entry['requires_channel'] ?? null, $at.'.requires_channel'),
            );
        }

        return new self($read, $accessControl);
    }

    public function isEmpty(): bool
    {
        return [] === $this->firewalls && [] === $this->accessControl;
    }

    /**
     * @param array<array-key, mixed> $map
     *
     * @return list<array<array-key, mixed>>
     */
    private static function listAt(array $map, string $key): array
    {
        $list = $map[$key] ?? [];

        if (!\is_array($list) || !array_is_list($list)) {
            throw InvalidSecurityMap::because($key.' is a list.');
        }

        $read = [];

        foreach ($list as $index => $item) {
            if (!\is_array($item)) {
                throw InvalidSecurityMap::because(\sprintf('%s[%d] is an array.', $key, $index));
            }

            $read[] = $item;
        }

        return $read;
    }

    private static function constraints(mixed $request, string $at): RequestConstraints
    {
        if (!\is_array($request)) {
            throw InvalidSecurityMap::because($at.'.request is an array.');
        }

        $port = $request['port'] ?? null;

        if (null !== $port && !\is_int($port)) {
            throw InvalidSecurityMap::because($at.'.request.port is an integer.');
        }

        $attributes = $request['attributes'] ?? [];

        if (!\is_array($attributes)) {
            throw InvalidSecurityMap::because($at.'.request.attributes is an array.');
        }

        $read = [];

        foreach ($attributes as $key => $regex) {
            if (!\is_string($key) || !\is_string($regex)) {
                throw InvalidSecurityMap::because($at.'.request.attributes holds regular expressions by attribute name.');
            }

            $read[$key] = $regex;
        }

        return new RequestConstraints(
            self::nullableString($request['path'] ?? null, $at.'.request.path'),
            self::nullableString($request['host'] ?? null, $at.'.request.host'),
            array_map(strtoupper(...), self::strings($request['methods'] ?? [], $at.'.request.methods')),
            $port,
            self::strings($request['ips'] ?? [], $at.'.request.ips'),
            $read,
            self::nullableString($request['matcher'] ?? null, $at.'.request.matcher'),
        );
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values, string $at): array
    {
        if (\is_string($values)) {
            $values = [$values];
        }

        if (!\is_array($values)) {
            throw InvalidSecurityMap::because($at.' is a list of strings.');
        }

        $read = [];

        foreach ($values as $value) {
            if (!\is_string($value) || '' === $value) {
                throw InvalidSecurityMap::because($at.' is a list of strings.');
            }

            $read[] = $value;
        }

        return $read;
    }

    private static function nullableString(mixed $value, string $at): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw InvalidSecurityMap::because($at.' is a string.');
        }

        return $value;
    }
}
