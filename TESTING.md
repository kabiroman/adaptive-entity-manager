# Testing Guide

## Available Test Commands

This project includes several composer scripts for different testing scenarios:

### Basic Commands

```bash
# Run all tests
composer test

# Check PHP syntax in all files
composer test:syntax

# Quick test: syntax check + all tests
composer test:quick
```

### Test Suites

```bash
# Run only unit tests
composer test:unit

# Run only integration tests  
composer test:integration

# Run only metadata tests (our new caching system)
composer test -- --testsuite metadata
```

### Coverage Reports

```bash
# Generate HTML coverage report
composer test:coverage

# Generate unit test coverage
composer test:unit:coverage
```

### Utility Commands

```bash
# Install development dependencies
composer install:dev

# Clear cache directories
composer cache:clear
```

## Test Structure

- `tests/Unit/` - Unit tests
- `tests/Integration/` - Integration tests  
- `tests/Metadata/` - Metadata caching system tests
- `tests/Mock/` - Mock objects and test fixtures

## Development Workflow

1. **Before committing changes:**
   ```bash
   composer test:syntax
   composer test -- --testsuite metadata
   ```

2. **Full test run:**
   ```bash
   composer test
   ```

3. **Generate coverage:**
   ```bash
   composer test:coverage
   # Open coverage/index.html in browser
   ```

## New Metadata Caching Tests

Our new caching system tests can be run specifically with:

```bash
composer test -- --testsuite metadata
```

These tests verify:
- Multi-level caching (runtime + persistent)
- Cache invalidation
- PSR-6 compatibility
- Performance optimizations

## CI/CD Integration

For continuous integration, use:

```bash
composer test:syntax && composer test
```

This ensures both syntax correctness and functional tests pass. 