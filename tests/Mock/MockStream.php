<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests\Mock;

class MockStream
{
    public static int $maxChunk = 8192;
    private static string $data = '';
    private static int $position = 0;

    public static bool $canSeek = true;
    public static int $failAtByte = -1;
    public static int $emptyReadsCount = 0;
    /** @var int[] */
    public static array $emptyReadsCountSerial = [];
    public static bool $canTell = true;

    public static int $currentEmptyReads = 0;
    public static bool $eofRead = false;

    public mixed $context;


    public static function setContent(string $data): void
    {
        self::$data = $data;
        self::$position = 0;
        self::$eofRead = false;
    }

    public static function resetConfig(): void
    {
        self::$data = '';
        self::$canSeek = true;
        self::$canTell = true;
        self::$failAtByte = -1;
        self::$emptyReadsCount = 0;
        self::$maxChunk = 8192;
        self::$currentEmptyReads = 0;
        self::$position = 0;
        self::$emptyReadsCountSerial = [];
        self::$eofRead = false;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        self::$position = 0;
        self::$currentEmptyReads = 0;
        self::$eofRead = false;

        return true;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function stream_read(int $count): string|false
    {
        $count = min(self::$maxChunk, $count);

        if (self::$failAtByte !== -1 && self::$position >= self::$failAtByte) {
            return false;
        }

        if (self::$currentEmptyReads < self::$emptyReadsCount) {
            self::$currentEmptyReads++;
            return '';
        }

        self::$currentEmptyReads = 0;
        self::confirmSerial();

        if (self::$position >= strlen(self::$data)) {
            if (self::$eofRead) {
                return false;
            }
            self::$eofRead = true;
        }

        $ret = substr(self::$data, self::$position, $count);
        self::$position += strlen($ret);

        return $ret;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function stream_tell(): false|int
    {
        if (!self::$canTell) {
            return false;
        }
        return self::$position;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function stream_eof(): bool
    {
        return self::$position >= strlen(self::$data);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if (!self::$canSeek) {
            return false;
        }

        $newPos = self::$position;
        switch ($whence) {
            case SEEK_SET:
                $newPos = $offset;
                break;
            case SEEK_CUR:
                $newPos += $offset;
                break;
            case SEEK_END:
                $newPos = strlen(self::$data) + $offset;
                break;
        }

        if ($newPos < 0 || $newPos > strlen(self::$data)) {
            return false;
        }

        self::$position = $newPos;
        return true;
    }

    /**
     * @return array<mixed>
     */
    //phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function stream_stat(): array
    {
        return [];
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true;
    }

    private static function confirmSerial(): void
    {
        if (empty(self::$emptyReadsCountSerial)) {
            return;
        }

        self::$emptyReadsCount = array_shift(self::$emptyReadsCountSerial);
    }
}
