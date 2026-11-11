<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Format;

use Phil\RouteAccessCheck\AnalysedRoute;
use Phil\RouteAccessCheck\Controller;
use Phil\RouteAccessCheck\Format\Describe;
use Phil\RouteAccessCheck\Format\GitHubFormatter;
use Phil\RouteAccessCheck\Format\JsonFormatter;
use Phil\RouteAccessCheck\Format\JunitFormatter;
use Phil\RouteAccessCheck\Format\TextFormatter;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteStatus;
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\Report\UnusedRule;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\AccountController;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\Routes;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\SampleReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(TextFormatter::class)]
#[CoversClass(JsonFormatter::class)]
#[CoversClass(GitHubFormatter::class)]
#[CoversClass(JunitFormatter::class)]
#[CoversClass(Describe::class)]
final class FormattersTest extends TestCase
{
    public function testTheText(): void
    {
        $text = new TextFormatter();

        self::assertSame('text', $text->name());
        self::assertSame(<<<'TEXT'
            PROTECTED admin  GET /admin
                - protected: access_control #1 (path ^/admin): ROLE_ADMIN
            UNCOVERED export  GET|POST //shop.example.com/export/{code}
                ! no access rule for GET, HEAD
                - protected: #[IsGranted('ROLE_USER')] on App\Export for POST [POST]
            UNCOVERED legacy  ANY /legacy  (baselined)
                ! no access rule for *
            IGNORED   _wdt  ANY /_wdt
            CONFLICT  closure  ANY /c
                ! declared public, but
            protected: 100%

            Warnings:
              - access_control #2 (path ^/old): ROLE_OLD reaches no route: no route matches it, or an earlier rule settles every route it matches.
              - The baseline entry of route "gone" matches no failing route: remove it.

            5 routes: 1 protected, 0 public, 0 mixed, 2 uncovered, 1 conflict, 1 ignored; 1 baselined.

            TEXT, $text->format(SampleReport::build()));
    }

    public function testTheTextOfProblemsOnly(): void
    {
        self::assertSame(<<<'TEXT'
            UNCOVERED export  GET|POST //shop.example.com/export/{code}
                ! no access rule for GET, HEAD
                - protected: #[IsGranted('ROLE_USER')] on App\Export for POST [POST]
            CONFLICT  closure  ANY /c
                ! declared public, but
            protected: 100%

            Warnings:
              - access_control #2 (path ^/old): ROLE_OLD reaches no route: no route matches it, or an earlier rule settles every route it matches.
              - The baseline entry of route "gone" matches no failing route: remove it.

            5 routes: 1 protected, 0 public, 0 mixed, 2 uncovered, 1 conflict, 1 ignored; 1 baselined.

            TEXT, new TextFormatter()->format(SampleReport::build(), true));

        $code = Routes::read('code', '/code/{c}');
        $inexactRoute = new AnalysedRoute('code', $code->route, ['GET'], null, '/code/1', null, false);
        self::assertSame(<<<'TEXT'
            UNCOVERED code  ANY /code/{c}
                ! no access rule for GET
                ~ sample path /code/1: inexact, a rule may decide on a placeholder value no sample carries

            Warnings:
              - The sample path /code/1 of route "code" is inexact: a rule may decide on a placeholder value no sample carries.

            1 routes: 0 protected, 0 public, 0 mixed, 1 uncovered, 0 conflict, 0 ignored; 0 baselined.

            TEXT, new TextFormatter()->format(new Report([new RouteVerdict($inexactRoute, RouteStatus::Uncovered, [], ['GET'])])));
    }

    public function testTheJson(): void
    {
        $json = new JsonFormatter();
        /** @var array{passed: bool, baselined: int, counts: array<string, int>, unusedRules: list<array<string, string>>, staleBaselineEntries: list<string>, routes: list<array<string, mixed>>} $document */
        $document = json_decode($json->format(SampleReport::build()), true, 16, \JSON_THROW_ON_ERROR);

        self::assertSame('json', $json->name());
        self::assertFalse($document['passed']);
        self::assertSame(1, $document['baselined']);
        self::assertSame(2, $document['counts']['uncovered']);
        self::assertSame([['id' => 'access_control#2', 'description' => 'access_control #2 (path ^/old): ROLE_OLD']], $document['unusedRules']);
        self::assertSame(['gone'], $document['staleBaselineEntries']);
        self::assertCount(5, $document['routes']);
        self::assertSame([
            'name' => 'export',
            'path' => '/export/{code}',
            'host' => 'shop.example.com',
            'methods' => ['GET', 'POST'],
            'controller' => AccountController::class.'::open',
            'status' => 'uncovered',
            'baselined' => false,
            'uncoveredMethods' => ['GET', 'HEAD'],
            'conflicts' => [],
            'rules' => [[
                'source' => 'attribute',
                'effect' => 'protected',
                'scope' => 'route',
                'description' => "#[IsGranted('ROLE_USER')] on App\\Export for POST",
                'methods' => ['POST'],
                'ruleId' => null,
            ]],
            'samplePath' => '/export/1',
            'exactSample' => true,
        ], $document['routes'][1]);
        self::assertNull($document['routes'][2]['controller']);
        self::assertStringContainsString("\n    \"passed\": false,\n", $json->format(SampleReport::build()));
        self::assertStringContainsString('"path": "/admin"', $json->format(SampleReport::build()));
        self::assertStringEndsWith("}\n", $json->format(SampleReport::build()));

        /** @var array{routes: list<array<string, mixed>>} $problems */
        $problems = json_decode($json->format(SampleReport::build(), true), true, 16, \JSON_THROW_ON_ERROR);
        self::assertSame(['export', 'closure'], array_column($problems['routes'], 'name'));
        self::assertStringContainsString('"description": "ü"', $json->format(new Report([], [new UnusedRule('x', 'ü')])));
        /** @var array{passed: bool} $passing */
        $passing = json_decode($json->format(new Report([])), true, 16, \JSON_THROW_ON_ERROR);
        self::assertTrue($passing['passed']);
    }

    public function testTheGitHubAnnotations(): void
    {
        $file = new ReflectionMethod(AccountController::class, 'open');
        $dir = \dirname((string) $file->getFileName(), 2);
        $github = new GitHubFormatter($dir.'/');

        self::assertSame('github', $github->name());
        self::assertSame(
            '::error file=Fixture/AccountController.php,line='.$file->getStartLine().',title=Route access::Route "export" (GET|POST //shop.example.com/export/{code}): no access rule for GET, HEAD.'."\n"
            .'::error title=Route access::Route "closure" (ANY /c): declared public, but%0Aprotected: 100%25.'."\n"
            .'::warning title=Route access::access_control #2 (path ^/old): ROLE_OLD reaches no route: no route matches it, or an earlier rule settles every route it matches.'."\n"
            .'::warning title=Route access::The baseline entry of route "gone" matches no failing route: remove it.'."\n"
            .'5 routes: 1 protected, 0 public, 0 mixed, 2 uncovered, 1 conflict, 1 ignored; 1 baselined.'."\n",
            $github->format(SampleReport::build()),
        );

        self::assertStringContainsString('::error file='.str_replace(':', '%3A', (string) $file->getFileName()).',line=', new GitHubFormatter()->format(SampleReport::build()));
        self::assertStringContainsString('::error file='.str_replace(':', '%3A', (string) $file->getFileName()).',line=', new GitHubFormatter('/elsewhere')->format(SampleReport::build()));
        self::assertStringContainsString('::error file=Fixture/AccountController.php,', new GitHubFormatter($dir)->format(SampleReport::build()));
    }

    public function testGitHubPropertiesAreEscaped(): void
    {
        $route = Routes::read('r', '/r', AccountController::class.'::open');
        $controller = new Controller(AccountController::class, 'open', "/app/a,b:c%\r\n.php", null);
        $analysed = new AnalysedRoute('r', $route->route, ['GET'], $controller, '/r', null);

        self::assertStringStartsWith('::error file=/app/a%2Cb%3Ac%25%0D%0A.php,title=Route access::Route "r" (ANY /r): no access rule for GET.', new GitHubFormatter()->format(new Report([new RouteVerdict($analysed, RouteStatus::Uncovered, [], ['GET'])])));
        self::assertStringContainsString("::error title=Route access::Route \"r\" (ANY /r): x%0D.\n", new GitHubFormatter()->format(new Report([new RouteVerdict(Routes::read('r', '/r'), RouteStatus::Conflict, [], [], ["x\r"])])));
    }

    public function testTheJunitDocument(): void
    {
        $junit = new JunitFormatter();

        self::assertSame('junit', $junit->name());
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="route-access" tests="5" failures="2" skipped="2">
                <testcase name="admin GET /admin" classname="route-access"></testcase>
                <testcase name="export GET|POST //shop.example.com/export/{code}" classname="route-access"><failure type="uncovered" message="no access rule for GET, HEAD"/></testcase>
                <testcase name="legacy ANY /legacy" classname="route-access"><skipped message="baselined: no access rule for *"/></testcase>
                <testcase name="_wdt ANY /_wdt" classname="route-access"><skipped message="ignored"/></testcase>
                <testcase name="closure ANY /c" classname="route-access"><failure type="conflict" message="declared public, but
            protected: 100%"/></testcase>
                <system-out>access_control #2 (path ^/old): ROLE_OLD reaches no route: no route matches it, or an earlier rule settles every route it matches.
            The baseline entry of route &quot;gone&quot; matches no failing route: remove it.</system-out>
              </testsuite>
            </testsuites>

            XML, $junit->format(SampleReport::build()));

        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <testsuites>
              <testsuite name="route-access" tests="0" failures="0" skipped="0">

              </testsuite>
            </testsuites>

            XML, $junit->format(new Report([])));

        self::assertStringContainsString('tests="2" failures="2" skipped="0"', $junit->format(SampleReport::build(), true));
        self::assertStringContainsString('<testcase name="a&amp;b&lt;c&gt; ANY /x" classname="route-access"><failure type="conflict" message="&apos;&quot;"/>', $junit->format(new Report([new RouteVerdict(Routes::read('a&b<c>', '/x'), RouteStatus::Conflict, [], [], ['\'"'])])));
        self::assertStringContainsString("message=\"\u{FFFD}\"", $junit->format(new Report([new RouteVerdict(Routes::read('a', '/x'), RouteStatus::Conflict, [], [], ["\xFF"])])));
    }
}
