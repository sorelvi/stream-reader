<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader;

use InvalidArgumentException;
use Sorelvi\StreamReader\Exception\ErrorCode;

final class HandleContext
{
    public int $needMoreBytes = 0;

    private int $totalReadBytes = 0;

    /** @var array<string, array<string,string|int|float|bool|null>> */
    private array $context = [];

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $context = new self();
        $context->totalReadBytes = self::getPositiveIntValue(
            "totalReadBytes",
            $data,
            ErrorCode::PARAMETER_TOTAL_READ_BYTES_VALUE_MUST_BE_INT_ZERO_POSITIVE
        );
        $contextInput = $data["context"] ?? [];

        if (!is_array($contextInput)) {
            throw new InvalidArgumentException(
                'Context data must be an array',
                ErrorCode::PARAMETER_CONTEXT_VALUE_MUST_BE_ARRAY->value
            );
        }

        foreach ($contextInput as $part => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(
                    'Context data part must be an array',
                    ErrorCode::PARAMETER_CONTEXT_GROUP_VALUE_MUST_BE_ARRAY->value
                );
            }

            foreach ($item as $key => $value) {
                if ($value !== null && !is_scalar($value)) {
                    throw new InvalidArgumentException(
                        'Context parameter must be a scalar value or null',
                        ErrorCode::PARAMETER_CONTEXT_VALUE_MUST_BE_SCALAR->value
                    );
                }

                $context->setParam((string) $part, (string) $key, $value);
            }
        }

        return $context;
    }

    /**
     * @param array<mixed> $data
     */
    private static function getPositiveIntValue(
        string $key,
        array $data,
        ErrorCode $errorNotInt
    ): int {
        if (array_key_exists($key, $data)) {
            if (!is_int($data[$key]) || $data[$key] < 0) {
                throw new InvalidArgumentException(
                    "$key value must be a non-negative integer.",
                    $errorNotInt->value
                );
            }
            return $data[$key];
        }

        return 0;
    }

    /**
     * @return array<string, int|array<string, array<string,string|int|float|bool|null>>>
     */
    public function toArray(): array
    {
        return [
            'needMoreBytes' => 0,
            'totalReadBytes' => $this->totalReadBytes,
            'context' => $this->context,
        ];
    }

    public function setParam(string $part, string $key, string|int|float|bool|null $value): self
    {
        $this->context[$part][$key] = $value;

        return $this;
    }

    public function getParam(
        string $part,
        string $key,
        string|int|float|bool|null $default = null
    ): string|int|float|bool|null {
        return $this->context[$part][$key] ?? $default;
    }

    public function hasParam(string $part, string $key): bool
    {
        return array_key_exists($key, $this->context[$part] ?? []);
    }

    public function getTotalReadBytes(): int
    {
        return $this->totalReadBytes;
    }

    public function setTotalReadBytes(int $totalReadBytes): void
    {
        $this->totalReadBytes = $totalReadBytes;
    }

    public function addTotalReadBytes(int $totalReadBytes): void
    {
        $this->totalReadBytes += $totalReadBytes;
    }

    public function resetTotalReadBytes(): void
    {
        $this->totalReadBytes = 0;
    }
}
