<?php

declare(strict_types=1);

namespace Alight\Tests;

use Alight\Utility;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UtilityTest extends TestCase
{
    public function testRandomHexReturnsRequestedLength(): void
    {
        $value = Utility::randomHex(32);

        self::assertSame(32, strlen($value));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $value);
    }

    public function testRandomHexRejectsOddLength(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('length must be even.');

        Utility::randomHex(31);
    }

    public function testUniqueNumberReturnsNumericValueWithRequestedLength(): void
    {
        $value = Utility::uniqueNumber(20);

        self::assertSame(20, strlen($value));
        self::assertMatchesRegularExpression('/^\d{20}$/', $value);
    }

    public function testUniqueNumberRejectsLengthBelowSixteen(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Length must be greater than 15.');

        Utility::uniqueNumber(15);
    }

    #[DataProvider('jsonProvider')]
    public function testJsonDetection(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Utility::isJson($value));
    }

    public static function jsonProvider(): iterable
    {
        yield 'object' => ['{"name":"alight"}', true];
        yield 'array' => ['[1,2,3]', true];
        yield 'empty array' => ['[]', true];
        yield 'empty object' => ['{}', true];
        yield 'malformed json' => ['{"name":}', false];
        yield 'scalar json' => ['123', false];
        yield 'empty string' => ['', false];
        yield 'non-string value' => [[], false];
    }

    public function testArrayFilterSupportsComparisonAndReindexesRows(): void
    {
        $rows = [
            ['id' => 1, 'status' => 0],
            ['id' => 2, 'status' => 1],
            ['id' => 3, 'status' => 1],
        ];

        self::assertSame(
            [
                ['id' => 2, 'status' => 1],
                ['id' => 3, 'status' => 1],
            ],
            Utility::arrayFilter($rows, ['id[>]' => 1, 'status' => 1])
        );
    }

    public function testArrayFilterCanBuildEnumMap(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Administrator'],
            ['id' => 2, 'name' => 'Editor'],
        ];

        self::assertSame(
            [1 => 'Administrator', 2 => 'Editor'],
            Utility::arrayFilter($rows, enumKey: 'id', enumValue: 'name')
        );
    }

    public function testNumberPadAddsLeadingZeroes(): void
    {
        self::assertSame('07', Utility::numberPad(7));
        self::assertSame('0007', Utility::numberPad(7, 4));
    }
}
