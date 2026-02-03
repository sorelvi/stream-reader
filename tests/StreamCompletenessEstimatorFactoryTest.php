<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests;

use PHPUnit\Framework\TestCase;
use Sorelvi\StreamReader\Enum\Preset;
use Sorelvi\StreamReader\Estimator\FixedByte;
use Sorelvi\StreamReader\Estimator\Utf16;
use Sorelvi\StreamReader\Estimator\Utf8;
use Sorelvi\StreamReader\HandleContext;
use Sorelvi\StreamReader\StreamCompletenessEstimatorFactory;

class StreamCompletenessEstimatorFactoryTest extends TestCase
{
    /**
     * @dataProvider provideCreateFixedByteData
     */
    public function testCreateFixedByte(Preset $preset, int $expectedLength): void
    {
        $item = StreamCompletenessEstimatorFactory::create($preset);
        $this->assertInstanceOf(FixedByte::class, $item);
        $this->assertEquals($item->getMaxAddReadBytes() + 1, $expectedLength);
    }

    /**
     * @return iterable<mixed>
     */
    public static function provideCreateFixedByteData(): iterable
    {
        yield [Preset::byte1, 1];
        yield [Preset::byte2, 2];
        yield [Preset::byte3, 3];
        yield [Preset::byte4, 4];
        yield [Preset::byte5, 5];
        yield [Preset::byte6, 6];
        yield [Preset::byte7, 7];
        yield [Preset::byte8, 8];
        yield [Preset::byte9, 9];
        yield [Preset::byte10, 10];
    }


    public function testCreateCharsets(): void
    {
        $utf8 = StreamCompletenessEstimatorFactory::create(Preset::UTF8);
        $this->assertInstanceOf(Utf8::class, $utf8);

        $utf16 = StreamCompletenessEstimatorFactory::create(Preset::UTF16);
        $this->assertInstanceOf(Utf16::class, $utf16);
        $utf16ContextLE = new HandleContext();
        $utf16->handle("\xFF\xFE", $utf16ContextLE);
        $this->assertTrue($utf16ContextLE->getParam(get_class($utf16), 'is_le'));
        $utf16ContextBE = new HandleContext();
        $utf16->handle("\xFE\xFF", $utf16ContextBE);
        $this->assertFalse($utf16ContextBE->getParam(get_class($utf16), 'is_le'));

        $utf16LE = StreamCompletenessEstimatorFactory::create(Preset::UTF16LE);
        $this->assertInstanceOf(Utf16::class, $utf16LE);
        $utf16LEContextLE = new HandleContext();
        $utf16LE->handle("\xFF\xFE", $utf16LEContextLE);
        $this->assertTrue($utf16LEContextLE->getParam(get_class($utf16), 'is_le'));
        $utf16LEContextBE = new HandleContext();
        $utf16LE->handle("\xFE\xFF", $utf16LEContextBE);
        $this->assertTrue($utf16LEContextBE->getParam(get_class($utf16), 'is_le'));

        $utf16BE = StreamCompletenessEstimatorFactory::create(Preset::UTF16BE);
        $this->assertInstanceOf(Utf16::class, $utf16BE);
        $utf16BEContextLE = new HandleContext();
        $utf16BE->handle("\xFF\xFE", $utf16BEContextLE);
        $this->assertFalse($utf16BEContextLE->getParam(get_class($utf16), 'is_le'));
        $utf16BEContextBE = new HandleContext();
        $utf16BE->handle("\xFE\xFF", $utf16BEContextBE);
        $this->assertFalse($utf16BEContextBE->getParam(get_class($utf16), 'is_le'));

        $utf32 = StreamCompletenessEstimatorFactory::create(Preset::UTF32);
        $this->assertInstanceOf(FixedByte::class, $utf32);
        $this->assertEquals($utf32->getMaxAddReadBytes() + 1, 4);
    }
}
