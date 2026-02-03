<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests\Mock;

use Sorelvi\StreamReader\EstimatorInterface;
use Sorelvi\StreamReader\HandleContext;

class MockEstimator implements EstimatorInterface
{
    public int $countOfHandle = 0;

    /** @var int[] */
    public array $handleResults = [];

    public function __construct(
        public ?int $handleResult = null,
        public int $maxAddReadBytes = 0,
    ) {
    }

    public function handle(string $buffer, HandleContext $context): int
    {
        $this->countOfHandle++;

        if ($this->handleResult !== null) {
            return $this->handleResult;
        }

        if (!empty($this->handleResults)) {
            return array_shift($this->handleResults);
        }

        return 0;
    }

    public function getMaxAddReadBytes(): int
    {
        return $this->maxAddReadBytes;
    }
}
