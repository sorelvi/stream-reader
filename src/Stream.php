<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader;

use Sorelvi\StreamReader\Exception\CanNotCreateStream;
use Sorelvi\StreamReader\Exception\CanNotReadZeroBytes;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\ErrorReadingFromStream;
use Sorelvi\StreamReader\Exception\FileNotAccessible;
use Sorelvi\StreamReader\Exception\IsNotStream;
use Sorelvi\StreamReader\Exception\TooManyEmptyAttempts;

class Stream implements StreamInterface
{
    private const SEEK_SPEED = 262144;
    private const DEFAULT_READ_LENGTH = 65536;

    private bool $isStreamSelfOpen = false;
    private int $currentPosition = 0;
    private int $maxEmptyAttempts = 20;
    private int $attemptsDelayMicroseconds = 10000;

    /** @param resource $stream */
    public function __construct(private readonly mixed $stream)
    {
        if (!is_resource($this->stream)) {
            throw new IsNotStream();
        }
        $this->iRead(0);
    }

    public static function createForString(string $dataString): self
    {
        $stream = fopen('php://temp', 'r+b');
        if ($stream === false) {
            throw new CanNotCreateStream();
        }
        fwrite($stream, $dataString);
        rewind($stream);

        $reader = new self($stream);
        $reader->isStreamSelfOpen = true;

        return $reader;
    }

    public static function createForFile(string $filePath): self
    {
        $isFile = is_file($filePath);
        $isReadable = is_readable($filePath);

        if (!$isFile || !$isReadable) {
            throw new FileNotAccessible('Can not open file', match (true) {
                !$isFile && !$isReadable => ErrorCode::SOURCE_IS_NOT_EXISTS->value,
                $isFile => ErrorCode::SOURCE_IS_NOT_READABLE->value,
                default => ErrorCode::SOURCE_IS_NOT_FILE->value,
            });
        }

        $inputFile = fopen($filePath, 'rb');
        if ($inputFile === false) {
            throw new CanNotCreateStream();
        }

        $reader = new self($inputFile);
        $reader->isStreamSelfOpen = true;

        return $reader;
    }

    public function __destruct()
    {
        if ($this->isStreamSelfOpen) {
            $this->closeInputFile();
        }
    }

    public function isEndOfStream(): bool
    {
        return feof($this->stream);
    }

    public function read(int $length = self::DEFAULT_READ_LENGTH): string
    {
        if ($length < 1) {
            throw  new CanNotReadZeroBytes();
        }

        if (!$this->isStreamValid()) {
            throw new ErrorReadingFromStream("Invalid stream", ErrorCode::SOURCE_STREAM_IS_INVALID->value);
        }

        if (feof($this->stream)) {
            return '';
        }

        $emptyAttempts = 0;
        $string = '';
        $needRead = $length;
        do {
            $part = fread($this->stream, $needRead);
            $isEof = feof($this->stream);

            if ($part === false) {
                throw new ErrorReadingFromStream("Broken stream", ErrorCode::SOURCE_STREAM_IS_BROKEN->value);
            }

            if ($part === '' && !$isEof) {
                $emptyAttempts++;
                if ($emptyAttempts > $this->maxEmptyAttempts) {
                    throw new TooManyEmptyAttempts();
                }

                usleep($this->attemptsDelayMicroseconds);
                continue;
            }

            $emptyAttempts = 0;
            $string .= $part;
            $needRead -= strlen($part);
        } while ($needRead > 0 && !$isEof);

        $this->iRead(strlen($string));

        return $string;
    }

    public function skip(int $offset): void
    {
        $meta = stream_get_meta_data($this->stream);

        if ($meta['seekable'] && (@fseek($this->stream, $offset, SEEK_CUR) === 0)) {
            $this->iRead($offset);
        } else {
            $needRead = $offset;
            while ($needRead > 0 && !feof($this->stream)) {
                $cRead = min(self::SEEK_SPEED, $needRead);
                $readLength = strlen($this->read($cRead));
                $needRead -= $readLength;
            }
        }
    }

    public function getCurrentPosition(): int
    {
        return $this->currentPosition;
    }

    public function setMaxEmptyAttempts(int $maxEmptyAttempts): void
    {
        $this->maxEmptyAttempts = $maxEmptyAttempts;
    }

    public function setAttemptsDelayMicroseconds(int $attemptsDelayMicroseconds): void
    {
        $this->attemptsDelayMicroseconds = $attemptsDelayMicroseconds;
    }

    private function iRead(int $length): void
    {
        $sysPos = @ftell($this->stream);
        if ($sysPos !== false) {
            $this->currentPosition = $sysPos;
        } else {
            $this->currentPosition += $length;
        }
    }

    private function isStreamValid(): bool
    {
        return is_resource($this->stream);
    }

    private function closeInputFile(): void
    {
        if ($this->isStreamValid()) {
            fclose($this->stream);
        }
    }
}
