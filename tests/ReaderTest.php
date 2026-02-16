<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests;

use PHPUnit\Framework\TestCase;
use Sorelvi\StreamReader\Estimator\FixedByte;
use Sorelvi\StreamReader\Estimator\Utf8;
use Sorelvi\StreamReader\Exception\CanNotReadZeroChunk;
use Sorelvi\StreamReader\Exception\CanNotRestoreReadingStream;
use Sorelvi\StreamReader\Exception\ChunkLengthMustBePositive;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\MaxAddReadMustBeZeroOrPositive;
use Sorelvi\StreamReader\Exception\StreamDamaged;
use Sorelvi\StreamReader\HandleContext;
use Sorelvi\StreamReader\Reader;
use Sorelvi\StreamReader\Stream;
use Sorelvi\StreamReader\Tests\Mock\MockEstimator;
use Sorelvi\StreamReader\Tests\Mock\MockInternalStream;

class ReaderTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    public function testCreateForStringNoParam(): void
    {
        $reader = Reader::createForString('😀Hello world😀');
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('😀', $data);
        $this->assertEquals(4, $reader->context->getTotalReadBytes());
    }

    public function testCreateForStringContext(): void
    {
        $context = new HandleContext();
        $context->addTotalReadBytes(4);
        $reader = Reader::createForString('😀Hello world😀', $context);
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('H', $data);
        $this->assertEquals(5, $reader->context->getTotalReadBytes());
    }

    public function testCreateForStringEstimator(): void
    {
        $estimator = new FixedByte(1);
        $reader = Reader::createForString('Hello world', null, $estimator);
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('H', $data);
        $this->assertEquals(1, $reader->context->getTotalReadBytes());
    }

    public function testCreateForStringContextEstimator(): void
    {
        $estimator = new FixedByte(1);
        $context = new HandleContext();
        $context->addTotalReadBytes(4);
        $reader = Reader::createForString('😀Hello world😀', $context, $estimator);
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('H', $data);
        $this->assertEquals(5, $reader->context->getTotalReadBytes());
    }

    public function testCreateForFileNoParam(): void
    {
        $reader = Reader::createForFile($this->createFile('😀Hello world😀'));
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('😀', $data);
        $this->assertEquals(4, $reader->context->getTotalReadBytes());
    }

    public function testCreateForFileContext(): void
    {
        $context = new HandleContext();
        $context->addTotalReadBytes(4);
        $reader = Reader::createForFile($this->createFile('😀Hello world😀'), $context);
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('H', $data);
        $this->assertEquals(5, $reader->context->getTotalReadBytes());
    }

    public function testCreateForFileEstimator(): void
    {
        $estimator = new FixedByte(1);
        $reader = Reader::createForFile($this->createFile('Hello world😀'), null, $estimator);
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('H', $data);
        $this->assertEquals(1, $reader->context->getTotalReadBytes());
    }

    public function testCreateForFileContextEstimator(): void
    {
        $estimator = new FixedByte(1);
        $context = new HandleContext();
        $context->addTotalReadBytes(4);
        $reader = Reader::createForFile($this->createFile('😀Hello world😀'), $context, $estimator);
        $reader->setChunkLength(1);
        $data = $reader->readChunk()->current();
        $this->assertEquals('H', $data);
        $this->assertEquals(5, $reader->context->getTotalReadBytes());
    }

    public function testSetChunkLength(): void
    {
        $reader = Reader::createForString('Hello World');
        $reader->setChunkLength(1);
        $this->assertEquals('H', $reader->readChunk()->current());
        $this->assertEquals('el', $reader->readChunk(2)->current());
        $reader->setChunkLength(2);
        $this->assertEquals('lo', $reader->readChunk()->current());
        $this->expectException(ChunkLengthMustBePositive::class);
        $reader->setChunkLength(0);
    }

    public function testRestoreContext(): void
    {
        $stream1 = new MockInternalStream();
        $context1 = new HandleContext();
        $context1->addTotalReadBytes(1);
        $estimator1 = new Utf8();
        new Reader($stream1, $estimator1, $context1);
        $this->assertEquals(1, $stream1->lastSkip);

        $stream2 = new MockInternalStream();
        $stream2->currentPosition = 1;
        $context2 = new HandleContext();
        $context2->addTotalReadBytes(1);
        $estimator2 = new Utf8();
        new Reader($stream2, $estimator2, $context2);
        $this->assertEquals(0, $stream2->lastSkip);

        $stream3 = new MockInternalStream();
        $stream3->currentPosition = 1;
        $context3 = new HandleContext();
        $estimator3 = new Utf8();
        $this->expectException(CanNotRestoreReadingStream::class);
        new Reader($stream3, $estimator3, $context3);
    }

    public function testRestoreContextShortSource(): void
    {
        $stream1 = Stream::createForString('TestError');
        $context1 = new HandleContext();
        $context1->addTotalReadBytes(10);
        $estimator1 = new Utf8();

        $this->expectException(CanNotRestoreReadingStream::class);
        $this->expectExceptionCode(ErrorCode::SOURCE_CAN_NOT_SKIP_TO_TARGET->value);

        new Reader($stream1, $estimator1, $context1);
    }

    public function testReadChunkEmptySource(): void
    {
        $stream = new MockInternalStream();
        $stream->read = '';
        $context = new HandleContext();
        $estimator = new FixedByte(1);
        $reader = new Reader($stream, $estimator, $context);
        $this->assertFalse($reader->readChunk()->valid());
    }

    public function testReadChunk(): void
    {
        $reader = Reader::createForString('HelloWorld😀😀😀');
        $result = '';
        foreach ($reader->readChunk() as $chunk) {
            $result .= $chunk;
        }
        $this->assertEquals('HelloWorld😀😀😀', $result);
    }

    public function testReadChunkErrorMaxAddReadBytes(): void
    {
        $mockEstimator = new MockEstimator(0, 0);
        $reader = Reader::createForString(
            'HelloWorld😀😀😀',
            null,
            $mockEstimator
        );
        $reader->setChunkLength(1);
        $this->assertEquals('H', $reader->readChunk()->current());

        $mockEstimator->maxAddReadBytes = 1;
        $this->assertEquals('e', $reader->readChunk()->current());

        $mockEstimator->maxAddReadBytes = -1;
        $this->expectException(MaxAddReadMustBeZeroOrPositive::class);
        $reader->readChunk()->current();
    }

    public function testReadChunkLimitsTest(): void
    {
        $mockEstimator = new MockEstimator(null, 4);
        $mockEstimator->handleResults = [1,1,1,1];
        $reader = Reader::createForString(
            'HelloWorld😀😀😀',
            null,
            $mockEstimator
        );
        $reader->setChunkLength(1);
        $this->assertEquals('Hello', $reader->readChunk()->current());

        $mockEstimator->handleResults = [1,1,1,1,1];
        $this->expectException(StreamDamaged::class);
        $this->expectExceptionCode(ErrorCode::TOO_LONG_BYTE_CHAIN->value);
        $reader->readChunk()->current();
    }

    public function testReadIncompleteSequence(): void
    {
        $reader = Reader::createForString("Hello\xF0\x80\xBF");
        $reader->setChunkLength(6);
        $this->expectException(StreamDamaged::class);
        $this->expectExceptionCode(ErrorCode::INCOMPLETE_SEQUENCE_AT_EOF->value);
        $reader->readChunk()->current();
    }

    public function testReadZeroChunk(): void
    {
        $reader = Reader::createForString('HelloWorld');
        $this->assertEquals('H', $reader->readChunk(1)->current());
        $this->expectException(CanNotReadZeroChunk::class);
        $reader->readChunk(0)->current();
    }

    private function createFile(string $content): string
    {
        $currentFile = tempnam(sys_get_temp_dir(), 'stream_test_');
        if (!$currentFile) {
            throw new \Exception('Cannot create temp file');
        }
        file_put_contents($currentFile, $content);
        $this->files[] = $currentFile;

        return $currentFile;
    }

    private function removeFiles(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file) && is_writable($file)) {
                @unlink($file);
            }
        }

        $this->files = [];
    }

    protected function tearDown(): void
    {
        $this->removeFiles();
    }
}
