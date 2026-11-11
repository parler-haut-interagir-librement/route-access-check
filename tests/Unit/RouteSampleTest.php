<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit;

use Phil\RouteAccessCheck\RouteSample;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

#[CoversClass(RouteSample::class)]
final class RouteSampleTest extends TestCase
{
    public function testAStaticRouteIsItsOwnSample(): void
    {
        $sample = RouteSample::of(new Route('/admin/users'));

        self::assertSame('/admin/users', $sample->path);
        self::assertNull($sample->host);
        self::assertTrue($sample->exact);
    }

    public function testPlaceholdersTakeValuesTheirRequirementsAccept(): void
    {
        $route = new Route('/{_locale}/posts/{id}/{slug}/{date}/{uuid}', ['slug' => 'intro'], ['_locale' => 'fr|en', 'id' => '\d+', 'date' => '\d{4}-\d{2}-\d{2}', 'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}']);
        $sample = RouteSample::of($route);

        self::assertSame('/fr/posts/1/intro/2026-01-01/00000000-0000-4000-8000-000000000000', $sample->path);
        self::assertTrue($sample->exact);
    }

    public function testTheDefaultComesFirstWhenTheRequirementAcceptsIt(): void
    {
        self::assertSame('/page/7', RouteSample::of(new Route('/page/{n}', ['n' => 7], ['n' => '\d+']))->path);
        self::assertSame('/page/1', RouteSample::of(new Route('/page/{n}', ['n' => 'last'], ['n' => '\d+']))->path);
        self::assertSame('/page/1', RouteSample::of(new Route('/page/{n}', ['n' => ['array']], ['n' => '\d+']))->path);
        self::assertSame('/page/1', RouteSample::of(new Route('/page/{n}', ['n' => ''], ['n' => '\d*']))->path);
    }

    public function testCandidatesAreTriedInOrder(): void
    {
        self::assertSame('/a', RouteSample::of(new Route('/{x}', [], ['x' => '[a-z]']))->path);
        self::assertSame('/A', RouteSample::of(new Route('/{x}', [], ['x' => '[A-Z]']))->path);
        self::assertSame('/aa', RouteSample::of(new Route('/{x}', [], ['x' => '[a-z]{2}']))->path);
        self::assertSame('/AA', RouteSample::of(new Route('/{x}', [], ['x' => '[A-Z]{2}']))->path);
        self::assertSame('/a1', RouteSample::of(new Route('/{x}', [], ['x' => '[a-z]\d']))->path);
        self::assertSame('/a-1', RouteSample::of(new Route('/{x}', [], ['x' => '[a-z]-\d']))->path);
        self::assertSame('/x.y', RouteSample::of(new Route('/{x}', [], ['x' => '\w\.\w']))->path);
        self::assertSame('/en', RouteSample::of(new Route('/{x}', [], ['x' => '(?:en|de)-?']))->path);
        self::assertSame('/01J0000000000000000000000A', RouteSample::of(new Route('/{x}', [], ['x' => '[0-9A-Z]{26}']))->path);
        self::assertSame('/edit', RouteSample::of(new Route('/{x}', [], ['x' => 'edit|show']))->path);
        self::assertSame('/v1.2', RouteSample::of(new Route('/{x}', [], ['x' => 'v1.2']))->path);
        self::assertSame('/zz', RouteSample::of(new Route('/{x}', [], ['x' => 'zz|y_-.9']))->path);
        self::assertFalse(RouteSample::of(new Route('/{x}', [], ['x' => 'zz|y\d']))->exact, 'Only literal alternatives give their first.');
    }

    public function testEveryLiteralAndAnotherValueThanTheDefaultAreSampled(): void
    {
        $paths = static fn (Route $route): array => array_map(static fn (RouteSample $sample): string => ($sample->host ?? '').$sample->path, RouteSample::all($route));

        self::assertSame(['/en/admin', '/fr/admin'], $paths(new Route('/{_locale}/admin', [], ['_locale' => 'en|fr'])));
        self::assertSame(['/fr/admin', '/en/admin'], $paths(new Route('/{_locale}/admin', ['_locale' => 'fr'], ['_locale' => 'en|fr|fr'])));
        self::assertSame(['/en/admin', '/aa/admin'], $paths(new Route('/{_locale}/admin', ['_locale' => 'en'], ['_locale' => '[a-z]{2}'])));
        self::assertSame(['/page/7', '/page/1'], $paths(new Route('/page/{n}', ['n' => 7], ['n' => '\d+'])));
        self::assertSame(['/page/1', '/page/2'], $paths(new Route('/page/{n}', ['n' => 1], ['n' => '\d+'])));
        self::assertSame(['/page/1'], $paths(new Route('/page/{n}', [], ['n' => '\d+'])));
        self::assertSame(['/en/admin', '/fr/admin'], $paths(new Route('/{_locale}/admin', ['_locale' => 'de'], ['_locale' => 'en|fr'])), 'A default the requirement refuses is no sample.');
        self::assertSame(['/en/admin'], $paths(new Route('/{x}/admin', [], ['x' => 'en|'])), 'An empty literal is no sample.');
        self::assertSame(['/a'], $paths(new Route('/{x}', ['x' => ['b']], ['x' => '[A-Za-z]+'])), 'A default that is no string nor integer is left out.');
        self::assertSame(['en.example.com/x/a', 'en.example.com/x/b', 'fr.example.com/x/a', 'fr.example.com/x/b'], $paths(new Route('/x/{y}', [], ['y' => 'a|b', 'site' => 'en|fr'], host: '{site}.example.com')));

        foreach (RouteSample::all(new Route('/{_locale}/admin', [], ['_locale' => 'en|fr'])) as $sample) {
            self::assertTrue($sample->exact);
        }
    }

    public function testTooManyVariantsOrADefaultAloneMakeTheSampleInexact(): void
    {
        $sixteen = implode('|', array_map(static fn (int $n): string => 'v'.$n, range(1, 17)));
        $wide = RouteSample::all(new Route('/{a}', [], ['a' => $sixteen]));

        self::assertCount(16, $wide);
        self::assertFalse($wide[15]->exact);
        self::assertTrue(RouteSample::of(new Route('/{a}', [], ['a' => implode('|', array_map(static fn (int $n): string => 'v'.$n, range(1, 16)))]))->exact);

        $product = RouteSample::all(new Route('/{a}/{b}', [], ['a' => 'a|b|c|d', 'b' => 'a|b|c|d|e']));

        self::assertCount(16, $product);
        self::assertSame('/a/a', $product[0]->path);
        self::assertFalse($product[0]->exact);
        self::assertTrue(RouteSample::of(new Route('/{a}/{b}', [], ['a' => 'a|b|c|d', 'b' => 'a|b|c|d']))->exact);

        $year = RouteSample::all(new Route('/{year}', ['year' => '2026'], ['year' => '\d{4}']));

        self::assertCount(1, $year);
        self::assertFalse($year[0]->exact, 'Only the default satisfies the requirement: it decided the match.');
    }

    public function testAnUnsatisfiedRequirementMakesTheSampleInexact(): void
    {
        $sample = RouteSample::of(new Route('/code/{code}', [], ['code' => '[A-Z]{3}\d{3}']));

        self::assertSame('/code/1', $sample->path);
        self::assertFalse($sample->exact);
    }

    public function testTheUtf8OptionReadsTheRequirementAsUnicode(): void
    {
        self::assertSame('/a', RouteSample::of(new Route('/{x}', [], ['x' => '\x{1F600}|a'], ['utf8' => true]))->path);
        self::assertSame('/é', RouteSample::of(new Route('/{x}', ['x' => 'é'], ['x' => '..']))->path, 'Without the option, a requirement reads bytes.');
        self::assertSame('/a', RouteSample::of(new Route('/{x}', [], ['x' => '\p{L}'], ['utf8' => true]))->path);
        self::assertTrue(RouteSample::of(new Route('/{x}', [], ['x' => '.'], ['utf8' => true]))->exact);
    }

    public function testTheHostIsSampledToo(): void
    {
        $sample = RouteSample::of(new Route('/', requirements: ['tenant' => '[a-z]+'], host: '{tenant}.example.com'));

        self::assertSame('a.example.com', $sample->host);
        self::assertSame('/', $sample->path);
        self::assertSame('shop.example.com', RouteSample::of(new Route('/', host: 'shop.example.com'))->host);
    }

    public function testARouteThatDoesNotCompileGetsARoughSample(): void
    {
        $sample = RouteSample::of(new Route('/{a}/{a}', host: '{h}.example.com'));

        self::assertSame('/1/1', $sample->path);
        self::assertSame('a.example.com', $sample->host);
        self::assertFalse($sample->exact);
        self::assertNull(RouteSample::of(new Route('/{a}/{a}'))->host);
    }
}
