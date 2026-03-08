# Sorelvi Stream Reader

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777bb4.svg)
![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)
[![CI](https://github.com/sorelvi/stream-reader/actions/workflows/tests.yml/badge.svg)](https://github.com/sorelvi/stream-reader/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/sorelvi/stream-reader.svg)](https://packagist.org/packages/sorelvi/stream-reader)

*Industrial-grade, memory-efficient streaming reader for PHP.*

Reading gigabytes of text data (logs, SQL dumps, CSVs) in PHP can be tricky. Standard `fread` breaks multi-byte characters (UTF-8, UTF-16) if a chunk boundary splits a character in half. `Sorelvi\StreamReader` solves this problem by strictly analyzing byte sequences and ensuring every yielded chunk contains only valid, complete characters.

## Key Features
- **Memory Efficient**: Uses Generators (`yield`) to process files of any size (even terabytes) with constant O(1) memory usage.
- **Multibyte Safe**: Guaranteed character integrity. Never splits a UTF-8 emoji or a UTF-16 surrogate pair across chunks.
- **Zero Dependencies**: Works on pure PHP (no `mbstring` required).
- **Polyglot**: Built-in support via `Preset` enums:
  * **UTF-8** (optimized bitwise scanning)
  * **UTF-16** (LE/BE auto-detection via BOM or manual config)
  * **UTF-32**
  * **Fixed-byte encodings** (ASCII, Windows-1251, ISO-8859-1 via `Preset::BYTE1`)
  * **Fixed-width protocols** (`Preset::BYTE2` through `Preset::BYTE10`)
- **Network/Pipe Ready**: Built-in retry logic handles slow or non-blocking network streams.
- **Resumable**: Architecture supports pausing and resuming reading via `HandleContext`.
- **Extensible**: Full support for custom `StreamInterface` and `EstimatorInterface` implementations.

## Requirements
* PHP 8.2 or higher

## Installation

```shell
composer require sorelvi/stream-reader
```

## Quick Start
### Reading a Large File
The easiest way to read a file is using the static factory method with a `Preset`.
```php
use Sorelvi\StreamReader\Reader;
use Sorelvi\StreamReader\Enum\Preset;
use Sorelvi\StreamReader\Exception\StreamReaderException;

try {
    // 1. Create a reader for a UTF-8 file
    $reader = Reader::createForFile('/path/to/huge_dump.sql');

    // Optional: Adjust chunk size (default is 64KB)
    $reader->setChunkLength(8192); // 8KB

    // 2. Iterate cleanly
    foreach ($reader->readChunk() as $chunk) {
        // $chunk is guaranteed to end on a valid character boundary
        // e.g. send to database or parse CSV line
        process_data($chunk);
    }
} catch (StreamReaderException $e) {
    // Handles IO errors, corrupted streams, and encoding violations
    error_log('[' . $e->getCode() . '] ' . $e->getMessage());
}
```

### Reading from String
```php
use Sorelvi\StreamReader\Reader;

$sql = "INSERT INTO ... 🚀"; // String with emojis
$reader = Reader::createForString($sql);

foreach ($reader->readChunk() as $chunk) {
    echo $chunk;
}
```

### Per-call Chunk Size
The `readChunk()` method accepts an optional chunk size that overrides the instance default for that single call:
```php
$reader = Reader::createForString('Hello World');
$reader->setChunkLength(2); // default: 2 bytes

$first  = $reader->readChunk(1)->current(); // 'H'  — one-off override
$second = $reader->readChunk()->current();  // 'el' — uses default 2
```

### Integrity vs. Validation
**Important**: This library focuses on Stream Integrity, not Content Validation.
- **What it DOES**: It ensures that chunks are not split in the middle of a byte sequence defined by the encoding (e.g., it won't cut a 4-byte Emoji in half).
- **What it does NOT**: It does not validate if the bytes actually represent valid characters in that encoding. If you read a binary file as UTF-8, the reader will still process it without error, ensuring "structural" completeness based on UTF-8 bit-mask rules, even if the resulting output is garbage.

**Garbage In → Complete Garbage Out (not Half-Garbage).**

## Advanced Usage
### Dependency Injection (Manual Construction)
For strict control over the estimator or context, you can instantiate the class manually.

```php
use Sorelvi\StreamReader\Reader;
use Sorelvi\StreamReader\StreamCompletenessEstimatorFactory;
use Sorelvi\StreamReader\Enum\Preset;
use Sorelvi\StreamReader\HandleContext;

// 1. Prepare resources
$inputFile = fopen('data.csv', 'rb');
$context = new HandleContext();

// 2. Create the specific estimator
$estimator = StreamCompletenessEstimatorFactory::create(Preset::UTF16LE);

// 3. Instantiate Reader with explicit dependencies
$reader = new Reader($inputFile, $estimator, $context);

foreach ($reader->readChunk() as $chunk) {
    // ...
}
```

### Resume Reading (Context Management)
The `HandleContext` tracks the number of bytes read. You can persist this state to resume reading later (e.g., between HTTP requests or background jobs).

```php
use Sorelvi\StreamReader\Reader;
use Sorelvi\StreamReader\HandleContext;
use Sorelvi\StreamReader\Enum\Preset;

// Step 1: Read some data
$context = new HandleContext();
$reader = Reader::createForFile('large.log', $context, Preset::UTF16);

foreach ($reader->readChunk() as $chunk) {
    process($chunk);
    if (should_pause()) {
        break;
    }
}

// Step 2: Save context
$state = $context->toArray();
save_to_db($state);

// --- Later ---

// Step 3: Resume
$newContext = HandleContext::fromArray(load_from_db());

// The reader will automatically seek to the correct position
$reader = Reader::createForFile('large.log', $newContext, Preset::UTF16);
```

#### HandleContext API
| Method | Description |
|--------|-------------|
| `getTotalReadBytes(): int` | Returns total bytes consumed so far. |
| `addTotalReadBytes(int): void` | Increments the byte counter. |
| `setTotalReadBytes(int): void` | Overrides the byte counter. |
| `resetTotalReadBytes(): void` | Resets counter to zero. |
| `toArray(): array` | Serializes context to a plain array (for persistence). |
| `fromArray(array): self` | Restores context from a previously serialized array. |
| `setParam(string $part, string $key, scalar\|null): self` | Stores arbitrary state (used internally by estimators). |
| `getParam(string $part, string $key, $default): scalar\|null` | Retrieves stored state. |
| `hasParam(string $part, string $key): bool` | Checks if a parameter is stored. |

### Supporting UTF-16
The reader intelligently handles UTF-16 endianness:
1. Uses the explicitly configured endianness if `Preset::UTF16LE` or `Preset::UTF16BE` is specified.
2. For `Preset::UTF16` (auto mode), detects endianness from the Byte Order Mark (BOM): `0xFF 0xFE` → LE, anything else → BE (per RFC 2781).
3. Correctly handles Surrogate Pairs (4-byte characters in UTF-16) to ensure they are not split across chunks.
4. The detected endianness is stored in `HandleContext` and reused on subsequent calls, ensuring consistency across resumed sessions.

```php
// Auto-detect BOM
$reader = Reader::createForFile('export_utf16.csv', null, Preset::UTF16);

// Or specify explicitly
$reader = Reader::createForFile('export_utf16le.csv', null, Preset::UTF16LE);
```

### Network and Pipe Streams
When working with non-blocking or slow network streams, `fread` may return an empty string without reaching EOF. The `Stream` class handles this transparently with configurable retry logic.

```php
use Sorelvi\StreamReader\Stream;
use Sorelvi\StreamReader\Reader;
use Sorelvi\StreamReader\StreamCompletenessEstimatorFactory;
use Sorelvi\StreamReader\Enum\Preset;

$socket = fsockopen('tcp://example.com', 9000);

$stream = new Stream($socket);
$stream->setMaxEmptyAttempts(50);          // max retries on empty reads (default: 20)
$stream->setAttemptsDelayMicroseconds(5000); // delay between retries in µs (default: 10000)

$estimator = StreamCompletenessEstimatorFactory::create(Preset::UTF8);
$reader = new Reader($stream, $estimator);

foreach ($reader->readChunk() as $chunk) {
    // ...
}
```

If the stream returns empty data more times than `maxEmptyAttempts`, a `TooManyEmptyAttempts` exception is thrown.

### Custom Estimator
You can implement `EstimatorInterface` to support any custom encoding or fixed-width binary protocol.

```php
use Sorelvi\StreamReader\EstimatorInterface;
use Sorelvi\StreamReader\HandleContext;
use Sorelvi\StreamReader\Reader;

class MyProtocolEstimator implements EstimatorInterface
{
    public function handle(string $buffer, HandleContext $context): int
    {
        // Return 0 if the buffer ends on a complete unit,
        // or N > 0 to request N more bytes before yielding.
        $remainder = strlen($buffer) % 7; // 7-byte fixed records
        return $remainder ? 7 - $remainder : 0;
    }

    public function getMaxAddReadBytes(): int
    {
        return 6; // max bytes ever requested in one handle() call
    }
}

$reader = Reader::createForFile('records.bin', null, new MyProtocolEstimator());
```

### Custom Stream
You can implement `StreamInterface` to wrap any data source (e.g., in-memory buffers, S3 streams, database BLOBs).

```php
use Sorelvi\StreamReader\StreamInterface;

class MyCustomStream implements StreamInterface
{
    public function isEndOfStream(): bool { /* ... */ }
    public function read(int $length): string { /* ... */ }
    public function skip(int $offset): void { /* ... */ }
    public function getCurrentPosition(): int { /* ... */ }
}

$reader = new Reader(new MyCustomStream(), $estimator);
```

## Supported Presets
Use the `Sorelvi\StreamReader\Enum\Preset` enum to select your encoding:

| Preset | Description |
|--------|-------------|
| `Preset::UTF8` | Variable-width (1–4 bytes), bitwise scan. |
| `Preset::UTF16` | Variable-width (2 or 4 bytes), BOM auto-detect. |
| `Preset::UTF16LE` | UTF-16 Little Endian, explicit. |
| `Preset::UTF16BE` | UTF-16 Big Endian, explicit. |
| `Preset::UTF32` | Fixed 4-byte width. Equivalent to `Preset::BYTE4`. |
| `Preset::BYTE1` | Fixed 1-byte (ASCII, Windows-1251, ISO-8859-*, KOI8-R, etc.). |
| `Preset::BYTE2` | Fixed 2-byte width. |
| `Preset::BYTE3` | Fixed 3-byte width. |
| `Preset::BYTE4` | Fixed 4-byte width. |
| `Preset::BYTE5`–`Preset::BYTE10` | Fixed 5–10 byte widths for custom binary protocols. |

## Exception Reference
All library exceptions extend `Sorelvi\StreamReader\Exception\StreamReaderException`.

| Exception | Code | Thrown When |
|-----------|------|-------------|
| `FileNotAccessible` | 303–305 | File does not exist, is a directory, or is not readable. |
| `IsNotStream` | — | Constructor receives a non-resource argument. |
| `CanNotCreateStream` | — | `fopen()` fails internally. |
| `CanNotReadZeroBytes` | — | `Stream::read()` called with `$length < 1`. |
| `CanNotReadZeroChunk` | — | `Reader::readChunk()` called with `$chunkLength < 1`. |
| `ChunkLengthMustBePositive` | — | `Reader::setChunkLength()` called with value `< 1`. |
| `ErrorReadingFromStream` | 301, 302 | Stream became invalid or `fread()` returned `false`. |
| `TooManyEmptyAttempts` | — | Empty-read retry limit exceeded (network/pipe streams). |
| `StreamDamaged` | 101–107 | Invalid or incomplete byte sequence detected in stream. |
| `CanNotRestoreReadingStream` | 306 | Context byte offset cannot be seeked to in the stream. |
| `MaxAddReadMustBeZeroOrPositive` | — | Custom estimator returns a negative value from `getMaxAddReadBytes()`. |

### ErrorCode Enum
`Sorelvi\StreamReader\Exception\ErrorCode` provides integer codes for programmatic error handling:

```php
use Sorelvi\StreamReader\Exception\StreamDamaged;
use Sorelvi\StreamReader\Exception\ErrorCode;

try {
    foreach ($reader->readChunk() as $chunk) { /* ... */ }
} catch (StreamDamaged $e) {
    match ($e->getCode()) {
        ErrorCode::INCOMPLETE_SEQUENCE_AT_EOF->value  => handleTruncated(),
        ErrorCode::ORPHAN_CONTINUATION_BYTE->value    => handleCorrupted(),
        ErrorCode::INVALID_START_BYTE_DETECTED->value => handleInvalid(),
        default => throw $e,
    };
}
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please ensure to run static analysis tools (`composer check`) before submitting.

See [CONTRIBUTING.md](CONTRIBUTING.md) for full development workflow.

## License
Licensed under the [Apache License, Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).

Copyright 2026 Sorelvi.
