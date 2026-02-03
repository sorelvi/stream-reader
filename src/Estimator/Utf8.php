<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Estimator;

use Sorelvi\StreamReader\EstimatorInterface;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\StreamDamaged;
use Sorelvi\StreamReader\HandleContext;

class Utf8 implements EstimatorInterface
{
    private const MAX_TAIL_BYTES = 3;

    public function handle(string $buffer, HandleContext $context): int
    {
        $len = strlen($buffer);
        if ($len === 0) {
            return 0;
        }

        if (ord($buffer[$len - 1]) < 128) {
            return 0;
        }

        $checkLimit = min($len, self::MAX_TAIL_BYTES + 1);
        for ($cursor = 1; $cursor <= $checkLimit; $cursor++) {
            $byte = ord($buffer[$len - $cursor]);

            if ($byte < 128) {
                throw new StreamDamaged(
                    ErrorCode::ORPHAN_CONTINUATION_BYTE,
                    "Detected orphan UTF-8 continuation byte(s) without a start byte."
                );
            }

            if ($byte > 191) {
                if ($byte > 244 || $byte === 192 || $byte === 193) {
                    throw new StreamDamaged(
                        ErrorCode::INVALID_START_BYTE_DETECTED,
                        sprintf("Invalid UTF-8 start byte detected: 0x%02X", $byte)
                    );
                }

                /** @var int<2, 4> $expectedLen */
                $expectedLen = match (true) {
                    $byte >= 240 => 4,
                    $byte >= 224 => 3,
                    default => 2,
                };

                if ($cursor > $expectedLen) {
                    throw new StreamDamaged(
                        ErrorCode::ORPHAN_CONTINUATION_BYTE
                    );
                }

                return $expectedLen - $cursor;
            }
        }

        throw new StreamDamaged(
            ErrorCode::LONG_SEQUENCE_OF_CONTINUATION,
            "Unexpected sequence of continuation bytes at the end of the buffer."
        );
    }

    public function getMaxAddReadBytes(): int
    {
        return self::MAX_TAIL_BYTES;
    }
}
