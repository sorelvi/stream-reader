# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-18

### Added
- Initial release of the **StreamReader** library.
- **Core Features:**
    - `Reader` class for memory-efficient binary stream reading via Generators.
    - `Utf8` estimator logic to prevent breaking multi-byte characters during chunk reading.
    - Support for custom chunk sizes.
- **Quality Assurance:**
    - Static Analysis via PHPStan (Level 9).
    - Mutation Testing via Infection (100% MSI target).
    - Code Style enforcement via Slevomat Coding Standard (PSR-12 compliant).
    - Comprehensive unit tests covering edge cases.
- **Infrastructure:**
    - GitHub Actions Workflow for CI (Tests, Linting, Static Analysis).
    - Dependabot configuration for automated dependency updates.
