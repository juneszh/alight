<?php

declare(strict_types=1);

namespace Alight\Tests;

use Alight\Cache;
use Alight\Config;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class CacheTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::$instance = [];
        $this->setConfig([]);
    }

    public function testPsr16StoresValuesWithCustomArrayAdapter(): void
    {
        $this->setArrayCacheConfig();

        $cache = Cache::init();
        $cache->set('message', 'hello', 60);

        self::assertInstanceOf(Psr16Cache::class, $cache);
        self::assertSame('hello', $cache->get('message'));
        self::assertTrue($cache->has('message'));
    }

    public function testReturnsSameInstanceForSameInterfaceAndConfigKey(): void
    {
        $this->setArrayCacheConfig();

        self::assertSame(Cache::init(), Cache::init());
        self::assertSame(Cache::psr6(), Cache::psr6());
        self::assertNotSame(Cache::init(), Cache::psr6());
    }

    public function testSupportsDefaultAndNamedCacheConfigurations(): void
    {
        $this->setConfig([
            'app' => ['cacheAdapter' => self::arrayAdapter(...)],
            'cache' => [
                'primary' => ['type' => 'array', 'namespace' => 'primary'],
                'secondary' => ['type' => 'array', 'namespace' => 'secondary'],
            ],
        ]);

        Cache::init()->set('key', 'primary');
        Cache::init('secondary')->set('key', 'secondary');

        self::assertSame('primary', Cache::init()->get('key'));
        self::assertSame('secondary', Cache::init('secondary')->get('key'));
    }

    public function testPsr6AdapterSupportsTaggedItems(): void
    {
        $this->setArrayCacheConfig();

        $cache = Cache::psr6();
        $item = $cache->getItem('tagged');
        $item->set('value');
        $item->tag('group');
        $cache->save($item);

        self::assertTrue($cache->getItem('tagged')->isHit());
        self::assertTrue($cache->invalidateTags(['group']));
        self::assertFalse($cache->getItem('tagged')->isHit());
    }

    public function testEmptyCacheTypeUsesNullAdapter(): void
    {
        $this->setConfig(['cache' => ['type' => '']]);

        $cache = Cache::init();
        $cache->set('key', 'value');

        self::assertNull($cache->get('key'));
        self::assertFalse($cache->has('key'));
    }

    public function testRejectsMissingCacheConfiguration(): void
    {
        $this->setConfig(['cache' => []]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing cache configuration.');

        Cache::init();
    }

    public function testRejectsMissingNamedCacheConfiguration(): void
    {
        $this->setConfig([
            'app' => ['cacheAdapter' => self::arrayAdapter(...)],
            'cache' => [
                'primary' => ['type' => 'array'],
            ],
        ]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Missing cache configuration about 'missing'.");

        Cache::init('missing');
    }

    public function testRejectsUnknownTypeWithoutCustomAdapter(): void
    {
        $this->setConfig([
            'app' => ['cacheAdapter' => null],
            'cache' => ['type' => 'unknown'],
        ]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid cacheAdapter specified.');

        Cache::init();
    }

    private function setArrayCacheConfig(): void
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
