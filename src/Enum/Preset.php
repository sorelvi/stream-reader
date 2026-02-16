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
    case BYTE1;
    case BYTE2;
    case BYTE3;
    case BYTE4;
    case BYTE5;
    case BYTE6;
    case BYTE7;
    case BYTE8;
    case BYTE9;
    case BYTE10;
    case UTF8;
    case UTF16;
    case UTF16LE;
    case UTF16BE;
    case UTF32;
}
