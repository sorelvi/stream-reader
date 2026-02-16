<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\HandleContext;

class HandleContextTest extends TestCase
{
    /**
     * @param array<mixed> $source
     * @param array<mixed> $expected
     * @dataProvider providePositiveLeData
     */
    public function testFromArray(array $source, array $expected): void
    {
        $context = HandleContext::fromArray($source);

        $this->compareArraysRecursive($expected, $context->toArray());
    }


    /**
     * @return iterable<mixed>
     */
    public static function providePositiveLeData(): iterable
    {
        yield 'case 1' => [
            [

            ],
            [
                'needMoreBytes' => 0,
                'totalReadBytes' => 0,
                'context' => [],
            ],
        ];

        yield 'case 2' => [
            [
                'needMoreBytes' => 0,
                'totalReadBytes' => 0,
            ],
            [
                'needMoreBytes' => 0,
                'totalReadBytes' => 0,
                'context' => [],
            ],
        ];

        yield 'case 3' => [
            [
                'needMoreBytes' => 1,
                'totalReadBytes' => 3,
                'context' => [
                    [
                        'stringValue' => 'xxx',
                        'intValue' => 3,
                        'floatValue' => 3.3,
                        'boolValue' => false,
                        'nullValue' => null,
                        3 => 'yps',
                    ],
                ],
            ],
            [
                'needMoreBytes' => 1,
                'totalReadBytes' => 3,
                'context' => [
                    [
                        'stringValue' => 'xxx',
                        'intValue' => 3,
                        'floatValue' => 3.3,
                        'boolValue' => false,
                        'nullValue' => null,
                        '3' => 'yps',
                    ],
                ],
            ],
        ];
    }


    /**
     * @param array<mixed> $source
     * @dataProvider provideFromArrayExceptionsData
     */
    public function testFromArrayExceptions(array $source, string $exceptionClass, ?ErrorCode $errorCode): void
    {
        $this->expectException($exceptionClass);
        if ($errorCode) {
            $this->expectExceptionCode($errorCode->value);
        }

        HandleContext::fromArray($source);
    }

    /**
     * @return iterable<mixed>
     */
    public static function provideFromArrayExceptionsData(): iterable
    {
        yield 'case 1' => [
            [
                'context' => false,
            ],
            InvalidArgumentException::class,
            ErrorCode::PARAMETER_CONTEXT_VALUE_MUST_BE_ARRAY,
        ];

        yield 'case 2' => [
            [
                'context' => [
                    'group' => false,
                ],
            ],
            InvalidArgumentException::class,
            ErrorCode::PARAMETER_CONTEXT_GROUP_VALUE_MUST_BE_ARRAY,
        ];

        yield 'case 3' => [
            [
                'context' => [
                    'group' => [
                        'key' => ['xx'],
                    ],
                ],
            ],
            InvalidArgumentException::class,
            ErrorCode::PARAMETER_CONTEXT_VALUE_MUST_BE_SCALAR,
        ];

        yield 'case 4' => [
            [
                'needMoreBytes' => false,
            ],
            InvalidArgumentException::class,
            ErrorCode::PARAMETER_NEED_MORE_BYTES_VALUE_MUST_BE_INT_ZERO_POSITIVE,
        ];

        yield 'case 6' => [
            [
                'totalReadBytes' => false,
            ],
            InvalidArgumentException::class,
            ErrorCode::PARAMETER_TOTAL_READ_BYTES_VALUE_MUST_BE_INT_ZERO_POSITIVE,
        ];

        yield 'case 7' => [
            [
                'needMoreBytes' => -1,
            ],
            InvalidArgumentException::class,
            ErrorCode::PARAMETER_NEED_MORE_BYTES_VALUE_MUST_BE_INT_ZERO_POSITIVE,
        ];

        yield 'case 9' => [
            [
                'totalReadBytes' => -1,
            ],
            InvalidArgumentException::class,
            ErrorCode::PARAMETER_TOTAL_READ_BYTES_VALUE_MUST_BE_INT_ZERO_POSITIVE,
        ];
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $current
     */
    public function compareArraysRecursive(array $expected, array $current, string $path = ''): void
    {
        foreach ($expected as $key => $expectedValue) {
            $currentPath = $path ? $path . '[' . $key . ']' : '[' . $key . ']';

            if (!array_key_exists($key, $current)) {
                $this->fail("Key {$currentPath} not found in current array");
            }

            $currentValue = $current[$key];

            if (is_array($expectedValue)) {
                if (!is_array($currentValue)) {
                    $this->fail("Expected array at {$currentPath}, got " . gettype($currentValue));
                }

                $this->compareArraysRecursive($expectedValue, $currentValue, $currentPath);
            } else {
                $this->assertEquals($expectedValue, $currentValue, "Failed at path: {$currentPath}");
            }
        }

        foreach ($current as $key => $currentValue) {
            if (!array_key_exists($key, $expected)) {
                $currentPath = $path ? $path . '[' . $key . ']' : '[' . $key . ']';
                $this->fail("Unexpected key {$currentPath} found in expected array");
            }
        }
    }

    public function testSimpleLogic(): void
    {
        $handleContext = new HandleContext();
        $handleContext->addTotalReadBytes(100);
        $this->assertEquals(100, $handleContext->getTotalReadBytes());
        $handleContext->addTotalReadBytes(100);
        $this->assertEquals(200, $handleContext->getTotalReadBytes());
        $handleContext->setTotalReadBytes(50);
        $this->assertEquals(50, $handleContext->getTotalReadBytes());
        $handleContext->resetTotalReadBytes();
        $this->assertEquals(0, $handleContext->getTotalReadBytes());
        $handleContext->setParam('t1', 'k1', 10);
        $this->assertEquals(10, $handleContext->getParam('t1', 'k1', 'default'));
        $this->assertEquals(10, $handleContext->getParam('t1', 'k1'));
    }
}
