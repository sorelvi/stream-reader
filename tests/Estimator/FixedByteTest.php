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
use Sorelvi\StreamReader\Estimator\FixedByte;
use Sorelvi\StreamReader\HandleContext;

class FixedByteTest extends TestCase
{
    public function testMaxAddReadBytes(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $estimator = new FixedByte($i);
            $this->assertEquals(
                $i - 1,
                $estimator->getMaxAddReadBytes(),
                "Wrong MaxAddReadBytes"
            );
        }
    }

    public function testHandle(): void
    {
        for ($lengthOfChar = 1; $lengthOfChar <= 10; $lengthOfChar++) {
            $estimator = new FixedByte($lengthOfChar);
            for ($bufferLength = 1; $bufferLength <= 10; $bufferLength++) {
                $context = new HandleContext();
                $str = str_repeat('a', $bufferLength);
                $remainder = $bufferLength % $lengthOfChar;
                $expected = max($remainder ? $lengthOfChar - $remainder : 0, 0);

                $this->assertEquals(
                    $expected,
                    $estimator->handle($str, $context),
                    "Error on handle"
                );
            }
        }
    }
}
