<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests\Estimator;

use PHPUnit\Framework\TestCase;
use Sorelvi\StreamReader\Enum\Utf16Endianness;
use Sorelvi\StreamReader\Estimator\Utf16;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\StreamDamaged;
use Sorelvi\StreamReader\HandleContext;
use Throwable;

class Utf16Test extends TestCase
{
    public function testMaxAddReadBytes(): void
    {
        $estimator = new Utf16(Utf16Endianness::AUTO);
        $this->assertEquals(3, $estimator->getMaxAddReadBytes());
    }

    /**
     * @dataProvider providePositiveLeData
     */
    public function testPositiveLe(string $buffer, int $expectedNeed): void
    {
        $estimator = new Utf16(Utf16Endianness::LE);
        $context = new HandleContext();

        $result = $estimator->handle($buffer, $context);

        $this->assertSame($expectedNeed, $result);
    }

    /**
     * @return iterable<array<int, int|string>>
     */
    public static function providePositiveLeData(): iterable
    {
        yield 'empty string' => ['', 0];
        yield 'not even string' => ["\xD7", 1];
        yield 'simple word v1' => ["\xFF\xD7", 0];
        yield 'simple word v2' => ["\x00\x00", 0];
        yield 'high surrogate word v1' => ["\x00\xD8", 2];
        yield 'high surrogate word v2' => ["\xFF\xDB", 2];
        yield 'low surrogate word v1' => ["\x00\xD8\x00\xDC", 0];
        yield 'low surrogate word v2' => ["\xFF\xDB\xFF\xDF", 0];
    }

    /**
     * @dataProvider providePositiveBeData
     */
    public function testPositiveBe(string $buffer, int $expectedNeed): void
    {
        $estimator = new Utf16(Utf16Endianness::BE);
        $context = new HandleContext();

        $result = $estimator->handle($buffer, $context);

        $this->assertSame($expectedNeed, $result);
    }

    /**
     * @return iterable<array<int, int|string>>
     */
    public static function providePositiveBeData(): iterable
    {
        yield 'high surrogate word v1' => ["\xD8\x00", 2];
        yield 'high surrogate word v2' => ["\xDB\xFF", 2];
        yield 'low surrogate word v1' => ["\xD8\x00\xDC\x00", 0];
        yield 'low surrogate word v2' => ["\xDB\xFF\xDF\xFF", 0];
    }

    /**
     * @param class-string<Throwable> $class
     * @dataProvider provideExceptionTestsData
     */
    public function testExceptionTests(string $buffer, string $class, ?ErrorCode $errorCode): void
    {
        $estimator = new Utf16(Utf16Endianness::LE);
        $context = new HandleContext();

        $this->expectException($class);
        if ($errorCode) {
            $this->expectExceptionCode($errorCode->value);
        }

        $estimator->handle($buffer, $context);
    }

    /**
     * @return iterable<array<int, string|class-string<Throwable>|ErrorCode|null>>
     */
    public static function provideExceptionTestsData(): iterable
    {
        yield 'LOW_SURROGATE_AT_BEGINNING' => [
            "\x00\xDC",
            StreamDamaged::class,
            ErrorCode::LOW_SURROGATE_AT_BEGINNING,
        ];
        yield 'ORPHAN_LOW_SURROGATE V1' => [
            "\x00\x00\x00\xDC",
            StreamDamaged::class,
            ErrorCode::ORPHAN_LOW_SURROGATE,
        ];
        yield 'ORPHAN_LOW_SURROGATE V2' => [
            "\x00\x00\xFF\xDF",
            StreamDamaged::class,
            ErrorCode::ORPHAN_LOW_SURROGATE,
        ];
    }

    /**
     * @dataProvider provideDetectionBomData
     */
    public function testDetectionBom(
        string $buffer,
        int $expectLength,
        Utf16Endianness $expectEndianness,
    ): void {
        $context = new HandleContext();
        $estimator = new Utf16(Utf16Endianness::AUTO);
        $this->assertEquals($expectLength, $estimator->handle($buffer, $context));
        $this->assertTrue($context->hasParam(get_class($estimator), 'is_le'));

        if ($context->getParam(get_class($estimator), 'is_le')) {
            $this->assertEquals($expectEndianness, Utf16Endianness::LE);
            $this->assertEquals(2, $estimator->handle("\x00\xD8", $context));
        } else {
            $this->assertEquals($expectEndianness, Utf16Endianness::BE);
            $this->assertEquals(2, $estimator->handle("\xD8\x00", $context));
        }
    }

    /**
     * @return iterable<array<int, string|int|Utf16Endianness>>
     */
    public static function provideDetectionBomData(): iterable
    {
        yield 'Single LE BOM' => ["\xFF\xFE", 0, Utf16Endianness::LE];
        yield 'Single BE BOM v1' => ["\xFE\xFE", 0, Utf16Endianness::BE];
        yield 'Single BE BOM v2' => ["\xFE\xFF", 0, Utf16Endianness::BE];
        yield 'Single auto BE BOM' => ["\xD8\x00\xDC\x00", 0, Utf16Endianness::BE];
    }

    public function testChangeBomOnContext(): void
    {
        $context = new HandleContext();
        $estimator = new Utf16(Utf16Endianness::LE);
        $context->setParam(get_class($estimator), 'is_le', 1);
        $this->assertEquals(2, $estimator->handle("\x00\xD8", $context));
    }
}
