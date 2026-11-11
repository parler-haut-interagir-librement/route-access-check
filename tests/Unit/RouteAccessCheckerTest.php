<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use ArrayIterator;
use Phil\RouteAccessCheck\AccessEffect;
use Phil\RouteAccessCheck\AccessRule;
use Phil\RouteAccessCheck\Baseline;
use Phil\RouteAccessCheck\Detector\PublicRouteDetector;
use Phil\RouteAccessCheck\Detector\SecurityAttributeDetector;
use Phil\RouteAccessCheck\Detector\SecurityConfigDetector;
use Phil\RouteAccessCheck\IgnoreList;
use Phil\RouteAccessCheck\Report\RouteStatus;
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\Report\UnusedRule;
use Phil\RouteAccessCheck\RouteAccessChecker;
use Phil\RouteAccessCheck\RouteReader;
use Phil\RouteAccessCheck\RuleScope;
use Phil\RouteAccessCheck\Security\SecurityMap;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AccountController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AdminController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\StubDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(RouteAccessChecker::class)]
final class RouteAccessCheckerTest extends TestCase
{
    public function testEachMethodNeedsACoveringRule(): void
    {
        $report = new RouteAccessChecker([new StubDetector([
            'protected' => [$this->rule(AccessEffect::Protected)],
            'public' => [$this->rule(AccessEffect::Public, RuleScope::Configuration)],
            'unsecured' => [$this->rule(AccessEffect::Unsecured, RuleScope::Configuration)],
            'mixed' => [$this->rule(AccessEffect::Protected, methods: ['POST']), $this->rule(AccessEffect::Public, methods: ['GET', 'HEAD'])],
            'partial' => [$this->rule(AccessEffect::Protected, methods: ['POST']), $this->rule(AccessEffect::Unchecked), $this->rule(AccessEffect::Conditional)],
        ])])->check($this->routes([
            'protected' => new Route('/p'),
            'public' => new Route('/pub', methods: ['GET']),
            'unsecured' => new Route('/u'),
            'mixed' => new Route('/m', methods: ['GET', 'POST']),
            'partial' => new Route('/x', methods: ['GET', 'POST']),
            'none' => new Route('/n', methods: ['DELETE']),
        ]));

        self::assertSame(
            ['protected' => RouteStatus::Protected, 'public' => RouteStatus::Public, 'unsecured' => RouteStatus::Public, 'mixed' => RouteStatus::Mixed, 'partial' => RouteStatus::Uncovered, 'none' => RouteStatus::Uncovered],
            $this->statuses($report->verdicts),
        );
        self::assertSame(['GET', 'HEAD'], $report->verdicts[4]->uncoveredMethods);
        self::assertSame(['DELETE'], $report->verdicts[5]->uncoveredMethods);
        self::assertSame([], $report->verdicts[0]->uncoveredMethods);
        self::assertCount(3, $report->verdicts[4]->rules);
        self::assertSame([], $report->verdicts[0]->conflicts);
    }

    public function testEveryLiteralOfAnAlternationMustBeCovered(): void
    {
        $checker = new RouteAccessChecker([new SecurityConfigDetector(SecurityMap::fromArray([
            'firewalls' => [['name' => 'main']],
            'access_control' => [
                ['request' => ['path' => '^/en/admin'], 'roles' => 'ROLE_ADMIN'],
                ['request' => ['path' => '^/admin', 'host' => '^en\\.example\\.com$'], 'roles' => 'ROLE_ADMIN'],
            ],
        ]))]);
        $routes = $this->routes([
            'users' => new Route('/{_locale}/admin/users', requirements: ['_locale' => 'en|fr'], methods: ['GET']),
            'tenant' => new Route('/admin', requirements: ['site' => 'en|fr'], host: '{site}.example.com', methods: ['GET']),
            'paged' => new Route('/{_locale}/admin/list', ['_locale' => 'en'], ['_locale' => '[a-z]{2}'], methods: ['GET']),
        ]);

        $report = $checker->check($routes);

        self::assertSame(['users' => RouteStatus::Uncovered, 'tenant' => RouteStatus::Uncovered, 'paged' => RouteStatus::Uncovered], $this->statuses($report->verdicts));
        self::assertSame([], $report->unusedRules, 'The entry that covers one literal is still used.');
    }

    public function testAPublicDeclarationAndACheckOnTheSameMethodConflict(): void
    {
        $declared = $this->rule(AccessEffect::Public, description: '#[PublicRoute]');
        $configPublic = $this->rule(AccessEffect::Public, RuleScope::Configuration, 'access_control PUBLIC_ACCESS');
        $check = $this->rule(AccessEffect::Protected, RuleScope::Configuration, 'access_control ROLE_USER');
        $postCheck = $this->rule(AccessEffect::Protected, description: 'IsGranted for POST', methods: ['POST']);
        $unsecured = $this->rule(AccessEffect::Unsecured, RuleScope::Configuration, 'firewall "dev"');

        $report = new RouteAccessChecker([new StubDetector([
            'declared' => [$declared, $check],
            'config_public' => [$configPublic, $check],
            'split' => [$declared, $postCheck],
            'unsecured' => [$unsecured, $postCheck],
            'unsecured_public' => [$unsecured, $declared],
        ])])->check($this->routes([
            'declared' => new Route('/d', methods: ['GET']),
            'config_public' => new Route('/c', methods: ['GET']),
            'split' => new Route('/s', methods: ['POST']),
            'unsecured' => new Route('/u', methods: ['GET', 'POST']),
            'unsecured_public' => new Route('/up', methods: ['GET']),
        ]));

        self::assertSame(
            ['declared' => RouteStatus::Conflict, 'config_public' => RouteStatus::Protected, 'split' => RouteStatus::Conflict, 'unsecured' => RouteStatus::Conflict, 'unsecured_public' => RouteStatus::Public],
            $this->statuses($report->verdicts),
        );
        self::assertSame(['declared public by #[PublicRoute], but protected by access_control ROLE_USER'], $report->verdicts[0]->conflicts);
        self::assertSame(['protected by IsGranted for POST behind firewall "dev", where the check always denies'], $report->verdicts[3]->conflicts);
        self::assertSame([], $report->verdicts[1]->conflicts);
    }

    public function testEveryConflictIsListed(): void
    {
        $report = new RouteAccessChecker([new StubDetector(['r' => [
            self::rule(AccessEffect::Public, description: 'declared'),
            self::rule(AccessEffect::Protected, description: 'first'),
            self::rule(AccessEffect::Protected, description: 'second'),
        ]], ['a' => 'unused a', 'b' => 'unused b'])])->check(self::routes(['r' => new Route('/r', methods: ['POST'])]), new Baseline(['10' => RouteStatus::Uncovered]));

        self::assertSame(['declared public by declared, but protected by first', 'declared public by declared, but protected by second'], $report->verdicts[0]->conflicts);
        self::assertEquals([new UnusedRule('a', 'unused a'), new UnusedRule('b', 'unused b')], $report->unusedRules);
        self::assertSame(['10'], $report->staleBaselineEntries);
    }

    public function testAConflictOutranksAnUncoveredMethod(): void
    {
        $report = new RouteAccessChecker([new StubDetector(['r' => [
            $this->rule(AccessEffect::Public, description: 'declared', methods: ['POST']),
            $this->rule(AccessEffect::Protected, description: 'check', methods: ['POST']),
        ]])])->check($this->routes(['r' => new Route('/r', methods: ['GET', 'POST'])]));

        self::assertSame(RouteStatus::Conflict, $report->verdicts[0]->status);
        self::assertSame(['GET', 'HEAD'], $report->verdicts[0]->uncoveredMethods);
    }

    public function testIgnoredRoutesStillUseTheirRules(): void
    {
        $used = new AccessRule('access_control', AccessEffect::Public, RuleScope::Configuration, 'profiler', null, 'access_control#1');
        $report = new RouteAccessChecker(
            new ArrayIterator([new StubDetector(['_profiler' => [$used]], ['access_control#1' => 'profiler', 'access_control#2' => 'never'])]),
            new IgnoreList(['_profiler'], []),
        )->check($this->routes(['_profiler' => new Route('/_profiler'), 'home' => new Route('/')]));

        self::assertSame(RouteStatus::Ignored, $report->verdicts[0]->status);
        self::assertSame([$used], $report->verdicts[0]->rules);
        self::assertSame(RouteStatus::Uncovered, $report->verdicts[1]->status);
        self::assertEquals([new UnusedRule('access_control#2', 'never')], $report->unusedRules);
    }

    public function testRulesFromDetectorsWithoutInventoryAreNotUnused(): void
    {
        $report = new RouteAccessChecker([new SecurityAttributeDetector(), new StubDetector([], ['x' => 'declared'])])
            ->check($this->routes(['a' => new Route('/a', ['_controller' => AdminController::class.'::dashboard'])]));

        self::assertEquals([new UnusedRule('x', 'declared')], $report->unusedRules);
        self::assertSame(RouteStatus::Protected, $report->verdicts[0]->status);
    }

    public function testTheBaselineToleratesTheSameFailureOnly(): void
    {
        $routes = $this->routes(['open' => new Route('/open'), 'conflict' => new Route('/c', ['_controller' => AccountController::class.'::profile'], methods: ['POST']), 'fixed' => new Route('/f', ['_controller' => AdminController::class.'::dashboard'])]);
        $checker = new RouteAccessChecker([new SecurityAttributeDetector(), new PublicRouteDetector()]);
        $baseline = new Baseline(['open' => RouteStatus::Uncovered, 'conflict' => RouteStatus::Uncovered, 'fixed' => RouteStatus::Uncovered, 'gone' => RouteStatus::Conflict]);

        $report = $checker->check($routes, $baseline);

        self::assertTrue($report->verdicts[0]->baselined);
        self::assertFalse($report->verdicts[1]->baselined);
        self::assertSame(RouteStatus::Conflict, $report->verdicts[1]->status);
        self::assertFalse($report->verdicts[2]->baselined);
        self::assertSame(['conflict', 'fixed', 'gone'], $report->staleBaselineEntries);
        self::assertSame([], $checker->check($routes)->staleBaselineEntries);
    }

    public function testTheDefaultsIgnoreTheFrameworkAndReadControllers(): void
    {
        $checker = new RouteAccessChecker([new PublicRouteDetector()], reader: new RouteReader());
        $report = $checker->check($this->routes(['_wdt' => new Route('/_wdt/{token}'), 'landing' => new Route('/', ['_controller' => AccountController::class.'::signIn'])]));

        self::assertSame(['_wdt' => RouteStatus::Ignored, 'landing' => RouteStatus::Public], $this->statuses($report->verdicts));
    }

    /**
     * @param array<string, Route> $routes
     */
    private function routes(array $routes): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        return $collection;
    }

    /**
     * @param list<RouteVerdict> $verdicts
     *
     * @return array<string, RouteStatus>
     */
    private function statuses(array $verdicts): array
    {
        $statuses = [];

        foreach ($verdicts as $verdict) {
            $statuses[$verdict->route->name] = $verdict->status;
        }

        return $statuses;
    }

    /**
     * @param list<string>|null $methods
     */
    private function rule(AccessEffect $effect, RuleScope $scope = RuleScope::Route, string $description = 'rule', ?array $methods = null): AccessRule
    {
        return new AccessRule('stub', $effect, $scope, $description, $methods);
    }
}
