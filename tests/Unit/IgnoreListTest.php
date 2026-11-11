<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use Phil\RouteAccessCheck\Exception\InvalidConfiguration;
use Phil\RouteAccessCheck\IgnoreList;
use Phil\RouteAccessCheck\Tests\Unit\Fixture\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IgnoreList::class)]
#[CoversClass(InvalidConfiguration::class)]
final class IgnoreListTest extends TestCase
{
    public function testTheFrameworkRoutesAreIgnoredByDefault(): void
    {
        $ignore = new IgnoreList();

        self::assertTrue($ignore->ignores(Routes::read('_profiler_home', '/somewhere')));
        self::assertTrue($ignore->ignores(Routes::read('_wdt', '/x')));
        self::assertTrue($ignore->ignores(Routes::read('_preview_error', '/x')));
        self::assertTrue($ignore->ignores(Routes::read('anything', '/_error/{code}')));
        self::assertTrue($ignore->ignores(Routes::read('anything', '/_profiler/open')));
        self::assertFalse($ignore->ignores(Routes::read('home', '/')));
        self::assertFalse($ignore->ignores(Routes::read('my_profiler', '/my/_profiler')));
    }

    public function testTheApplicationNamesItsOwnPrefixes(): void
    {
        $ignore = new IgnoreList(['health_'], ['/.well-known/', '/healthz']);

        self::assertTrue($ignore->ignores(Routes::read('health_db', '/x')));
        self::assertTrue($ignore->ignores(Routes::read('x', '/.well-known/security.txt')));
        self::assertTrue($ignore->ignores(Routes::read('x', '/healthz')));
        self::assertFalse($ignore->ignores(Routes::read('_wdt', '/_wdt')));
    }

    public function testAnEmptyPathPrefixIsRefused(): void
    {
        $this->expectExceptionObject(InvalidConfiguration::because('An ignored prefix is not empty: it would leave every route aside.'));

        new IgnoreList([], ['/a', '']);
    }

    public function testAnEmptyNamePrefixIsRefused(): void
    {
        $this->expectExceptionObject(InvalidConfiguration::because('An ignored prefix is not empty: it would leave every route aside.'));

        new IgnoreList(['a', ''], ['/a']);
    }
}
