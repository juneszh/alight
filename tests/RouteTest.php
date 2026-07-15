<?php

declare(strict_types=1);

namespace Alight\Tests;

use Alight\Request;
use Alight\Response;
use Alight\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    protected function setUp(): void
    {
        Route::init();
    }

    protected function tearDown(): void
    {
        Route::init();
    }

    #[DataProvider('methodProvider')]
    public function testRegistersHttpMethod(string $method, array $expectedMethods): void
    {
        Route::{$method}('/users', 'handler', ['page' => 1]);

        self::assertSame($expectedMethods, Route::$config[1]['methods']);
        self::assertSame('/users', Route::$config[1]['pattern']);
        self::assertSame('handler', Route::$config[1]['handler']);
        self::assertSame(['page' => 1], Route::$config[1]['args']);
    }

    public static function methodProvider(): iterable
    {
        yield 'options' => ['options', ['OPTIONS']];
        yield 'head' => ['head', ['HEAD']];
        yield 'get' => ['get', ['GET']];
        yield 'post' => ['post', ['POST']];
        yield 'delete' => ['delete', ['DELETE']];
        yield 'put' => ['put', ['PUT']];
        yield 'patch' => ['patch', ['PATCH']];
    }

    public function testGroupAndMapNormalizePatterns(): void
    {
        Route::group('/api/');
        Route::map(['GET', 'POST'], '/users/', 'handler');

        self::assertSame(['GET', 'POST'], Route::$config[1]['methods']);
        self::assertSame('/api/users', Route::$config[1]['pattern']);
    }

    public function testAnyUsesDefaultOrConfiguredMethods(): void
    {
        Route::any('/default', 'handler');
        Route::setAnyMethods(['GET', 'POST']);
        Route::any('/custom', 'handler');

        self::assertSame(Request::ALLOW_METHODS, Route::$config[1]['methods']);
        self::assertSame(['GET', 'POST'], Route::$config[2]['methods']);
    }

    public function testGlobalHandlersAreCopiedIntoNewRoute(): void
    {
        Route::beforeHandler('before', ['before-arg']);
        Route::afterHandler('after', ['after-arg']);
        Route::authHandler('auth', ['auth-arg']);
        Route::get('/protected', 'handler');

        self::assertSame([['before', ['before-arg']]], Route::$config[1]['beforeGlobal']);
        self::assertSame([['after', ['after-arg']]], Route::$config[1]['afterGlobal']);
        self::assertSame(['auth', ['auth-arg']], Route::$config[1]['authHandler']);
    }

    public function testFluentUtilitiesAttachRouteHandlers(): void
    {
        Route::get('/article', 'handler')
            ->before('before', [1])
            ->after('after', [2])
            ->auth(3)
            ->cache(60, 30, ['public' => true])
            ->minify();

        self::assertSame(
            [
                ['before', [1]],
                [[Response::class, 'auth'], [3]],
                [[Response::class, 'cache'], [60, 30, ['public' => true]]],
            ],
            Route::$config[1]['before']
        );
        self::assertSame(
            [
                ['after', [2]],
                [[Response::class, 'minify'], []],
            ],
            Route::$config[1]['after']
        );
    }

    public function testCorsRegistersOptionsAndCurrentRouteHandlers(): void
    {
        Route::put('/profile', 'handler')->cors('*', ['Authorization'], ['PUT']);

        self::assertSame(['PUT'], Route::$config[1]['methods']);
        self::assertSame(
            [[[Response::class, 'cors'], ['*', ['Authorization'], ['PUT']]]],
            Route::$config[1]['before']
        );
        self::assertSame(['OPTIONS'], Route::$config[2]['methods']);
        self::assertSame('/profile', Route::$config[2]['pattern']);
        self::assertSame([Response::class, 'cors'], Route::$config[2]['handler']);
    }

    public function testInitResetsAllRouteState(): void
    {
        Route::group('api');
        Route::setAnyMethods(['GET']);
        Route::beforeHandler('before');
        Route::afterHandler('after');
        Route::authHandler('auth');
        Route::disableCache();
        Route::get('/users', 'handler');

        Route::init();
        Route::any('/status', 'handler');

        self::assertFalse(Route::$disableCache);
        self::assertSame(Request::ALLOW_METHODS, Route::$config[1]['methods']);
        self::assertSame('/status', Route::$config[1]['pattern']);
        self::assertArrayNotHasKey('beforeGlobal', Route::$config[1]);
        self::assertArrayNotHasKey('afterGlobal', Route::$config[1]);
        self::assertArrayNotHasKey('authHandler', Route::$config[1]);
    }
}
