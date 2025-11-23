<?php
/**
 * Plugin Name: Featured Image Helper
 * Plugin URI: https://github.com/kahunam/featured-image-helper
 * Description: Identify, generate, and manage featured images using Gemini AI
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: featured-image-helper
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Featured_Image_Helper
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'FIH_VERSION', '1.0.0' );
define( 'FIH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FIH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FIH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load Composer autoloader if available.
 */
if ( file_exists( FIH_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once FIH_PLUGIN_DIR . 'vendor/autoload.php';

	// Load environment variables from .env file if it exists
	if ( class_exists( 'Dotenv\Dotenv' ) && file_exists( FIH_PLUGIN_DIR . '.env' ) ) {
		try {
			$dotenv = Dotenv\Dotenv::createImmutable( FIH_PLUGIN_DIR );
			$dotenv->load();
		} catch ( Exception $e ) {
			// Silently fail if .env file is malformed
			error_log( 'Featured Image Helper: Failed to load .env file - ' . $e->getMessage() );
		}
	}
}

/**
 * The code that runs during plugin activation.
 */
function fih_activate_plugin() {
	require_once FIH_PLUGIN_DIR . 'includes/class-fih-activator.php';
	FIH_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function fih_deactivate_plugin() {
	require_once FIH_PLUGIN_DIR . 'includes/class-fih-activator.php';
	FIH_Activator::deactivate();
}

register_activation_hook( __FILE__, 'fih_activate_plugin' );
register_deactivation_hook( __FILE__, 'fih_deactivate_plugin' );

/**
 * Load plugin text domain for translations.
 */
function fih_load_textdomain() {
	load_plugin_textdomain(
		'featured-image-helper',
		false,
		dirname( FIH_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'fih_load_textdomain' );

/**
 * Require the main plugin class files.
 */
require_once FIH_PLUGIN_DIR . 'includes/class-fih-core.php';
require_once FIH_PLUGIN_DIR . 'includes/class-fih-gemini.php';
require_once FIH_PLUGIN_DIR . 'includes/class-fih-admin.php';
require_once FIH_PLUGIN_DIR . 'includes/class-fih-settings.php';
require_once FIH_PLUGIN_DIR . 'includes/class-fih-queue.php';
require_once FIH_PLUGIN_DIR . 'includes/class-fih-logger.php';

/**
 * Begin execution of the plugin.
 */
function fih_run_plugin() {
	$plugin = FIH_Core::get_instance();
	$plugin->run();
}
fih_run_plugin();
