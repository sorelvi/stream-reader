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
use Sorelvi\StreamReader\HandleContext;

class FixedByte implements EstimatorInterface
{
    public function __construct(private readonly int $lengthOfChar)
    {
    }

    public function handle(string $buffer, HandleContext $context): int
    {
        if ($this->lengthOfChar === 1) {
            return 0;
        }

        $remainder = strlen($buffer) % $this->lengthOfChar;

        return $remainder ? $this->lengthOfChar - $remainder : 0;
    }

    public function getMaxAddReadBytes(): int
    {
        return $this->lengthOfChar - 1;
    }
}
