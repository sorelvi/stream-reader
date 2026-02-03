# Sorelvi Stream Reader

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-777bb4.svg)
![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)

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
  * **Fixed-byte encodings** (ASCII, Windows-1251, ISO-8859-1 via `byte1` preset)
- **Resumable**: Architecture supports pausing and resuming reading via `HandleContext`.

## Requirements
* PHP 8.1 or higher

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
} catch (\Exception $e) {
    // Handles IO errors or corrupted streams explicitly
    error_log($e->getMessage());
}
```

### Reading from String
```php
use Sorelvi\StreamReader\Reader;
use Sorelvi\StreamReader\Enum\Preset;

$sql = "INSERT INTO ... 🚀"; // String with emojis
$reader = Reader::createForString($sql);

foreach ($reader->readChunk() as $chunk) {
    echo $chunk;
}
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
The HandleContext tracks the number of bytes read. You can persist this state to resume reading later (e.g., between HTTP requests or background jobs).

```php
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
$bytesRead = $context->toArray();
save_to_db($bytesRead);

// --- Later ---

// Step 3: Resume
$newContext = HandleContext::fromArray(load_from_db());

// The reader will automatically seek to the correct position
$reader = Reader::createForFile('large.log', $newContext, Preset::UTF16);
```

### Supporting UTF-16
The reader intelligently handles UTF-16 endianness.
1. Checks for Byte Order Mark (BOM).
1. Checks explicit encoding names (UTF-16LE, UTF-16BE).
1. Falls back to RFC 2781 standards (Big Endian) if unknown.
1. Correctly handles Surrogate Pairs (4-byte characters in UTF-16) to ensure they are not split.

```php
$reader = Reader::createForFile('export_utf16.csv', null, Preset::UTF16);
```

## Supported Presets
Use the Sorelvi\StreamReader\Enum\Preset enum to select your encoding:
- Preset::UTF8
- Preset::UTF16 (Auto-detect BOM)
- Preset::UTF16LE / Preset::UTF16BE
- Preset::UTF32
- Preset::byte1 (ASCII, Windows-1251, KOI8-R, etc.)
- Preset::byte2 ... Preset::byte10 (Fixed-width custom protocols)

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please ensure to run static analysis tools (`composer check`) before submitting.

## License
Licensed under the [Apache License, Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).

Copyright 2026 Sorelvi.

