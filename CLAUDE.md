# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Featured Image Helper is a WordPress plugin that automatically generates and manages featured images for posts using Google's Gemini AI (specifically Gemini 2.5 Flash with image generation capabilities, also known as "nano banana").

## Development Commands

### Installation

```bash
# Symlink the plugin directory to your WordPress installation
ln -s /path/to/featured-image-helper /path/to/wordpress/wp-content/plugins/

# Or copy files directly to your WordPress plugins directory
cp -r . /path/to/wordpress/wp-content/plugins/featured-image-helper/

# Install Composer dependencies
composer install
```

### Testing

```bash
# Run all unit tests
composer test

# Run integration tests (requires GEMINI_API_KEY in .env)
vendor/bin/phpunit --group integration

# Run with code coverage
composer test:coverage

# Run specific test file
vendor/bin/phpunit tests/GeminiApiTest.php
```

### Environment Setup

The plugin supports loading configuration from a `.env` file:

1. Copy `.env.example` to `.env`
2. Add your `GEMINI_API_KEY`
3. The plugin will automatically load these values if Composer autoloader is available

Environment variables take precedence over WordPress options for API keys.

## Architecture

### Core Plugin Structure

The plugin follows WordPress plugin development standards with a singleton pattern for the main core class:

- **Entry Point**: `featured-image-helper.php` - Main plugin file that defines constants, registers activation/deactivation hooks, and initializes the core
- **Core Singleton**: `FIH_Core` (includes/class-fih-core.php) - Central orchestrator that initializes all subsystems via the WordPress `init` hook

### Class System

All classes follow the `FIH_` prefix convention and are initialized in a specific order:

1. **FIH_Logger** - Must be initialized first as other classes depend on it for logging
2. **FIH_Gemini** - Handles all Gemini AI API interactions
3. **FIH_Queue** - Manages batch processing queue
4. **FIH_Settings** - Handles settings registration (not heavily used, most settings handled in FIH_Admin)
5. **FIH_Admin** - Only initialized in admin context (`is_admin()`), handles all UI and AJAX

### Image Generation Flow

**Single Image Generation:**
1. User clicks "Generate" button → AJAX request to `fih_generate_single_image`
2. `FIH_Admin::ajax_generate_single_image()` validates permissions and post ID
3. Calls `FIH_Gemini::generate_image()` which:
   - Builds prompt from post content using configured style templates
   - Makes API request to Gemini 2.5 Flash endpoint with retry logic (up to 3 attempts with exponential backoff)
   - Processes base64-encoded image from `candidates[0].content.parts[].inline_data.data`
   - Saves image to WordPress media library via `wp_upload_bits()`
   - Sets as featured image using `set_post_thumbnail()`
   - Generates alt text and metadata
   - Logs attempt if debug logging enabled

**Batch Queue Processing:**
1. Posts added to queue via `FIH_Queue::add_to_queue()` (stored in `wp_fih_queue` table)
2. WordPress cron (`fih_process_queue`) runs at configured interval (default: 5 minutes)
3. `FIH_Queue::process_queue()` retrieves pending items ordered by priority
4. Processes batch (default: 5 images) using same generation flow
5. Updates queue item status (pending → processing → completed/failed)
6. Sends email notification when all items processed

### Database Schema

Two custom tables created on activation (includes/class-fih-activator.php):

- **wp_fih_queue**: Stores batch processing queue with columns: id, post_id, status, priority, created_at, processed_at
- **wp_fih_logs**: Stores debug logs with columns: id, post_id, prompt, response_status, error_message, generation_time, created_at

Tables are dropped on uninstall (uninstall.php).

### Gemini API Integration

**API Endpoint**: Uses Gemini 2.5 Flash image generation endpoint (`gemini-2.5-flash-image:generateContent`)

**Request Format** (class-fih-gemini.php:192):
```php
array(
    'contents' => array(
        array('parts' => array(array('text' => $prompt)))
    ),
    'generationConfig' => array(
        'responseModalities' => array('Image'),
        'imageConfig' => array('aspectRatio' => '16:9') // Supports: 1:1, 3:4, 4:3, 9:16, 16:9
    )
)
```

**Response Format**: Image data is in `candidates[0].content.parts[].inline_data` with base64-encoded `data` and `mime_type` fields

**Retry Logic**: Automatically retries failed requests up to 3 times with exponential backoff (2^retry_count seconds)

**Aspect Ratio Conversion**: The plugin converts WIDTHxHEIGHT format (e.g., "1200x630") to simplified aspect ratios, mapping to closest supported ratio (convert_size_to_aspect_ratio in class-fih-gemini.php:228)

### Settings and Options

Settings are stored as WordPress options with `fih_` prefix. All settings handling happens in `FIH_Admin::save_settings()` with form-based saves (no WordPress Settings API used).

**Critical Settings:**
- `fih_gemini_api_key` - Stored in plain text (previously had XOR encryption, now removed per git history)
- `fih_default_prompt_style` - One of: photographic, illustration, abstract, minimal
- `fih_content_source` - Determines prompt source: title, excerpt, or content (first 100 words)
- `fih_batch_size` - Images per batch (1-20, default: 5)
- `fih_queue_interval` - Cron interval in minutes (1-60, default: 5)

### Auto-Generation Hooks

Auto-generation setup in `FIH_Admin::setup_auto_generation()`:
- `publish_post` hook for "on publish" trigger
- `save_post` hook for "on update" trigger
- Only applies to enabled post types (default: 'post')
- Skips if post already has featured image
- Adds to queue rather than generating immediately

### AJAX Handlers

All AJAX actions use `wp_ajax_` prefix and are registered in `FIH_Admin::__construct()`:
- All use `fih_ajax_nonce` for security
- Capability checks: `edit_posts` for generation actions, `manage_options` for settings
- Return JSON via `wp_send_json_success()` / `wp_send_json_error()`

### Prompt Templates

Prompt templates use the **SPLICE rubric** for structured, consistent image generation:
- **S** – Style and medium
- **P** – Perspective and composition
- **L** – Lighting and atmosphere
- **I** – Identity of the subject (replaced with {content})
- **C** – Cultural and contextual details
- **E** – Emotion and energy

Four built-in styles (class-fih-gemini.php:41):

**photographic**: Professional photorealistic photographs with natural lighting, centered composition, modern professional setting

**illustration**: Digital illustrations with artistic rendering, dynamic composition, vibrant lighting, creative visual elements

**abstract**: Modern abstract art with bold shapes, dramatic lighting, strong contrast, conceptual interpretation

**minimal**: Clean minimalist design with geometric forms, symmetrical composition, soft lighting, negative space

All templates include "Rules: No text, no words, no letters, no captions" to prevent unwanted text in images.

Prompts are filterable via `fih_prompt_template` filter.

### WordPress Integration Points

- **Admin Menu**: Adds top-level menu "Featured Images" with Dashboard and Settings subpages
- **Dashboard Widget**: Shows posts without featured images by post type
- **Media Library Integration**: Generated images marked with `_fih_generated`, `_fih_source`, and `_fih_generated_date` meta keys
- **Cron**: Custom interval `fih_queue_interval` added via `cron_schedules` filter
- **Translation Ready**: Uses `featured-image-helper` text domain

### Assets Loading

Assets conditionally loaded only on plugin pages (class-fih-admin.php:277):
- Checks for specific admin page hooks before enqueueing
- `admin.css` and `admin.js` from assets/ directory
- JavaScript localized with AJAX URL, nonces, and translated strings
- WordPress media library enqueued for image selection UI

## Important Notes

- API keys are stored in plain text in wp_options (encryption was removed)
- The plugin expects Gemini 2.5 Flash API access for image generation
- Queue processing requires WordPress cron to be functional
- Generated images use the post slug as filename prefix with timestamp
- All database queries use `$wpdb->prepare()` for SQL injection prevention
- All form submissions use WordPress nonces for CSRF protection
