<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Exception;

use Throwable;

class StreamDamaged extends StreamReaderException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message = "",
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode->value, $previous);
    }
}
