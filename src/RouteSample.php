<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck;

use LogicException;
use Symfony\Component\Routing\Route;

/**
 * The paths and hosts a route accepts, built from its compiled tokens, so that the access rules are tried on each.
 *
 * A placeholder whose requirement is an alternation of literals, such as `en|fr`, takes each of them, its default
 * first. Any other placeholder takes its default when its requirement accepts it, and the first of a few common
 * values its requirement accepts beside it, so that a rule written for the default alone is caught. The samples are
 * every combination of these values, at most sixteen.
 *
 * A sample is inexact when a placeholder kept `1` because no value satisfied its requirement, when only its default
 * did, or when the combinations went past sixteen: a rule may then decide on a value no sample carries.
 */
final readonly class RouteSample
{
    /** @var list<string> */
    private const array CANDIDATES = ['1', 'a', 'A', 'a1', 'a-1', '2026-01-01', '00000000-0000-4000-8000-000000000000', '01J0000000000000000000000A', 'aa', 'AA', 'en', 'x.y', '2'];

    private const string FALLBACK = '1';

    /** The characters of a requirement made of literal alternatives only, such as `en|fr`: letters, digits, `_`, `-`, `.` and `|`. */
    private const string LITERAL = 'a..zA..Z0..9_|.-';

    /** The most samples a route gets. */
    public const int MAX_SAMPLES = 16;

    /**
     * @param bool $exact whether every placeholder got values its requirement accepts, and every one of them was tried
     */
    private function __construct(
        public string $path,
        public ?string $host,
        public bool $exact,
    ) {
    }

    /**
     * The first sample of the route: each placeholder at its first value.
     */
    public static function of(Route $route): self
    {
        return self::all($route)[0];
    }

    /**
     * @return non-empty-list<self> every sample of the route, the one of() returns first
     */
    public static function all(Route $route): array
    {
        try {
            $compiled = $route->compile();
        } catch (LogicException) {
            $path = preg_replace('/\{[^}]*\}/', self::FALLBACK, $route->getPath()) ?? $route->getPath();
            $host = '' === $route->getHost() ? null : preg_replace('/\{[^}]*\}/', 'a', $route->getHost());

            return [new self($path, $host, false)];
        }

        $utf8 = true === $route->getOption('utf8');
        $exact = true;
        /** @var list<array{0: string, 1: string, 2?: string, 3?: string}> $tokens */
        $tokens = $compiled->getTokens();
        /** @var list<array{0: string, 1: string, 2?: string, 3?: string}> $hostTokens */
        $hostTokens = $compiled->getHostTokens();
        $hasHost = '' !== $route->getHost();
        $combinations = [['host' => [], 'path' => []]];

        foreach (['host' => $hasHost ? $hostTokens : [], 'path' => $tokens] as $part => $partTokens) {
            foreach ($partTokens as $index => $token) {
                if ('variable' !== $token[0]) {
                    continue;
                }

                $next = [];

                foreach ($combinations as $combination) {
                    foreach (self::valuesFor($token[2] ?? '[^/]++', $route->getDefault($token[3] ?? ''), $utf8, $exact) as $value) {
                        $combination[$part][$index] = $value;
                        $next[] = $combination;
                    }
                }

                if (\count($next) > self::MAX_SAMPLES) {
                    $exact = false;
                    $next = \array_slice($next, 0, self::MAX_SAMPLES);
                }

                $combinations = $next;
            }
        }

        $samples = [];

        foreach ($combinations as $combination) {
            $samples[] = new self(
                self::build($tokens, $combination['path']),
                $hasHost ? self::build($hostTokens, $combination['host']) : null,
                $exact,
            );
        }

        return $samples;
    }

    /**
     * @param list<array{0: string, 1: string, 2?: string, 3?: string}> $tokens the tokens of a compiled route, last first
     * @param array<int, string>                                        $values the value of each variable token, by index
     */
    private static function build(array $tokens, array $values): string
    {
        $built = '';

        foreach ($tokens as $index => $token) {
            $built = $token[1].($values[$index] ?? '').$built;
        }

        return $built;
    }

    /**
     * @return non-empty-array<int, string>
     */
    private static function valuesFor(string $regex, mixed $default, bool $utf8, bool &$exact): array
    {
        $pattern = '{^(?:'.$regex.')$}sD'.($utf8 ? 'u' : '');
        $accepts = static fn (string $candidate): bool => '' !== $candidate && 1 === preg_match($pattern, $candidate);
        $default = \is_string($default) || \is_int($default) ? (string) $default : null;
        $first = null !== $default && $accepts($default) ? [$default] : [];

        if ('' === ltrim($regex, self::LITERAL)) {
            $literals = array_unique(array_filter([...$first, ...explode('|', $regex)], $accepts));

            if ([] !== $literals) {
                return $literals;
            }
        }

        foreach (self::CANDIDATES as $candidate) {
            if ($candidate !== $default && $accepts($candidate)) {
                return [...$first, $candidate];
            }
        }

        $exact = false;

        return [] === $first ? [self::FALLBACK] : $first;
    }
}
