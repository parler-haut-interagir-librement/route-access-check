# Testing

## The library's tests

The suite `tests/Unit` tests the reading of routes — method labels, controllers in every form, attributes read by
name, sample paths and hosts for each kind of requirement, inexact samples —; each detector — `#[IsGranted]` on class
and action, its methods positional or named, expressions, closures and arguments that cannot be read,
`#[PublicRoute]` and the route option, the firewalls and `access_control` entries per method label, conditional
entries and firewalls, entries without roles, host-restricted entries, route attributes —; the security map and its
validation; the statuses, conflicts and unused rules of the checker; the baseline and its limits; and each format to
the character.

From the monorepo root:

```sh
vendor/bin/phpunit packages/route-access-check/tests
```

The library is held to 100 % covered mutation score by Infection.

## The fuzz target

`tests/Fuzz/baseline.php` gives the baseline reader text nobody wrote: whatever it reads, it returns a baseline whose
own document reads back to the same entries, or refuses it with `InvalidBaseline`, and throws nothing else.
`composer fuzz baseline`, from the monorepo root, runs it from the seeds of `tests/Fuzz/corpus/baseline/`.

## The benchmark

`tests/Benchmark/RouteAccessCheckBench.php` checks 2,000 routes — static and with placeholders, hosts, methods,
controllers with attributes and without — against two firewalls and twenty-four `access_control` entries, and writes
the JSON report of the result. `composer bench`, from the monorepo root, runs it with the other benchmarks. On the
development machine, without OPcache, a check of the 2,000 routes takes about 0.14 s and its JSON report 9 ms.

## Testing an application's routes

Run the check as a test of the application, so that a route without a rule fails the suite as well as the CI step:

```php
use Phil\RouteAccessCheck\RouteAccessChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RouteAccessTest extends KernelTestCase
{
    public function testEveryRouteHasAnAccessRule(): void
    {
        $container = self::getContainer();
        $report = $container->get(RouteAccessChecker::class)->check($container->get('router')->getRouteCollection());

        self::assertSame([], array_map(static fn ($verdict): string => $verdict->route->name.': '.$verdict->problem(), $report->failures()));
    }
}
```

With `phil/route-access-check-bundle`, `RouteAccessChecker` is a service of the `test` environment.
