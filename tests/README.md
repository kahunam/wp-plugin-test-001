# Featured Image Helper Tests

This directory contains unit and integration tests for the Featured Image Helper plugin.

## Setup

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Create your `.env` file:**
   ```bash
   cp .env.example .env
   ```

3. **Add your API key to `.env`:**
   ```
   GEMINI_API_KEY=your_actual_api_key_here
   ```

## Running Tests

### Run all tests (excluding integration tests)
```bash
composer test
```

Or using PHPUnit directly:
```bash
vendor/bin/phpunit
```

### Run only integration tests
```bash
vendor/bin/phpunit --group integration
```

### Run expensive tests (actual API calls)
These tests are skipped by default to avoid API costs. To run them:
```bash
vendor/bin/phpunit --group expensive
```

Note: You'll need to remove the `markTestSkipped()` line in the test method.

### Run with code coverage
```bash
composer test:coverage
```

Coverage report will be generated in `tests/coverage/index.html`

### Run specific test file
```bash
vendor/bin/phpunit tests/GeminiApiTest.php
```

### Run specific test method
```bash
vendor/bin/phpunit --filter test_aspect_ratio_conversion
```

### Verbose output
```bash
vendor/bin/phpunit --verbose
```

## Test Structure

### Unit Tests (`GeminiApiTest.php`)
- Tests internal methods without making API calls
- Fast and safe to run frequently
- Includes tests for:
  - Aspect ratio conversion
  - GCD calculation
  - Prompt building
  - API request structure
  - Template validation

### Integration Tests (`GeminiApiIntegrationTest.php`)
- Makes actual API calls to Gemini
- Requires valid API key in `.env`
- Marked with `@group integration`
- Includes tests for:
  - API connection verification
  - Real image generation (expensive, skipped by default)
  - Request/response handling

## Test Data

The tests use mock WordPress functions defined in `bootstrap.php`. This allows testing without a full WordPress installation.

### Mock Post Structure
```php
$post = new stdClass();
$post->post_title = 'Test Post Title';
$post->post_excerpt = 'Test excerpt';
$post->post_content = 'Test content';
```

## Writing New Tests

1. Create a new test file in the `tests/` directory
2. Extend `PHPUnit\Framework\TestCase`
3. Use namespace `FIH\Tests`
4. Name test methods with `test_` prefix
5. Use data providers for testing multiple inputs

Example:
```php
<?php
namespace FIH\Tests;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase {
    public function test_something() {
        $this->assertTrue(true);
    }
}
```

## Debugging Tests

### Print output during tests
```php
echo "\nDebug info: " . $variable . "\n";
```

### Use `--debug` flag
```bash
vendor/bin/phpunit --debug
```

### Stop on first failure
```bash
vendor/bin/phpunit --stop-on-failure
```

## Environment Variables

The following environment variables can be set in `.env`:

| Variable | Required | Description |
|----------|----------|-------------|
| `GEMINI_API_KEY` | Yes | Your Google Gemini API key |
| `TEST_POST_ID` | No | Post ID for testing (default: 1) |
| `TEST_POST_TITLE` | No | Test post title |

## Troubleshooting

### "GEMINI_API_KEY not set"
Make sure you've created a `.env` file and added your API key.

### "Class not found"
Run `composer install` to install dependencies.

### "Call to undefined function wp_remote_post"
This is expected for unit tests. Integration tests mock this function.

### API rate limits
If you're hitting rate limits, reduce the number of integration tests or add delays between tests.
