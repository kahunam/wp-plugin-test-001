# Featured Image Helper

WordPress plugin to identify, generate, and manage featured images using Google Gemini AI.

## Description

Featured Image Helper is a powerful WordPress plugin that helps you automatically generate and manage featured images for your posts using Google's Gemini AI. It identifies posts without featured images, generates high-quality images based on post content, and provides comprehensive management tools.

## Features

### Core Features

- **Dashboard Widget**: Display count of posts without featured images, breakdown by post type with quick action links
- **Admin List Screen**: Custom admin page under Media menu with filterable table by post type, category, and date range
- **Gemini AI Generation**: Generate images from post title, excerpt, or full content using various styles
- **Batch Processing**: Queue system using WordPress cron to process multiple images efficiently
- **Smart Suggestions**: Scan media library by post content keywords with optional Unsplash/Pexels integration
- **Bulk Actions**: Set featured images from library, generate with Gemini, apply default fallback image
- **Auto-Fill Rules**: Automatically generate featured images on post publish or update
- **SEO Enhancement**: Auto-generate alt text and image titles via Gemini
- **Debug Logging**: Store and view last 3 API requests with detailed information

### Prompt Styles

- **Photographic**: Professional, photorealistic images
- **Illustration**: Artistic, colorful illustrations
- **Abstract**: Modern abstract art
- **Minimal**: Clean, minimalist designs
- **Custom**: Define your own prompt template

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Google Gemini API key

## Installation

1. Upload the `featured-image-helper` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Featured Image Helper to configure your API keys
4. Start generating featured images!

## Configuration

### API Configuration

1. Go to Settings → Featured Image Helper → API Configuration
2. Enter your Google Gemini API key (get one from [Google AI Studio](https://makersuite.google.com/app/apikey))
3. Optionally add Unsplash or Pexels API keys for smart suggestions
4. Test your API connection

### Generation Settings

- Choose default prompt style
- Select content source (title, excerpt, or full content)
- Set default image size (default: 1200x630)
- Create custom prompt templates

### Auto-Fill Rules

- Enable/disable automatic generation
- Set trigger (manual, on publish, on update)
- Select which post types to enable

### Advanced Settings

- Configure batch size (1-20 images per batch)
- Set queue processing interval
- Configure log retention period
- Enable/disable debug logging

## Usage

### Generate Single Image

1. Go to Media → Featured Images
2. Find a post without a featured image
3. Click "Generate Now"
4. Wait for the AI to create your image

### Bulk Generation

1. Go to Media → Featured Images
2. Select multiple posts using checkboxes
3. Click "Add Selected to Queue"
4. Images will be processed automatically based on your queue settings

### Auto-Generation

1. Enable auto-generation in Settings → Featured Image Helper → Auto-Fill Rules
2. Select trigger (publish or update)
3. Choose enabled post types
4. New posts will automatically get featured images

## Queue System

The plugin uses WordPress cron to process images in batches:

- Configurable batch size (default: 5 images)
- Configurable interval (default: every 5 minutes)
- Pause/resume functionality
- Progress tracking
- Email notifications on completion

## Debug Logging

When enabled, the plugin stores:

- Last 3 API requests
- Timestamp and post information
- Prompt used
- API response status
- Error messages
- Generation time

Logs are automatically purged after 7 days (configurable).

## Hooks & Filters

### Actions

- `fih_before_generate_image` - Fires before image generation
- `fih_after_generate_image` - Fires after successful generation

### Filters

- `fih_prompt_template` - Modify the prompt before sending to API
- `fih_image_generation_args` - Modify API request arguments
- `fih_batch_size` - Change batch processing size
- `fih_queue_interval` - Change queue processing interval
- `fih_log_entry` - Filter log data before saving

## Security

- All forms use WordPress nonces
- Capability checks on all admin pages (`manage_options` for settings, `edit_posts` for management)
- API keys are encrypted using WordPress salts
- Rate limiting on generation requests
- All inputs sanitized and outputs escaped
- Prepared SQL queries

## Performance

- Lazy load admin assets only on plugin pages
- Use transients for API responses (1 hour TTL)
- Async processing for batch operations
- Debounced AJAX requests
- Limited log storage
- Auto-cleanup of old logs

## Database Tables

The plugin creates two custom tables:

- `wp_fih_queue` - Stores batch processing queue
- `wp_fih_logs` - Stores debug logs

These tables are automatically removed on plugin uninstall.

## Support

For bug reports and feature requests, please visit [GitHub Issues](https://github.com/kahunam/featured-image-helper/issues).

## Changelog

### 1.0.0
- Initial release
- Core image generation functionality
- Queue system for batch processing
- Dashboard widget
- Settings page with multiple tabs
- Debug logging system
- Auto-generation rules
- Multiple prompt styles
- SEO enhancements

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Your Name]
Uses Google Gemini AI for image generation
