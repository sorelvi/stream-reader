<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader;

use Generator;

interface ReaderInterface
{
    /**
     * @return Generator<string>
     */
    public function readChunk(?int $chunkLength = null): Generator;
}
