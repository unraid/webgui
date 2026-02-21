# Unraid WebGUI Test Suite

This directory contains tests for the Unraid WebGUI codebase.

## Test Organization

```
tests/
├── Unit/               # Unit tests for individual classes and functions
├── Integration/        # Integration tests for components working together
├── scripts/            # Tests for shell scripts
├── bootstrap.php       # PHPUnit bootstrap file
└── README.md          # This file
```

## Running Tests

### Prerequisites

1. **PHP 7.4 or higher**
2. **Composer** - for PHP dependency management
   ```bash
   curl -sS https://getcomposer.org/installer | php
   ```

3. **Bats** (optional, for shell script tests)
   ```bash
   # On Ubuntu/Debian
   sudo apt-get install bats

   # On macOS
   brew install bats-core

   # Or install from source
   git clone https://github.com/bats-core/bats-core.git
   cd bats-core
   ./install.sh /usr/local
   ```

### Install Dependencies

```bash
composer install --dev
```

### Run All Tests

```bash
./run-tests.sh
```

### Run Only PHP Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Run only unit tests
vendor/bin/phpunit --testsuite "Unit Tests"

# Run only integration tests
vendor/bin/phpunit --testsuite "Integration Tests"

# Run a specific test file
vendor/bin/phpunit tests/Unit/DockerUtilTest.php

# Run a specific test method
vendor/bin/phpunit --filter testEnsureImageTagAddsLatestWhenMissing
```

### Run Only Shell Script Tests

```bash
bats tests/scripts/*.bats
```

### Run with Coverage (requires Xdebug)

```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Test Coverage

### PHP Files Covered

- **DockerClient.php** - Docker API client and utilities
  - `DockerUtil` class - Image parsing, JSON handling
  - `DockerClient` class - Container/image management, formatting utilities
  - `DockerTemplates` class - Template management (partial)

### Shell Scripts Covered

- **generate-pr-plugin.sh** - PR plugin generation script
  - Parameter validation
  - Plugin file generation
  - SHA256 calculation
  - XML structure validation
  - macOS/Linux compatibility

### Workflow Files Covered

- **pr-plugin-build.yml** - GitHub Actions workflow validation
  - YAML syntax validation
  - Required structure checks
  - Permission verification
  - Action version validation

## Writing New Tests

### Unit Tests

Create a new test file in `tests/Unit/`:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase
{
    public function testSomething()
    {
        $this->assertTrue(true);
    }
}
```

### Shell Script Tests

Create a new test file in `tests/scripts/`:

```bash
#!/usr/bin/env bats

@test "my test description" {
    run my_command
    [ "$status" -eq 0 ]
    [[ "$output" =~ "expected output" ]]
}
```

## Continuous Integration

These tests can be integrated into CI/CD pipelines:

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install dependencies
        run: composer install --dev
      - name: Run tests
        run: vendor/bin/phpunit
```

## Notes

### Current Limitations

1. **No Docker daemon in test environment** - Tests that require actual Docker API calls are mocked
2. **No libvirt in test environment** - VM-related functionality tests are limited
3. **No database** - Tests use mock data instead of real database queries
4. **Partial coverage** - Focus is on business logic and utilities; UI components have limited testing

### Test Philosophy

These tests focus on:
- Business logic and algorithms
- Data transformations and parsing
- Input validation and error handling
- Edge cases and boundary conditions
- Regression prevention

### Future Improvements

- Add mock Docker daemon for integration tests
- Add API endpoint tests
- Increase coverage of Docker template operations
- Add performance/benchmark tests
- Add security/penetration tests

## Troubleshooting

### "Class not found" errors

Make sure you've run `composer install --dev` and the autoloader is working.

### YAML parsing errors in workflow tests

Install the YAML PHP extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-yaml

# macOS
pecl install yaml
```

### Bats tests not running

Make sure bats is executable:
```bash
chmod +x tests/scripts/*.bats
```

## Contributing

When adding new functionality to the codebase:

1. Write tests first (TDD approach preferred)
2. Ensure all existing tests pass
3. Aim for >80% code coverage for new code
4. Document any test-specific configuration needs
5. Update this README if adding new test categories

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Bats Documentation](https://bats-core.readthedocs.io/)
- [Testing Best Practices](https://github.com/goldbergyoni/javascript-testing-best-practices)