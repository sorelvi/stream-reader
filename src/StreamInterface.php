<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader;

interface StreamInterface
{
    public function isEndOfStream(): bool;
    public function read(int $length): string;
    public function skip(int $offset): void;
    public function getCurrentPosition(): int;
}
