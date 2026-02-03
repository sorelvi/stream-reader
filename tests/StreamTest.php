<?php

/**
 * Copyright 2026 Sorelvi
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

namespace Sorelvi\StreamReader\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Sorelvi\StreamReader\Exception\CanNotCreateStream;
use Sorelvi\StreamReader\Exception\CanNotReadZeroBytes;
use Sorelvi\StreamReader\Exception\ErrorCode;
use Sorelvi\StreamReader\Exception\ErrorReadingFromStream;
use Sorelvi\StreamReader\Exception\FileNotAccessible;
use Sorelvi\StreamReader\Exception\IsNotStream;
use Sorelvi\StreamReader\Exception\TooManyEmptyAttempts;
use Sorelvi\StreamReader\Stream;
use Sorelvi\StreamReader\Tests\Mock\MockStream;

class StreamTest extends TestCase
{
    use PHPMock;

    /** @var string[] */
    private array $files = [];

    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup();
        MockStream::resetConfig();
    }

    public function testConstructor(): void
    {
        $testFile1 = $this->createFile('abcdefg');
        $resource = fopen($testFile1, 'r');
        $stream = new Stream($resource);

        $this->assertEquals(0, $stream->getCurrentPosition());
        $this->assertFalse($stream->isEndOfStream());

        $this->expectException(IsNotStream::class);
        new Stream('hello');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateForString(): void
    {
        $fclose = $this->getFunctionMock('Sorelvi\StreamReader', 'fclose');
        $stream = Stream::createForString('test');
        $fclose->expects($this->once())->willReturnCallback(
            function ($stream) {
                return \fclose($stream);
            }
        );
        $this->assertEquals(0, $stream->getCurrentPosition());
        $this->assertFalse($stream->isEndOfStream());
        $this->assertEquals('test', $stream->read(4));
        $this->assertEquals(4, $stream->getCurrentPosition());
        $this->assertEquals('', $stream->read());
        $this->assertTrue($stream->isEndOfStream());
        unset($stream);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateForFile(): void
    {
        $fclose = $this->getFunctionMock('Sorelvi\StreamReader', 'fclose');
        $stream = Stream::createForFile($this->createFile('test'));
        $fclose->expects($this->once())->willReturnCallback(
            function ($stream) {
                return \fclose($stream);
            }
        );
        $this->assertEquals(0, $stream->getCurrentPosition());
        $this->assertFalse($stream->isEndOfStream());
        $this->assertEquals('test', $stream->read(4));
        $this->assertEquals(4, $stream->getCurrentPosition());
        $this->assertEquals('', $stream->read());
        $this->assertTrue($stream->isEndOfStream());
        unset($stream);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateForStringNoOpen(): void
    {
        $fopen = $this->getFunctionMock('Sorelvi\StreamReader', 'fopen');
        $fopen->expects($this->once())->willReturn(false);
        $this->expectException(CanNotCreateStream::class);
        Stream::createForString('test');
    }

    public function testCreateForFileNoError(): void
    {
        $file = $this->createFile('test');
        $stream = Stream::createForFile($file);
        $this->assertEquals(0, $stream->getCurrentPosition());
        $this->assertFalse($stream->isEndOfStream());
        $this->assertEquals('test', $stream->read(4));
        $this->assertEquals(4, $stream->getCurrentPosition());
        $this->assertEquals('', $stream->read());
        $this->assertTrue($stream->isEndOfStream());
        unset($stream);
    }

    public function testCreateForFileIsDir(): void
    {
        vfsStream::newDirectory('subfolder')->at($this->root);
        $path = $this->root->url() . '/subfolder';
        $this->expectException(FileNotAccessible::class);
        $this->expectExceptionCode(ErrorCode::SOURCE_IS_NOT_FILE->value);
        Stream::createForFile($path);
    }

    public function testCreateForFileIsNotReadable(): void
    {
        $file = vfsStream::newFile('secret.txt', 0000)->at($this->root);
        $path = $file->url();
        $this->expectException(FileNotAccessible::class);
        $this->expectExceptionCode(ErrorCode::SOURCE_IS_NOT_READABLE->value);
        Stream::createForFile($path);
    }

    public function testCreateForFileIsNotExist(): void
    {
        $this->expectException(FileNotAccessible::class);
        $this->expectExceptionCode(ErrorCode::SOURCE_IS_NOT_EXISTS->value);
        Stream::createForFile($this->root->url() . '/xxx.xxx');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCreateForFileNoOpen(): void
    {
        $file = $this->createFile('test');
        $fopen = $this->getFunctionMock('Sorelvi\StreamReader', 'fopen');
        $fopen->expects($this->once())->willReturn(false);
        $this->expectException(CanNotCreateStream::class);
        Stream::createForFile($file);
    }

    public function testReadZeroByte(): void
    {
        $stream = Stream::createForString('test');
        $this->expectException(CanNotReadZeroBytes::class);
        $stream->read(-1);
    }

    public function testExternalStream(): void
    {
        $testFile1 = $this->createFile('abcdefg');
        $resource = fopen($testFile1, 'r');
        $stream = new Stream($resource);
        unset($stream);
        $this->assertTrue(is_resource($resource));
        fclose($resource);
    }

    public function testFailResource(): void
    {
        $testFile1 = $this->createFile('abcdefg');
        $resource = fopen($testFile1, 'r');
        $stream = new Stream($resource);
        $this->assertEquals('a', $stream->read(1));
        fclose($resource);
        $this->expectException(ErrorReadingFromStream::class);
        $this->expectExceptionCode(ErrorCode::SOURCE_STREAM_IS_INVALID->value);
        $stream->read(1);
    }

    public function testReadAfterEnd(): void
    {
        $stream = Stream::createForString('test');
        $stream->read();
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals('', $stream->read());
        }
    }

    public function testReadStreamIsBroken(): void
    {
        MockStream::$failAtByte = 1;
        MockStream::$maxChunk = 1;
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $this->assertEquals('h', $stream->read(1));
        $this->expectException(ErrorReadingFromStream::class);
        $this->expectExceptionCode(ErrorCode::SOURCE_STREAM_IS_BROKEN->value);
        $stream->read(1);
    }

    public function testReadStreamWithDelay(): void
    {
        MockStream::$emptyReadsCount = 4;
        MockStream::$maxChunk = 1;
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $stream->setMaxEmptyAttempts(4);
        $stream->setAttemptsDelayMicroseconds(1);
        $this->assertEquals('h', $stream->read(1));
    }

    public function testReadStreamWithDelayError(): void
    {
        MockStream::$emptyReadsCount = 2;
        MockStream::$maxChunk = 1;
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $stream->setMaxEmptyAttempts(1);
        $stream->setAttemptsDelayMicroseconds(1);
        $this->expectException(TooManyEmptyAttempts::class);
        $stream->read(1);
    }

    public function testReadStreamWithDelayValue(): void
    {
        MockStream::$emptyReadsCount = 1;
        MockStream::$maxChunk = 1;
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $stream->setMaxEmptyAttempts(1);
        $stream->setAttemptsDelayMicroseconds(100000);
        $mkStart = hrtime(true);
        $stream->read(1);
        $mkEnd = hrtime(true);
        $elapsedDelta = abs(floor(($mkEnd - $mkStart) / 1e6) - 100);
        $this->assertLessThan(15, $elapsedDelta);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNoTell(): void
    {
        $fopen = $this->getFunctionMock('Sorelvi\StreamReader', 'ftell');
        $fopen->expects($this->atLeastOnce())->willReturn(false);
        MockStream::$maxChunk = 1;
        MockStream::$canTell = false;
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $stream->read(1);
        $this->assertEquals(1, $stream->getCurrentPosition());
        $stream->read(1);
        $this->assertEquals(2, $stream->getCurrentPosition());
    }

    public function testSkip(): void
    {
        $stream = Stream::createForString('helloworld');
        $stream->skip(5);
        $this->assertEquals(5, $stream->getCurrentPosition());
        $this->assertEquals('w', $stream->read(1));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSkipErrorOnSeek(): void
    {
        $fseek = $this->getFunctionMock('Sorelvi\StreamReader', 'fseek');
        $fseek->expects($this->atLeastOnce())->willReturn(-1);
        $stream = Stream::createForString('helloworld');
        $stream->skip(5);
        $this->assertEquals(5, $stream->getCurrentPosition());
        $this->assertEquals('w', $stream->read(1));
    }

    public function testReadOnHalfStream(): void
    {
        $file = $this->createFile('test');
        $resource = fopen($file, 'r');
        fread($resource, 1);
        $stream = new Stream($resource);
        $this->assertEquals($stream->getCurrentPosition(), 1);
        $this->assertEquals('e', $stream->read(1));
    }

    public function testReadStreamWithShortOut(): void
    {
        MockStream::$maxChunk = 1;
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $this->assertEquals('hello', $stream->read(5));
    }

    public function testReadStreamWithNotStable(): void
    {
        MockStream::$maxChunk = 1;
        MockStream::$emptyReadsCount = 4;
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $stream->setMaxEmptyAttempts(4);
        $stream->setAttemptsDelayMicroseconds(1);
        $this->assertEquals('h', $stream->read(1));
        MockStream::$currentEmptyReads = 0;
        $this->assertEquals('e', $stream->read(1));
        MockStream::$emptyReadsCount = 5;
        MockStream::$currentEmptyReads = 0;
        $this->expectException(TooManyEmptyAttempts::class);
        $stream->read(1);
    }

    public function testReadStreamWithNotStableSerial(): void
    {
        MockStream::$maxChunk = 1;
        MockStream::$emptyReadsCount = 4;
        MockStream::$emptyReadsCountSerial = [5];
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $stream->setMaxEmptyAttempts(4);
        $stream->setAttemptsDelayMicroseconds(1);
        $this->expectException(TooManyEmptyAttempts::class);
        $stream->read(5);
    }

    public function testReadStreamWithLongRequest(): void
    {
        MockStream::$maxChunk = 1;
        MockStream::$emptyReadsCount = 4;
        MockStream::$emptyReadsCountSerial = [4,4,4,4,4,4,4,4,4,];
        MockStream::setContent('hello');
        $resource = fopen('mock://test', 'r');
        $stream = new Stream($resource);
        $stream->setMaxEmptyAttempts(4);
        $stream->setAttemptsDelayMicroseconds(1);
        $this->assertEquals('hello', $stream->read(10));
    }

    public static function setUpBeforeClass(): void
    {
        if (!in_array('mock', stream_get_wrappers())) {
            stream_wrapper_register('mock', MockStream::class);
        }
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
