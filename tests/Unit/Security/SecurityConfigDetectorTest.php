<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Security;

use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\RouteReader;
use Phil\RouteAccessCheck\RuleScope;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

#[CoversClass(SecurityConfigDetector::class)]
final class SecurityConfigDetectorTest extends TestCase
{
    private const array OTHER_METHODS = ['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', '*'];

    public function testAnEmptyMapSaysNothing(): void
    {
        $detector = new SecurityConfigDetector(new SecurityMap());

        self::assertSame([], [...$detector->detect(Routes::read('a', '/a'))]);
        self::assertSame([], $detector->declaredRules());
    }

    public function testAFirewallWithoutSecurityLeavesTheRouteUnsecured(): void
    {
        self::assertEquals(
            [$this->rule('firewall', AccessEffect::Unsecured, 'firewall "dev" has security: false')],
            $this->detect(Routes::read('_profiler', '/_profiler/{token}')),
        );
    }

    public function testTheFirstMatchingEntryWins(): void
    {
        self::assertEquals(
            [$this->rule('access_control', AccessEffect::Public, 'access_control #1 (path ^/login): PUBLIC_ACCESS', null, 'access_control#1')],
            $this->detect(Routes::read('login', '/login')),
        );
        self::assertEquals(
            [
                $this->rule('access_control', AccessEffect::Conditional, 'access_control #2 (path ^/admin, ips 10.0.0.0/8): PUBLIC_ACCESS, for some requests only', null, 'access_control#2'),
                $this->rule('access_control', AccessEffect::Protected, 'access_control #3 (path ^/admin): ROLE_ADMIN', null, 'access_control#3'),
            ],
            $this->detect(Routes::read('admin', '/admin/{id}')),
        );
    }

    public function testEntriesAreTriedPerMethod(): void
    {
        self::assertEquals(
            [
                $this->rule('access_control', AccessEffect::Unchecked, 'no access_control entry matches behind firewall "main"', self::OTHER_METHODS),
                $this->rule('access_control', AccessEffect::Protected, 'access_control #4 (path ^/api, methods POST): ROLE_API', ['POST'], 'access_control#4'),
            ],
            $this->detect(Routes::read('api', '/api/items')),
        );
        self::assertEquals(
            [$this->rule('access_control', AccessEffect::Protected, 'access_control #4 (path ^/api, methods POST): ROLE_API', null, 'access_control#4')],
            $this->detect(Routes::read('api_post', '/api/items', methods: ['POST'])),
        );
    }

    public function testAnEntryWithoutRolesStopsTheSearch(): void
    {
        self::assertEquals(
            [$this->rule('access_control', AccessEffect::Unchecked, 'access_control #5 (path ^/legacy): no roles, nothing is checked', null, 'access_control#5')],
            $this->detect(Routes::read('legacy', '/legacy/never')),
        );
    }

    public function testAHostRestrictedEntryCoversOnlyARouteOfThatHost(): void
    {
        self::assertEquals(
            [$this->rule('access_control', AccessEffect::Protected, 'access_control #6 (host ^admin\.): ROLE_STAFF', null, 'access_control#6')],
            $this->detect(Routes::read('staff', '/staff', host: 'admin.example.com')),
        );
        self::assertSame(AccessEffect::Unchecked, $this->detect(Routes::read('staff', '/staff'))[0]->effect);
    }

    public function testTheRouteNameAndDefaultsAreAttributes(): void
    {
        self::assertSame('access_control#7', $this->detect(Routes::read('export', '/export'))[0]->ruleId);
        self::assertSame(AccessEffect::Unchecked, $this->detect(Routes::read('export_all', '/export/all'))[0]->effect);

        $feed = new RouteReader()->read('feed', new Route('/feed', ['_format' => 'json', 'page' => 1]));
        self::assertSame('access_control#8', $this->detect($feed)[0]->ruleId);
    }

    public function testEveryEntryIsDeclared(): void
    {
        $declared = $this->detector()->declaredRules();

        self::assertCount(9, $declared);
        self::assertSame('access_control #1 (path ^/login): PUBLIC_ACCESS', $declared['access_control#1']);
        self::assertSame('access_control #9 (path ^/never): ROLE_N', $declared['access_control#9']);
    }

    public function testARequestOutsideEveryFirewallIsUnchecked(): void
    {
        $detector = new SecurityConfigDetector(SecurityMap::fromArray([
            'firewalls' => [
                ['name' => 'internal', 'request' => ['matcher' => 'app.internal_matcher']],
                ['name' => 'secure', 'request' => ['path' => '^/secure']],
            ],
            'access_control' => [['roles' => 'ROLE_USER']],
        ]));

        self::assertEquals(
            [
                $this->rule('firewall', AccessEffect::Conditional, 'firewall "internal" (matcher app.internal_matcher) applies to some requests only'),
                $this->rule('firewall', AccessEffect::Unchecked, 'no firewall matches: access_control does not apply'),
            ],
            [...$detector->detect(Routes::read('other', '/other'))],
        );
        self::assertEquals(
            [
                $this->rule('firewall', AccessEffect::Conditional, 'firewall "internal" (matcher app.internal_matcher) applies to some requests only'),
                $this->rule('access_control', AccessEffect::Protected, 'access_control #1 (every request): ROLE_USER', null, 'access_control#1'),
            ],
            [...$detector->detect(Routes::read('secure', '/secure'))],
        );
    }

    public function testAFirewallRestrictedToSomeMethods(): void
    {
        $detector = new SecurityConfigDetector(SecurityMap::fromArray([
            'firewalls' => [
                ['name' => 'office', 'request' => ['ips' => ['10.0.0.0/8']], 'security' => false],
                ['name' => 'webhooks', 'request' => ['methods' => ['POST']], 'security' => false],
                ['name' => 'main'],
            ],
            'access_control' => [
                ['request' => ['path' => '^/hook', 'port' => 8080], 'roles' => 'PUBLIC_ACCESS'],
                ['request' => ['path' => '^/other'], 'roles' => 'ROLE_USER'],
            ],
        ]));

        self::assertEquals(
            [
                self::rule('firewall', AccessEffect::Conditional, 'firewall "office" (ips 10.0.0.0/8) applies to some requests only'),
                self::rule('access_control', AccessEffect::Conditional, 'access_control #1 (path ^/hook, port 8080): PUBLIC_ACCESS, for some requests only', self::OTHER_METHODS, 'access_control#1'),
                self::rule('access_control', AccessEffect::Unchecked, 'no access_control entry matches behind firewall "main"', self::OTHER_METHODS),
                self::rule('firewall', AccessEffect::Unsecured, 'firewall "webhooks" has security: false', ['POST']),
            ],
            [...$detector->detect(Routes::read('hook', '/hook'))],
        );
    }

    public function testEveryMethodOfARestrictedFirewallIsNamed(): void
    {
        $detector = new SecurityConfigDetector(SecurityMap::fromArray([
            'firewalls' => [['name' => 'api', 'request' => ['methods' => ['POST', 'PUT']]]],
            'access_control' => [['roles' => 'ROLE_API']],
        ]));

        self::assertEquals(
            [$this->rule('access_control', AccessEffect::Protected, 'access_control #1 (every request): ROLE_API', null, 'access_control#1')],
            [...$detector->detect(Routes::read('put', '/items', methods: ['PUT']))],
        );
    }

    public function testAMapWithoutFirewallsTriesItsEntries(): void
    {
        $detector = new SecurityConfigDetector(SecurityMap::fromArray(['access_control' => [['request' => ['path' => '^/admin'], 'roles' => 'ROLE_ADMIN']]]));

        self::assertSame(AccessEffect::Protected, [...$detector->detect(Routes::read('admin', '/admin'))][0]->effect);
        self::assertEquals(
            [$this->rule('access_control', AccessEffect::Unchecked, 'no access_control entry matches')],
            [...$detector->detect(Routes::read('home', '/'))],
        );
    }

    public function testTheUnlistedMethodStandsForTheOthers(): void
    {
        $detector = new SecurityConfigDetector(SecurityMap::fromArray(['access_control' => [
            ['request' => ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']], 'roles' => 'ROLE_USER'],
        ]]));

        $rules = [...$detector->detect(Routes::read('any', '/any'))];

        self::assertSame(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $rules[0]->methods);
        self::assertSame(['*'], $rules[1]->methods);
        self::assertSame(AccessEffect::Unchecked, $rules[1]->effect);
        self::assertSame('UNLISTED', SecurityConfigDetector::UNLISTED_METHOD);
        self::assertSame('route-access-check.invalid', SecurityConfigDetector::UNNAMED_HOST);
    }

    public function testTheSampleThatGuardsAMethodLeastDecides(): void
    {
        $detector = new SecurityConfigDetector(SecurityMap::fromArray(['access_control' => [
            ['request' => ['path' => '^/en/'], 'roles' => 'ROLE_ADMIN'],
            ['request' => ['path' => '^/de/'], 'roles' => 'PUBLIC_ACCESS'],
            ['request' => ['path' => '^/fr/', 'methods' => ['POST']], 'roles' => 'ROLE_EDITOR'],
            ['request' => ['path' => '^/it/', 'ips' => ['10.0.0.0/8']], 'roles' => 'ROLE_IT'],
        ]]));
        $read = static fn (string $requirement): AnalysedRoute => new RouteReader()->read('page', new Route('/{_locale}/page', [], ['_locale' => $requirement], methods: ['GET', 'POST']));

        self::assertEquals(
            [
                $this->rule('access_control', AccessEffect::Unchecked, 'no access_control entry matches, on /fr/page', ['GET', 'HEAD']),
                $this->rule('access_control', AccessEffect::Conditional, 'access_control #1 (path ^/en/): ROLE_ADMIN, on /en/page only', ['GET', 'HEAD'], 'access_control#1'),
                $this->rule('access_control', AccessEffect::Protected, 'access_control #1 (path ^/en/): ROLE_ADMIN, on /en/page', ['POST'], 'access_control#1'),
                $this->rule('access_control', AccessEffect::Conditional, 'access_control #3 (path ^/fr/, methods POST): ROLE_EDITOR, on /fr/page only', ['POST'], 'access_control#3'),
            ],
            [...$detector->detect($read('en|fr'))],
        );
        self::assertEquals(
            [
                $this->rule('access_control', AccessEffect::Public, 'access_control #2 (path ^/de/): PUBLIC_ACCESS, on /de/page', null, 'access_control#2'),
                $this->rule('access_control', AccessEffect::Conditional, 'access_control #1 (path ^/en/): ROLE_ADMIN, on /en/page only', null, 'access_control#1'),
            ],
            [...$detector->detect($read('en|de'))],
        );
        self::assertEquals(
            [
                $this->rule('access_control', AccessEffect::Conditional, 'access_control #4 (path ^/it/, ips 10.0.0.0/8): ROLE_IT, for some requests only, on /it/page', null, 'access_control#4'),
                $this->rule('access_control', AccessEffect::Unchecked, 'no access_control entry matches, on /it/page'),
                $this->rule('access_control', AccessEffect::Conditional, 'access_control #2 (path ^/de/): PUBLIC_ACCESS, on /de/page only', null, 'access_control#2'),
            ],
            [...$detector->detect($read('de|it'))],
        );
        self::assertEquals(
            [$this->rule('access_control', AccessEffect::Protected, 'access_control #1 (path ^/en/): ROLE_ADMIN', null, 'access_control#1')],
            [...$detector->detect($read('en|en'))],
        );
    }

    public function testEachSampleCarriesItsHost(): void
    {
        $detector = new SecurityConfigDetector(SecurityMap::fromArray(['access_control' => [
            ['request' => ['host' => '^b\\.example\\.com$'], 'roles' => 'ROLE_B'],
            ['request' => ['host' => '^a\\.example\\.com$'], 'roles' => 'ROLE_A'],
        ]]));

        self::assertEquals(
            [
                $this->rule('access_control', AccessEffect::Protected, 'access_control #2 (host ^a\\.example\\.com$): ROLE_A, on //a.example.com/', null, 'access_control#2'),
                $this->rule('access_control', AccessEffect::Conditional, 'access_control #1 (host ^b\\.example\\.com$): ROLE_B, on //b.example.com/ only', null, 'access_control#1'),
            ],
            [...$detector->detect(new RouteReader()->read('home', new Route('/', [], ['s' => 'a|b'], host: '{s}.example.com', methods: ['GET'])))],
        );
    }

    /**
     * @return list<AccessRule>
     */
    private function detect(AnalysedRoute $route): array
    {
        return array_values([...$this->detector()->detect($route)]);
    }

    private function detector(): SecurityConfigDetector
    {
        return new SecurityConfigDetector(SecurityMap::fromArray([
            'firewalls' => [
                ['name' => 'dev', 'request' => ['path' => '^/(_profiler|_wdt)'], 'security' => false],
                ['name' => 'main'],
            ],
            'access_control' => [
                ['request' => ['path' => '^/login'], 'roles' => 'PUBLIC_ACCESS'],
                ['request' => ['path' => '^/admin', 'ips' => ['10.0.0.0/8']], 'roles' => 'PUBLIC_ACCESS'],
                ['request' => ['path' => '^/admin'], 'roles' => 'ROLE_ADMIN'],
                ['request' => ['path' => '^/api', 'methods' => ['POST']], 'roles' => 'ROLE_API'],
                ['request' => ['path' => '^/legacy']],
                ['request' => ['host' => '^admin\.'], 'roles' => 'ROLE_STAFF'],
                ['request' => ['attributes' => ['_route' => '^export$']], 'roles' => 'ROLE_EXPORT'],
                ['request' => ['attributes' => ['_format' => '^json$']], 'roles' => 'ROLE_FEED'],
                ['request' => ['path' => '^/never'], 'roles' => 'ROLE_N'],
            ],
        ]));
    }

    /**
     * @param list<string>|null $methods
     */
    private function rule(string $source, AccessEffect $effect, string $description, ?array $methods = null, ?string $ruleId = null): AccessRule
    {
        return new AccessRule($source, $effect, RuleScope::Configuration, $description, $methods, $ruleId);
    }
}
