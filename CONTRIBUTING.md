# Contributing to StreamReader

First off, thank you for considering contributing to StreamReader! We value your time and effort.

This library follows strict quality standards to ensure reliability, type safety, and maintainability. Please read this guide to understand our development workflow.

## Development Setup

### 1. Requirements

PHP 8.1 or higher

Composer

PCOV or Xdebug (for coverage and mutation testing)

### 2. Installation Run the following commands to setup the project:

``` git clone https://github.com/sorelvi/stream-reader.git cd stream-reader composer install ```

## Code Quality Standards

We use a "Zero Tolerance" policy for static analysis errors and coding style violations. Our CI pipeline will fail if any of these checks do not pass.

### Static Analysis (PHPStan) We use PHPStan at Level 9 (max).

No mixed types allowed where avoidable.

All array shapes must be documented.

Strict type checking is enabled.

### Coding Style (Slevomat + PSR-12) We use a custom ruleset based on Slevomat Coding Standard.

Do not fix style manually. We have automated tools for that.

Run composer fix before committing.

### Mutation Testing (Infection) We don't just check code coverage; we check test quality.

We aim for 100% MSI (Mutation Score Indicator).

If you add logic, you must add tests that "kill" any mutations of that logic.

## Workflow & Commands

We have defined convenient Composer scripts to help you.

```bash 
composer cs-fix
```
Runs Rector and PHPCBF to auto-fix code style. Frequently, before running tests.

```bash 
composer test
```
Runs PHPUnit tests. After every change.

```bash 
composer check
```
Runs All Checks (CS, Stan, Mess Detector, Tests). Must pass before pushing.

```bash
composer infection
```
Runs Mutation Testing. Before creating a Pull Request.  

## Pull Request Checklist

Before submitting a Pull Request, please ensure the following:

* [ ] You have added tests for any new functionality.

* [ ] Mutation Score is 100% (or at least not lower than master). Run vendor/bin/infection to check.

* [ ] You have run composer fix to ensure code style consistency.

* [ ] composer check passes locally without errors.

* [ ] You have updated the README.md if API changes were made.

## Reporting Bugs

If you find a bug, please create an issue with:

1. A minimal reproducible example.

1. The version of the library and PHP you are using.

1. Expected vs. Actual behavior.

Thank you for helping us build robust software!
