<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader;

use Sorelvi\StreamReader\Enum\Preset;
use Sorelvi\StreamReader\Enum\Utf16Endianness;
use Sorelvi\StreamReader\Estimator\FixedByte;
use Sorelvi\StreamReader\Estimator\Utf16;
use Sorelvi\StreamReader\Estimator\Utf8;

final class StreamCompletenessEstimatorFactory
{
    public static function create(Preset $preset): EstimatorInterface
    {
        return match ($preset) {
            Preset::BYTE1 => new FixedByte(1),
            Preset::BYTE2 => new FixedByte(2),
            Preset::BYTE3 => new FixedByte(3),
            Preset::BYTE4, Preset::UTF32 => new FixedByte(4),
            Preset::BYTE5 => new FixedByte(5),
            Preset::BYTE6 => new FixedByte(6),
            Preset::BYTE7 => new FixedByte(7),
            Preset::BYTE8 => new FixedByte(8),
            Preset::BYTE9 => new FixedByte(9),
            Preset::BYTE10 => new FixedByte(10),
            Preset::UTF8 => new Utf8(),
            Preset::UTF16 => new Utf16(Utf16Endianness::AUTO),
            Preset::UTF16LE => new Utf16(Utf16Endianness::LE),
            Preset::UTF16BE => new Utf16(Utf16Endianness::BE)
        };
    }
}
