<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteStatus;
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\Report\UnusedRule;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

#[CoversClass(Report::class)]
#[CoversClass(RouteVerdict::class)]
#[CoversClass(RouteStatus::class)]
#[CoversClass(UnusedRule::class)]
final class ReportTest extends TestCase
{
    public function testFailuresAreTheUnbaselinedUncoveredAndConflictingRoutes(): void
    {
        $uncovered = new RouteVerdict(Routes::read('u', '/u'), RouteStatus::Uncovered, uncoveredMethods: ['GET', 'HEAD']);
        $conflict = new RouteVerdict(Routes::read('c', '/c'), RouteStatus::Conflict, conflicts: ['one', 'two']);
        $baselined = new RouteVerdict(Routes::read('b', '/b'), RouteStatus::Uncovered, baselined: true);
        $report = new Report([
            $uncovered,
            new RouteVerdict(Routes::read('p', '/p'), RouteStatus::Protected),
            $conflict,
            $baselined,
            new RouteVerdict(Routes::read('i', '/i'), RouteStatus::Ignored),
        ]);

        self::assertSame([$uncovered, $conflict], $report->failures());
        self::assertTrue($report->hasFailures());
        self::assertFalse($report->hasWarnings());
        self::assertSame(['protected' => 1, 'public' => 0, 'mixed' => 0, 'uncovered' => 2, 'conflict' => 1, 'ignored' => 1], $report->counts());
        self::assertSame(1, $report->baselinedCount());
        self::assertSame('no access rule for GET, HEAD', $uncovered->problem());
        self::assertSame('one; two', $conflict->problem());
        self::assertSame('', $report->verdicts[1]->problem());
        self::assertFalse(new Report([$baselined])->hasFailures());
        self::assertSame(['protected' => 0, 'public' => 0, 'mixed' => 0, 'uncovered' => 0, 'conflict' => 0, 'ignored' => 0], new Report([])->counts());
    }

    public function testWarningsAreUnusedRulesAndStaleEntries(): void
    {
        self::assertTrue(new Report([], [new UnusedRule('a', 'b')])->hasWarnings());
        self::assertTrue(new Report([], [], ['gone'])->hasWarnings());
    }

    public function testAnInexactSampleIsAWarningUnlessTheRouteIsIgnored(): void
    {
        $route = new AnalysedRoute('c', new Route('/c/{code}'), ['GET'], null, '/c/1', null, false);
        $inexact = new RouteVerdict($route, RouteStatus::Protected);

        self::assertSame([$inexact], new Report([new RouteVerdict(Routes::read('p', '/p'), RouteStatus::Protected), $inexact])->inexactSamples());
        self::assertTrue(new Report([$inexact])->hasWarnings());
        self::assertFalse(new Report([new RouteVerdict($route, RouteStatus::Ignored)])->hasWarnings());
    }

    public function testAVerdictKeepsEverythingWhenBaselined(): void
    {
        $verdict = new RouteVerdict(Routes::read('u', '/u'), RouteStatus::Conflict, [], ['GET'], ['x']);
        $baselined = $verdict->withBaselined(true);

        self::assertTrue($verdict->fails());
        self::assertFalse($baselined->fails());
        self::assertTrue($baselined->baselined);
        self::assertSame(['GET'], $baselined->uncoveredMethods);
        self::assertSame(['x'], $baselined->conflicts);
        self::assertSame($verdict->route, $baselined->route);
        self::assertSame(RouteStatus::Conflict, $baselined->status);
        self::assertFalse($baselined->withBaselined(false)->baselined);
    }

    public function testOnlyUncoveredAndConflictFail(): void
    {
        foreach (RouteStatus::cases() as $status) {
            self::assertSame(\in_array($status, [RouteStatus::Uncovered, RouteStatus::Conflict], true), $status->isFailure(), $status->value);
        }
    }
}
