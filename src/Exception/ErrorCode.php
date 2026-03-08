<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Exception;

enum ErrorCode: int
{
    /* ENCODING ERRORS */
    case TOO_LONG_BYTE_CHAIN = 101;
    case ORPHAN_CONTINUATION_BYTE = 102;
    case INVALID_START_BYTE_DETECTED = 103;
    case LONG_SEQUENCE_OF_CONTINUATION = 104;
    case LOW_SURROGATE_AT_BEGINNING = 105;
    case ORPHAN_LOW_SURROGATE = 106;
    case INCOMPLETE_SEQUENCE_AT_EOF = 107;

    /* CONTEXT */
    case PARAMETER_CONTEXT_VALUE_MUST_BE_ARRAY = 201;
    case PARAMETER_CONTEXT_GROUP_VALUE_MUST_BE_ARRAY = 202;
    case PARAMETER_TOTAL_READ_BYTES_VALUE_MUST_BE_INT_ZERO_POSITIVE = 205;
    case PARAMETER_CONTEXT_VALUE_MUST_BE_SCALAR = 206;

    /* STREAM */
    case SOURCE_STREAM_IS_INVALID = 301;
    case SOURCE_STREAM_IS_BROKEN = 302;
    case SOURCE_IS_NOT_FILE = 303;
    case SOURCE_IS_NOT_READABLE = 304;
    case SOURCE_IS_NOT_EXISTS = 305;
    case SOURCE_CAN_NOT_SKIP_TO_TARGET = 306;
}
