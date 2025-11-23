# Building the Plugin

The Featured Image Helper plugin uses WordPress Gutenberg components for its admin interface. This requires building the React assets before the plugin can be used.

## Prerequisites

- Node.js 18+ and npm
- PHP 7.4+ with Composer

## Installation

### 1. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### 2. Build Assets

```bash
# Production build (minified)
npm run build

# Development build with watch mode
npm run start
```

## Development Workflow

1. **Start watch mode** for automatic rebuilding during development:
   ```bash
   npm run start
   ```

2. **Make changes** to React components in `src/admin/`

3. **Test in WordPress** - refresh the settings page to see changes

4. **Build for production** when ready to deploy:
   ```bash
   npm run build
   ```

## File Structure

```
/src/admin/          # React source files
  settings.jsx       # Settings page React component
/build/              # Compiled assets (generated, not in git)
  settings.js        # Built JavaScript
  settings.css       # Built styles
  settings.asset.php # WordPress dependency manifest
```

## WordPress Components Used

The plugin uses the following `@wordpress/components`:

- **Panel, PanelBody, PanelRow** - Collapsible settings panels
- **TextControl** - Text input fields
- **SelectControl** - Dropdown selects
- **ToggleControl** - Toggle switches
- **Button** - Action buttons
- **Notice** - Success/error messages
- **Spinner** - Loading indicators

Documentation: https://developer.wordpress.org/block-editor/reference-guides/components/

## Troubleshooting

### Build fails with "Module not found"

Run `npm install` to ensure all dependencies are installed.

### Settings page is blank

1. Check that `build/` directory exists and contains `settings.js`
2. Run `npm run build` to generate assets
3. Check browser console for JavaScript errors

### Changes not appearing

1. Clear browser cache
2. Check that `npm run start` is running for development
3. Verify file permissions on `/build/` directory
