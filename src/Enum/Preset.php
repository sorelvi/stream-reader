<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Enum;

enum Preset
{
    case byte1;
    case byte2;
    case byte3;
    case byte4;
    case byte5;
    case byte6;
    case byte7;
    case byte8;
    case byte9;
    case byte10;
    case UTF8;
    case UTF16;
    case UTF16LE;
    case UTF16BE;
    case UTF32;
}
