<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use JsonException;
use Phil\RouteAccessCheck\Baseline;
use Phil\RouteAccessCheck\Exception\InvalidBaseline;
use Phil\RouteAccessCheck\Report\Report;
use Phil\RouteAccessCheck\Report\RouteStatus;
use Phil\RouteAccessCheck\Report\RouteVerdict;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Baseline::class)]
#[CoversClass(InvalidBaseline::class)]
final class BaselineTest extends TestCase
{
    public function testItIsWrittenSortedAndReadBack(): void
    {
        $report = new Report([
            new RouteVerdict(Routes::read('zeta', '/z'), RouteStatus::Uncovered),
            new RouteVerdict(Routes::read('alpha/é', '/a'), RouteStatus::Conflict, baselined: true),
            new RouteVerdict(Routes::read('ok', '/ok'), RouteStatus::Protected),
        ]);
        $json = Baseline::fromReport($report)->toJson();

        self::assertSame("{\n    \"version\": 1,\n    \"routes\": {\n        \"alpha/é\": \"conflict\",\n        \"zeta\": \"uncovered\"\n    }\n}\n", $json);
        self::assertEquals(new Baseline(['zeta' => RouteStatus::Uncovered, 'alpha/é' => RouteStatus::Conflict]), Baseline::fromJson($json));
        self::assertSame("{\n    \"version\": 1,\n    \"routes\": {}\n}\n", new Baseline()->toJson());
        self::assertSame([10 => RouteStatus::Uncovered], Baseline::fromJson('{"version":1,"routes":{"10":"uncovered"}}')->entries);
        self::assertTrue(Baseline::fromJson('{"version":1,"routes":{"10":"uncovered"}}')->tolerates(new RouteVerdict(Routes::read('10', '/ten'), RouteStatus::Uncovered)));
    }

    public function testItToleratesTheSameFailure(): void
    {
        $baseline = new Baseline(['a' => RouteStatus::Uncovered]);

        self::assertTrue($baseline->tolerates(new RouteVerdict(Routes::read('a', '/a'), RouteStatus::Uncovered)));
        self::assertFalse($baseline->tolerates(new RouteVerdict(Routes::read('a', '/a'), RouteStatus::Conflict)));
        self::assertFalse($baseline->tolerates(new RouteVerdict(Routes::read('b', '/b'), RouteStatus::Uncovered)));
        self::assertFalse(new Baseline()->tolerates(new RouteVerdict(Routes::read('a', '/a'), RouteStatus::Protected)));
    }

    public function testItToleratesFailuresOnly(): void
    {
        $this->expectExceptionObject(InvalidBaseline::because('it tolerates failures only, not "public".'));

        new Baseline(['a' => RouteStatus::Uncovered, 'b' => RouteStatus::Public]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'not json' => ['{', 'it is not JSON.'];
        yield 'too deep' => ['{"version":1,"routes":{"a":{"b":1}}}', 'it is not JSON.'];
        yield 'a list' => ['[1]', 'it is an object with "version": 1 and "routes".'];
        yield 'a string' => ['"x"', 'it is an object with "version": 1 and "routes".'];
        yield 'another version' => ['{"version":2,"routes":{}}', 'it is an object with "version": 1 and "routes".'];
        yield 'a string version' => ['{"version":"1","routes":{}}', 'it is an object with "version": 1 and "routes".'];
        yield 'no routes' => ['{"version":1}', 'it is an object with "version": 1 and "routes".'];
        yield 'routes not an object' => ['{"version":1,"routes":"a"}', 'it is an object with "version": 1 and "routes".'];
        yield 'routes a list' => ['{"version":1,"routes":["uncovered"]}', 'it is an object with "version": 1 and "routes".'];
        yield 'unknown status' => ['{"version":1,"routes":{"a":"broken"}}', '"routes" holds the status of each route, by name.'];
        yield 'status not a string' => ['{"version":1,"routes":{"a":1}}', '"routes" holds the status of each route, by name.'];
        yield 'not a failure' => ['{"version":1,"routes":{"a":"ignored"}}', 'it tolerates failures only, not "ignored".'];
    }

    #[DataProvider('invalidDocuments')]
    public function testAnInvalidDocumentIsRefused(string $json, string $reason): void
    {
        $this->expectExceptionObject(InvalidBaseline::because($reason));

        Baseline::fromJson($json);
    }

    public function testTheJsonErrorIsKept(): void
    {
        try {
            Baseline::fromJson('{');
            self::fail('Refused.');
        } catch (InvalidBaseline $invalidBaseline) {
            self::assertSame('The baseline is invalid: it is not JSON.', $invalidBaseline->getMessage());
            self::assertSame(0, $invalidBaseline->getCode());
            self::assertInstanceOf(JsonException::class, $invalidBaseline->getPrevious());
        }
    }

    public function testItIsBounded(): void
    {
        $padding = str_repeat(' ', Baseline::MAX_BYTES - 26);
        self::assertEquals(new Baseline(), Baseline::fromJson('{"version":1,"routes":{}}'.$padding.' '));

        $this->expectExceptionObject(InvalidBaseline::because('it is larger than 5000000 bytes.'));

        Baseline::fromJson('{"version":1,"routes":{}}'.$padding.'  ');
    }
}
