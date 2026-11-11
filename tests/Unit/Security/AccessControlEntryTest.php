<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Security;

use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\Security\AccessControlEntry;
use Phil\RouteAccessCheck\Security\RequestConstraints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessControlEntry::class)]
#[CoversClass(AccessEffect::class)]
final class AccessControlEntryTest extends TestCase
{
    public function testItsEffectFollowsWhatItRequires(): void
    {
        $path = new RequestConstraints('^/');

        self::assertSame(AccessEffect::Public, new AccessControlEntry(1, $path, ['PUBLIC_ACCESS'])->effect());
        self::assertSame(AccessEffect::Protected, new AccessControlEntry(1, $path, ['PUBLIC_ACCESS', 'ROLE_USER'])->effect());
        self::assertSame(AccessEffect::Protected, new AccessControlEntry(1, $path, ['PUBLIC_ACCESS'], 'is_granted("X")')->effect());
        self::assertSame(AccessEffect::Protected, new AccessControlEntry(1, $path, ['ROLE_USER'])->effect());
        self::assertSame(AccessEffect::Protected, new AccessControlEntry(1, $path, [], 'is_granted("X")')->effect());
        self::assertSame(AccessEffect::Unchecked, new AccessControlEntry(1, $path, [], null, 'https')->effect());
    }

    public function testItDescribesItself(): void
    {
        $entry = new AccessControlEntry(3, new RequestConstraints('^/admin'), ['ROLE_ADMIN'], 'request.isSecure()');

        self::assertSame('access_control#3', $entry->id());
        self::assertSame('access_control #3 (path ^/admin): ROLE_ADMIN, allow_if "request.isSecure()"', $entry->describe());
        self::assertSame('access_control #1 (every request): no roles, nothing is checked', new AccessControlEntry(1, new RequestConstraints())->describe());
    }

    public function testOnlyProtectedPublicAndUnsecuredCover(): void
    {
        self::assertTrue(AccessEffect::Protected->covers());
        self::assertTrue(AccessEffect::Public->covers());
        self::assertTrue(AccessEffect::Unsecured->covers());
        self::assertFalse(AccessEffect::Unchecked->covers());
        self::assertFalse(AccessEffect::Conditional->covers());
    }
}
