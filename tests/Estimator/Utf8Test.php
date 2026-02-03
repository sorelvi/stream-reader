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
use Sorelvi\StreamReader\Estimator\Utf8;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\StreamDamaged;
use Sorelvi\StreamReader\HandleContext;
use Throwable;

class Utf8Test extends TestCase
{
    public function testMaxAddReadBytes(): void
    {
        $estimator = new Utf8();
        $this->assertEquals(3, $estimator->getMaxAddReadBytes());
    }

    /**
     * @dataProvider provideBufferData
     */
    public function testHandleDeterminesMissingBytes(string $buffer, int $expectedNeed): void
    {
        $estimator = new Utf8();
        $context = new HandleContext();

        $result = $estimator->handle($buffer, $context);

        $this->assertSame($expectedNeed, $result);
    }

    /**
     * @return iterable<array<int, int|string>>
     */
    public static function provideBufferData(): iterable
    {
        yield 'empty string' => ['', 0];
        yield 'garbage ending with ascii' => ["\x80\x80\x80\x80\x80\x80\x7F", 0];
        yield 'full length 2-byte v210' => ["\xC2\xBF", 0];
        yield 'full length 2-byte v211' => ["\xC2", 1];
        yield 'full length 2-byte v220' => ["\xDF\x80", 0];
        yield 'full length 2-byte v221' => ["\xDF", 1];
        yield 'full length 3-byte v310' => ["\xE0\xBF\x80", 0];
        yield 'full length 3-byte v311' => ["\xE0\xBF", 1];
        yield 'full length 3-byte v312' => ["\xE0", 2];
        yield 'full length 3-byte v320' => ["\xEF\x80\xBF", 0];
        yield 'full length 3-byte v321' => ["\xEF\x80", 1];
        yield 'full length 3-byte v322' => ["\xEF", 2];
        yield 'full length 4-byte v410' => ["\xF0\x80\xBF\x80", 0];
        yield 'full length 4-byte v411' => ["\xF0\x80\xBF", 1];
        yield 'full length 4-byte v412' => ["\xF0\x80", 2];
        yield 'full length 4-byte v413' => ["\xF0", 3];
        yield 'full length 4-byte v420' => ["\xF4\x80\xBF\x80", 0];
        yield 'full length 4-byte v421' => ["\xF4\x80\xBF", 1];
        yield 'full length 4-byte v422' => ["\xF4\x80", 2];
        yield 'full length 4-byte v423' => ["\xF4", 3];
    }


    /**
     * @param class-string<Throwable> $class
     * @dataProvider provideExceptionTestsData
     */
    public function testExceptionTests(string $buffer, string $class, ?ErrorCode $errorCode): void
    {
        $estimator = new Utf8();
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
        yield 'LONG_SEQUENCE_OF_CONTINUATION_1' => [
            "\xF4\x80\xBF\x80\x80",
            StreamDamaged::class,
            ErrorCode::LONG_SEQUENCE_OF_CONTINUATION,
        ];

        yield 'LONG_SEQUENCE_OF_CONTINUATION_2' => [
            "\x80\xBF\x80\xBF",
            StreamDamaged::class,
            ErrorCode::LONG_SEQUENCE_OF_CONTINUATION,
        ];

        yield 'ORPHAN_CONTINUATION_BYTE_1' => [
            "\xEF\x80\xBF\x80",
            StreamDamaged::class,
            ErrorCode::ORPHAN_CONTINUATION_BYTE,
        ];

        yield 'ORPHAN_CONTINUATION_BYTE_2' => [
            "\x7F\x80\xBF\x80",
            StreamDamaged::class,
            ErrorCode::ORPHAN_CONTINUATION_BYTE,
        ];

        yield 'INVALID_START_BYTE_DETECTED_1' => [
            "\xF5",
            StreamDamaged::class,
            ErrorCode::INVALID_START_BYTE_DETECTED,
        ];

        yield 'INVALID_START_BYTE_DETECTED_2' => [
            "\xC0",
            StreamDamaged::class,
            ErrorCode::INVALID_START_BYTE_DETECTED,
        ];

        yield 'INVALID_START_BYTE_DETECTED_3' => [
            "\xC1",
            StreamDamaged::class,
            ErrorCode::INVALID_START_BYTE_DETECTED,
        ];
    }
}
