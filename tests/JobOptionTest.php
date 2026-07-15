<?php

declare(strict_types=1);

namespace Alight\Tests;

use Alight\Job;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class JobOptionTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $this->resetJobState();
    }

    protected function tearDown(): void
    {
        $this->resetJobState();
        date_default_timezone_set($this->timezone);
    }

    #[DataProvider('scheduleProvider')]
    public function testScheduleRule(string $method, array $args, string $expectedRule): void
    {
        $option = Job::call('handler', ['argument']);
        $option->{$method}(...$args);

        self::assertSame(
            [
                1 => [
                    'handler' => 'handler',
                    'args' => ['argument'],
                    'rule' => $expectedRule,
                ],
            ],
            Job::$config
        );
    }

    public static function scheduleProvider(): iterable
    {
        yield 'minutely' => ['minutely', [], '*'];
        yield 'hourly' => ['hourly', [5], '05'];
        yield 'daily' => ['daily', [9, 30], '09:30'];
        yield 'weekly' => ['weekly', [1, 9, 30], '1 09:30'];
        yield 'monthly' => ['monthly', [15, 9, 30], '15 09:30'];
        yield 'yearly' => ['yearly', [7, 15, 9, 30], '07-15 09:30'];
        yield 'every minutes' => ['everyMinutes', [5], '*/5'];
        yield 'every hours' => ['everyHours', [2, 15], '*/02:15'];
        yield 'date' => ['date', ['2026-07-15 09:30'], '2026-07-15 09:30'];
    }

    public function testDefaultRuleAndTimeLimit(): void
    {
        Job::call('handler')->timeLimit(7200);

        self::assertSame('*', Job::$config[1]['rule']);
        self::assertSame(7200, Job::$config[1]['timeLimit']);
    }

    private function resetJobState(): void
    {
        Job::$config = [];
        (new ReflectionProperty(Job::class, 'index'))->setValue(null, 0);
    }
}
