<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Security;

use Phil\RouteAccessCheck\Exception\InvalidSecurityMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\AttributesRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * What a firewall or an access_control entry matches, with the request matchers of symfony/http-foundation, so that
 * the check reads them as Symfony's security does: `path` and `host` as regular expressions, `attributes` (and
 * `route`, which is the `_route` attribute) as regular expressions on the request attributes, and `methods` exactly.
 * The parts that do not depend on the method are matched once per route, the methods once per method label.
 *
 * Client addresses, a port and a custom matcher service depend on the request rather than on the route: constraints
 * that carry one are conditional, and never cover a route alone.
 */
final readonly class RequestConstraints
{
    /** @var list<RequestMatcherInterface> */
    private array $matchers;

    private string $description;

    /**
     * @param list<string>          $methods
     * @param list<string>          $ips
     * @param array<string, string> $attributes
     */
    public function __construct(
        public ?string $path = null,
        public ?string $host = null,
        public array $methods = [],
        public ?int $port = null,
        public array $ips = [],
        public array $attributes = [],
        public ?string $matcherService = null,
    ) {
        $matchers = [];

        if (null !== $path) {
            $this->assertRegex('path', $path);
            $matchers[] = new PathRequestMatcher($path);
        }

        if (null !== $host) {
            $this->assertRegex('host', $host);
            $matchers[] = new HostRequestMatcher($host);
        }

        foreach ($attributes as $regex) {
            $this->assertRegex('attributes', $regex);
        }

        if ([] !== $attributes) {
            $matchers[] = new AttributesRequestMatcher($attributes);
        }

        $this->matchers = $matchers;
        $this->description = $this->describeParts();
    }

    /**
     * Whether the parts that depend on the route and not on its method — path, host, attributes — accept the request.
     */
    public function matches(Request $request): bool
    {
        return array_all($this->matchers, static fn (PathRequestMatcher|HostRequestMatcher|AttributesRequestMatcher $matcher): bool => $matcher->matches($request));
    }

    /**
     * Whether the methods accept a request method, compared exactly, as MethodRequestMatcher does.
     */
    public function allowsMethod(string $method): bool
    {
        return [] === $this->methods || \in_array($method, $this->methods, true);
    }

    public function isConditional(): bool
    {
        return null !== $this->port || [] !== $this->ips || null !== $this->matcherService;
    }

    /**
     * `path ^/admin, methods POST`, or `every request`.
     */
    public function describe(): string
    {
        return $this->description;
    }

    private function describeParts(): string
    {
        $parts = [];

        foreach (['path' => $this->path, 'host' => $this->host, 'port' => $this->port, 'matcher' => $this->matcherService] as $name => $value) {
            if (null !== $value) {
                $parts[] = $name.' '.$value;
            }
        }

        foreach (['methods' => $this->methods, 'ips' => $this->ips] as $name => $values) {
            if ([] !== $values) {
                $parts[] = $name.' '.implode('|', $values);
            }
        }

        foreach ($this->attributes as $key => $regex) {
            $parts[] = ('_route' === $key ? 'route' : 'attribute '.$key).' '.$regex;
        }

        return [] === $parts ? 'every request' : implode(', ', $parts);
    }

    private function assertRegex(string $option, string $regex): void
    {
        if (false === @preg_match('{'.$regex.'}', '')) {
            throw InvalidSecurityMap::because(\sprintf('the %s "%s" is not a valid regular expression.', $option, $regex));
        }
    }
}
