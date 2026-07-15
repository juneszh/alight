<?php

declare(strict_types=1);

namespace Alight\Tests;

use Alight\Cache;
use Alight\CacheHelper;
use Alight\Config;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheHelperTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setConfig([
            'app' => ['cacheAdapter' => self::arrayAdapter(...)],
            'cache' => [
                'type' => 'array',
                'namespace' => 'test',
                'defaultLifetime' => 60,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->setConfig([]);
    }

    public function testExplicitCallableGeneratesStableKeys(): void
    {
        $keys = CacheHelper::key(['user', 7], [CacheHelperFixture::class, 'first']);

        self::assertCount(2, $keys);
        self::assertStringEndsWith('.first.user.7', $keys[0]);
        self::assertStringEndsWith('.first', $keys[1]);
    }

    public function testClearInvalidatesEveryMethodTag(): void
    {
        $cache = Cache::psr6();
        $firstTag = CacheHelper::key([], [CacheHelperFixture::class, 'first'])[0];
        $secondTag = CacheHelper::key([], [CacheHelperFixture::class, 'second'])[0];

        $first = $cache->getItem('first-result');
        $first->set('first')->tag($firstTag);
        $cache->save($first);

        $second = $cache->getItem('second-result');
        $second->set('second')->tag($secondTag);
        $cache->save($second);

        self::assertTrue(CacheHelper::clear([CacheHelperFixture::class]));
        self::assertFalse($cache->getItem('first-result')->isHit());
        self::assertFalse($cache->getItem('second-result')->isHit());
    }

    private static function arrayAdapter(array $config): ArrayAdapter
    {
        return new ArrayAdapter($config['defaultLifetime']);
    }

    private function setConfig(array $config): void
    {
        (new ReflectionProperty(Config::class, 'config'))->setValue(null, $config);
        Cache::$instance = [];
    }
}

final class CacheHelperFixture
{
    public static function first(): void
    {
    }

    public static function second(): void
    {
    }
}
