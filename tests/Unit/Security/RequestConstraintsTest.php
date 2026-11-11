<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Security;

use Phil\RouteAccessCheck\Exception\InvalidSecurityMap;
use Phil\RouteAccessCheck\Security\RequestConstraints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(RequestConstraints::class)]
#[CoversClass(InvalidSecurityMap::class)]
final class RequestConstraintsTest extends TestCase
{
    public function testEachPartIsMatchedAsSymfonyDoes(): void
    {
        $request = Request::create('http://admin.example.com/admin/users', 'POST');
        $request->attributes->set('_route', 'admin_users');

        self::assertTrue(new RequestConstraints()->matches($request));
        self::assertTrue(new RequestConstraints('^/admin')->matches($request));
        self::assertFalse(new RequestConstraints('^/api')->matches($request));
        self::assertTrue(new RequestConstraints(host: '^admin\.')->matches($request));
        self::assertFalse(new RequestConstraints(host: '^shop\.')->matches($request));
        self::assertTrue(new RequestConstraints(methods: ['GET'])->matches($request), 'The methods are matched apart.');
        self::assertTrue(new RequestConstraints(methods: ['GET', 'POST'])->allowsMethod('POST'));
        self::assertFalse(new RequestConstraints(methods: ['GET'])->allowsMethod('POST'));
        self::assertFalse(new RequestConstraints(methods: ['GET'])->allowsMethod('get'));
        self::assertTrue(new RequestConstraints()->allowsMethod('PROPFIND'));
        self::assertTrue(new RequestConstraints(attributes: ['_route' => '^admin_'])->matches($request));
        self::assertFalse(new RequestConstraints(attributes: ['_route' => '^shop_'])->matches($request));
        self::assertFalse(new RequestConstraints('^/admin', '^shop\.')->matches($request));
        self::assertFalse(new RequestConstraints('^/api', '^admin\.')->matches($request));
    }

    public function testAddressesPortsAndMatcherServicesAreConditional(): void
    {
        self::assertFalse(new RequestConstraints('^/', '^a', ['GET'], attributes: ['_route' => 'x'])->isConditional());
        self::assertTrue(new RequestConstraints(port: 8080)->isConditional());
        self::assertTrue(new RequestConstraints(ips: ['127.0.0.1'])->isConditional());
        self::assertTrue(new RequestConstraints(matcherService: 'app.matcher')->isConditional());
        self::assertTrue(new RequestConstraints(ips: ['10.0.0.0/8'])->matches(Request::create('/')), 'Only the route-dependent parts are matched.');
    }

    public function testItDescribesItself(): void
    {
        self::assertSame('every request', new RequestConstraints()->describe());
        self::assertSame(
            'path ^/admin, host ^admin\., port 8443, matcher app.matcher, methods GET|POST, ips 127.0.0.1|::1, route ^admin_, attribute _format json',
            new RequestConstraints('^/admin', '^admin\.', ['GET', 'POST'], 8443, ['127.0.0.1', '::1'], ['_route' => '^admin_', '_format' => 'json'], 'app.matcher')->describe(),
        );
    }

    public function testAnInvalidPathIsRefused(): void
    {
        $this->expectExceptionObject(InvalidSecurityMap::because('the path "^/(admin" is not a valid regular expression.'));

        new RequestConstraints('^/(admin');
    }

    public function testAnInvalidHostIsRefused(): void
    {
        $this->expectExceptionObject(InvalidSecurityMap::because('the host "[" is not a valid regular expression.'));

        new RequestConstraints(host: '[');
    }

    public function testAnInvalidAttributeIsRefused(): void
    {
        $this->expectExceptionObject(InvalidSecurityMap::because('the attributes "(" is not a valid regular expression.'));

        new RequestConstraints(attributes: ['_route' => '(']);
    }
}
