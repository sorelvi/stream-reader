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
use Sorelvi\StreamReader\Enum\Preset;
use Sorelvi\StreamReader\Exception\CanNotReadZeroChunk;
use Sorelvi\StreamReader\Exception\CanNotRestoreReadingStream;
use Sorelvi\StreamReader\Exception\ChunkLengthMustBePositive;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\MaxAddReadMustBeZeroOrPositive;
use Sorelvi\StreamReader\Exception\StreamDamaged;

final class Reader implements ReaderInterface
{
    private int $chunkLength = 65536;
    public readonly HandleContext $context;

    public function __construct(
        private StreamInterface $stream,
        private readonly EstimatorInterface $estimator,
        ?HandleContext $context = null,
    ) {
        if (!$context) {
            $context = new HandleContext();
        }

        $this->context = $context;

        $this->restoreContext();
    }

    public static function createForString(
        string $dataString,
        ?HandleContext $context = null,
        EstimatorInterface|Preset $estimator = Preset::UTF8
    ): self {
        return new self(
            Stream::createForString($dataString),
            $estimator instanceof EstimatorInterface ?
                $estimator :
                StreamCompletenessEstimatorFactory::create($estimator),
            $context
        );
    }

    public static function createForFile(
        string $filePath,
        ?HandleContext $context = null,
        EstimatorInterface|Preset $estimator = Preset::UTF8,
    ): self {
        return new self(
            Stream::createForFile($filePath),
            $estimator instanceof EstimatorInterface ?
                $estimator :
                StreamCompletenessEstimatorFactory::create($estimator),
            $context
        );
    }

    public function setChunkLength(int $chunkLength): void
    {
        if ($chunkLength < 1) {
            throw new ChunkLengthMustBePositive();
        }
        $this->chunkLength = $chunkLength;
    }

    /**
     * @return Generator<string>
     */
    public function readChunk(?int $chunkLength = null): Generator
    {
        $chunkLength = $chunkLength ?? $this->chunkLength;

        if ($chunkLength < 1) {
            throw  new CanNotReadZeroChunk();
        }

        $stream = $this->stream;

        while (!$stream->isEndOfStream()) {
            $chunk = $stream->read($chunkLength);
            $chunk = $this->handleChunk($stream, $chunk);
            if ($chunk === '') {
                return;
            }

            $this->context->addTotalReadBytes(strlen($chunk));

            yield $chunk;
        }
    }

    private function restoreContext(): void
    {
        if ($this->stream->getCurrentPosition() > $this->context->getTotalReadBytes()) {
            throw new CanNotRestoreReadingStream();
        }

        $this->stream->skip(
            $this->context->getTotalReadBytes() -
            $this->stream->getCurrentPosition()
        );

        if ($this->stream->getCurrentPosition() !== $this->context->getTotalReadBytes()) {
            throw new CanNotRestoreReadingStream(code: ErrorCode::SOURCE_CAN_NOT_SKIP_TO_TARGET->value);
        }
    }

    private function handleChunk(
        StreamInterface $resource,
        string $chunk,
    ): string {
        $estimator = $this->estimator;
        $context = $this->context;
        $currentAddRead = 0;
        $maxAddRead = $estimator->getMaxAddReadBytes();
        if ($maxAddRead < 0) {
            throw new MaxAddReadMustBeZeroOrPositive();
        }

        while (
            ($context->needMoreBytes = $estimator->handle($chunk, $context))
            && !$resource->isEndOfStream()
        ) {
            $currentAddRead += $context->needMoreBytes;

            if ($currentAddRead > $maxAddRead) {
                throw new StreamDamaged(ErrorCode::TOO_LONG_BYTE_CHAIN);
            }

            $addChunk = $resource->read($context->needMoreBytes);

            $chunk .= $addChunk;
        }

        if ($context->needMoreBytes > 0) {
            throw new StreamDamaged(
                ErrorCode::INCOMPLETE_SEQUENCE_AT_EOF,
                'Stream ended with incomplete character sequence'
            );
        }

        return $chunk;
    }
}
