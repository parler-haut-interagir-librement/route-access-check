<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Security;

use Phil\RouteAccessCheck\Exception\InvalidSecurityMap;
use Phil\RouteAccessCheck\Security\AccessControlEntry;
use Phil\RouteAccessCheck\Security\Firewall;
use Phil\RouteAccessCheck\Security\RequestConstraints;
use Phil\RouteAccessCheck\Security\SecurityMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityMap::class)]
#[CoversClass(Firewall::class)]
#[CoversClass(InvalidSecurityMap::class)]
final class SecurityMapTest extends TestCase
{
    public function testItReadsFirewallsAndEntries(): void
    {
        $map = SecurityMap::fromArray([
            'firewalls' => [
                ['name' => 'dev', 'request' => ['path' => '^/_profiler'], 'security' => false],
                ['name' => 'main'],
            ],
            'access_control' => [
                ['request' => ['path' => '^/admin', 'host' => '^admin\.', 'methods' => ['get', 'post'], 'port' => 8443, 'ips' => '10.0.0.1', 'attributes' => ['_route' => '^admin'], 'matcher' => ''], 'roles' => 'ROLE_ADMIN', 'allow_if' => 'true', 'requires_channel' => 'https'],
                ['roles' => ['PUBLIC_ACCESS'], 'allow_if' => '', 'requires_channel' => null],
            ],
        ]);

        self::assertEquals(
            new SecurityMap(
                [new Firewall('dev', new RequestConstraints('^/_profiler'), false), new Firewall('main')],
                [
                    new AccessControlEntry(1, new RequestConstraints('^/admin', '^admin\.', ['GET', 'POST'], 8443, ['10.0.0.1'], ['_route' => '^admin']), ['ROLE_ADMIN'], 'true', 'https'),
                    new AccessControlEntry(2, new RequestConstraints(), ['PUBLIC_ACCESS']),
                ],
            ),
            $map,
        );
        self::assertFalse($map->isEmpty());
        self::assertTrue(SecurityMap::fromArray([])->isEmpty());
        self::assertFalse(SecurityMap::fromArray(['firewalls' => [['name' => 'main']]])->isEmpty());
        self::assertFalse(SecurityMap::fromArray(['access_control' => [['roles' => 'ROLE_USER']]])->isEmpty());
        self::assertSame('app.matcher', SecurityMap::fromArray(['access_control' => [['request' => ['matcher' => 'app.matcher']]]])->accessControl[0]->constraints->matcherService);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidMaps(): iterable
    {
        yield 'firewalls not a list' => [['firewalls' => ['a' => []]], 'firewalls is a list.'];
        yield 'firewalls not an array' => [['firewalls' => 'main'], 'firewalls is a list.'];
        yield 'entry not an array' => [['access_control' => ['x']], 'access_control[0] is an array.'];
        yield 'firewall without name' => [['firewalls' => [['security' => true]]], 'firewalls[0] needs a name and a boolean security.'];
        yield 'firewall with empty name' => [['firewalls' => [['name' => '']]], 'firewalls[0] needs a name and a boolean security.'];
        yield 'firewall with string security' => [['firewalls' => [['name' => 'a', 'security' => 'false']]], 'firewalls[0] needs a name and a boolean security.'];
        yield 'request not an array' => [['firewalls' => [['name' => 'a', 'request' => '^/']]], 'firewalls[0].request is an array.'];
        yield 'port not an integer' => [['access_control' => [['request' => ['port' => '80']]]], 'access_control[0].request.port is an integer.'];
        yield 'attributes not an array' => [['access_control' => [['request' => ['attributes' => 'x']]]], 'access_control[0].request.attributes is an array.'];
        yield 'attribute key not a string' => [['access_control' => [['request' => ['attributes' => ['x']]]]], 'access_control[0].request.attributes holds regular expressions by attribute name.'];
        yield 'attribute value not a string' => [['access_control' => [['request' => ['attributes' => ['_route' => 1]]]]], 'access_control[0].request.attributes holds regular expressions by attribute name.'];
        yield 'path not a string' => [['access_control' => [['request' => ['path' => 1]]]], 'access_control[0].request.path is a string.'];
        yield 'roles not a list' => [['access_control' => [['roles' => 1]]], 'access_control[0].roles is a list of strings.'];
        yield 'role empty' => [['access_control' => [['roles' => ['']]]], 'access_control[0].roles is a list of strings.'];
        yield 'role not a string' => [['access_control' => [['roles' => [1]]]], 'access_control[0].roles is a list of strings.'];
        yield 'methods not strings' => [['access_control' => [['request' => ['methods' => [true]]]]], 'access_control[0].request.methods is a list of strings.'];
        yield 'host not a string' => [['access_control' => [['request' => ['host' => []]]]], 'access_control[0].request.host is a string.'];
        yield 'ips not strings' => [['access_control' => [['request' => ['ips' => [1]]]]], 'access_control[0].request.ips is a list of strings.'];
        yield 'matcher not a string' => [['access_control' => [['request' => ['matcher' => 1]]]], 'access_control[0].request.matcher is a string.'];
        yield 'channel not a string' => [['access_control' => [['requires_channel' => 1]]], 'access_control[0].requires_channel is a string.'];
        yield 'second entry' => [['access_control' => [[], ['allow_if' => 3]]], 'access_control[1].allow_if is a string.'];
    }

    /**
     * @param array<array-key, mixed> $map
     */
    #[DataProvider('invalidMaps')]
    public function testAnInvalidMapIsRefused(array $map, string $reason): void
    {
        $this->expectExceptionObject(InvalidSecurityMap::because($reason));

        SecurityMap::fromArray($map);
    }

    public function testTheMessageNamesTheMap(): void
    {
        try {
            SecurityMap::fromArray(['firewalls' => 'main']);
            self::fail('Refused.');
        } catch (InvalidSecurityMap $invalidSecurityMap) {
            self::assertSame('The security map is invalid: firewalls is a list.', $invalidSecurityMap->getMessage());
        }
    }

    public function testTheMapIsBounded(): void
    {
        $limit = SecurityMap::fromArray(['firewalls' => array_fill(0, 5_000, ['name' => 'f']), 'access_control' => array_fill(0, 5_000, [])]);
        self::assertCount(5_000, $limit->accessControl);

        $this->expectExceptionObject(InvalidSecurityMap::because('it holds more than 10000 firewalls and entries.'));

        SecurityMap::fromArray(['firewalls' => array_fill(0, 5_001, ['name' => 'f']), 'access_control' => array_fill(0, 5_000, [])]);
    }
}
