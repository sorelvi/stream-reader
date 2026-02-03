<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests\Mock;

use Sorelvi\StreamReader\StreamInterface;

class MockInternalStream implements StreamInterface
{
    public int $lastSkip = 0;
    public function __construct(
        public bool $isEndOfStream = false,
        public string $read = 'Hello World!',
        public int $currentPosition = 0,
    ) {
    }

    public function isEndOfStream(): bool
    {
        return $this->isEndOfStream;
    }

    public function read(int $length): string
    {
        $result = substr($this->read, $this->currentPosition, $length);
        $this->currentPosition += $length;

        return $result;
    }

    public function skip(int $offset): void
    {
        $this->lastSkip = $offset;
        $this->currentPosition += $offset;
    }

    public function getCurrentPosition(): int
    {
        return $this->currentPosition;
    }
}
