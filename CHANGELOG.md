# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-18

### Added
- Initial release of the **StreamReader** library.
- **Core Features:**
    - `Reader` class for memory-efficient binary stream reading via Generators.
    - `Stream` class wrapping PHP resources with retry logic for network/pipe streams.
    - `HandleContext` for tracking and persisting reader state across sessions.
    - `StreamCompletenessEstimatorFactory` for creating encoding-specific estimators via `Preset` enum.
- **Encoding Support:**
    - `Utf8` estimator — variable-width (1–4 bytes), bitwise boundary detection, strict validation of start/continuation bytes.
    - `Utf16` estimator — variable-width (2 or 4 bytes), surrogate pair awareness, BOM-based endianness auto-detection (RFC 2781), explicit LE/BE modes.
    - `FixedByte` estimator — covers UTF-32 and fixed-width custom protocols (`Preset::BYTE1` through `Preset::BYTE10`).
- **Extensibility:**
    - `EstimatorInterface` for implementing custom encoding estimators.
    - `StreamInterface` for wrapping custom data sources.
- **Exception Hierarchy:**
    - Base `StreamReaderException` with typed sub-classes and `ErrorCode` enum for programmatic error handling.
- **Quality Assurance:**
    - Static Analysis via PHPStan (Level 9).
    - Mutation Testing via Infection (100% MSI target).
    - Code Style enforcement via Slevomat Coding Standard (PSR-12 compliant).
    - Mess Detection via PHPMD.
    - Comprehensive unit tests covering edge cases, encoding boundaries, and network stream retry logic.
- **Infrastructure:**
    - GitHub Actions Workflow for CI (Tests, Linting, Static Analysis).