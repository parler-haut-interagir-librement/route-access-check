<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Attribute;

use Attribute;
use Phil\RouteAccessCheck\Exception\InvalidConfiguration;

/**
 * Declares that a controller, or one of its actions, is public on purpose, and says why:
 *
 * ```php
 * #[PublicRoute(reason: 'The sign-in form is reached before signing in.')]
 * public function signIn(): Response
 * ```
 *
 * The reason is kept in the report of each route, so that a reviewer reads why a route has no access rule. Read only
 * by the check: it changes nothing at runtime.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class PublicRoute
{
    public const int MAX_REASON_LENGTH = 500;

    public string $reason;

    public function __construct(string $reason)
    {
        $reason = trim($reason);

        if ('' === $reason) {
            throw InvalidConfiguration::because('A public route says why it is public: its reason is empty.');
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw InvalidConfiguration::because(\sprintf('The reason of a public route is at most %d characters.', self::MAX_REASON_LENGTH));
        }

        $this->reason = $reason;
    }
}
