<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Estimator;

use Sorelvi\StreamReader\Enum\Utf16Endianness;
use Sorelvi\StreamReader\EstimatorInterface;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\StreamDamaged;
use Sorelvi\StreamReader\HandleContext;

final class Utf16 implements EstimatorInterface
{
    private const CONTEXT_KEY_IS_LE = 'is_le';

    public function __construct(private readonly Utf16Endianness $endianness)
    {
    }

    public function handle(string $buffer, HandleContext $context): int
    {
        $len = strlen($buffer);
        if ($len === 0) {
            return 0;
        }

        if ($len % 2 !== 0) {
            return 1;
        }

        $isLe = $this->getIsLe($buffer, $context);

        $byte1 = ord($buffer[$len - 2]);
        $byte2 = ord($buffer[$len - 1]);
        $lastWord = $isLe ? $byte1 | ($byte2 << 8) : ($byte1 << 8) | $byte2;

        if ($this->isHighSurrogate($lastWord)) {
            return 2;
        }

        if ($this->isLowSurrogate($lastWord)) {
            if ($len < 4) {
                throw new StreamDamaged(
                    ErrorCode::LOW_SURROGATE_AT_BEGINNING,
                    "Low surrogate at the beginning of the chunk"
                );
            }

            $hByte1 = ord($buffer[$len - 4]);
            $hByte2 = ord($buffer[$len - 3]);
            $wordHs = $isLe ? $hByte1 | ($hByte2 << 8) : ($hByte1 << 8) | $hByte2;

            if (!$this->isHighSurrogate($wordHs)) {
                throw new StreamDamaged(
                    ErrorCode::ORPHAN_LOW_SURROGATE,
                    "Orphaned low surrogate"
                );
            }
        }

        return 0;
    }

    /**
     * Maximum total bytes that might need to be read to complete the stream:
     * - 1 byte to make length even
     * - 2 bytes to complete a surrogate pair
     * Total: 3 bytes
     */
    public function getMaxAddReadBytes(): int
    {
        return 3;
    }

    private function isHighSurrogate(int $word): bool
    {
        return $word >= 0xD800 && $word <= 0xDBFF;
    }

    private function isLowSurrogate(int $word): bool
    {
        return $word >= 0xDC00 && $word <= 0xDFFF;
    }

    private function getIsLe(string $buffer, HandleContext $context): bool
    {
        if ($context->hasParam(__CLASS__, self::CONTEXT_KEY_IS_LE)) {
            return (bool) $context->getParam(__CLASS__, self::CONTEXT_KEY_IS_LE);
        }

        $isLe = $this->detectEndianness($buffer);
        $context->setParam(__CLASS__, self::CONTEXT_KEY_IS_LE, $isLe);

        return $isLe;
    }

    private function detectEndianness(string $buffer): bool
    {
        if ($this->endianness === Utf16Endianness::LE) {
            return true;
        }
        if ($this->endianness === Utf16Endianness::BE) {
            return false;
        }

        if (strlen($buffer) >= 2) {
            $first = ord($buffer[0]);
            $second = ord($buffer[1]);

            if ($first === 0xFF && $second === 0xFE) {
                return true; // LE
            }
        }

        return false; // BE
    }
}
