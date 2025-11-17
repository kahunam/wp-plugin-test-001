<?php
/**
 * Fired during plugin activation and deactivation.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin activation and deactivation.
 *
 * This class defines all code necessary to run during the plugin's activation and deactivation.
 *
 * @since 1.0.0
 */
class FIH_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Create custom database tables and set default options.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create queue table.
		$queue_table_name = $wpdb->prefix . 'fih_queue';
		$queue_sql        = "CREATE TABLE IF NOT EXISTS $queue_table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			priority int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY priority (priority)
		) $charset_collate;";

		// Create logs table.
		$logs_table_name = $wpdb->prefix . 'fih_logs';
		$logs_sql        = "CREATE TABLE IF NOT EXISTS $logs_table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) DEFAULT NULL,
			prompt text,
			response_status varchar(50),
			error_message text,
			generation_time float,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $queue_sql );
		dbDelta( $logs_sql );

		// Set default options.
		$default_options = array(
			'fih_default_prompt_style'    => 'photographic',
			'fih_auto_generate_enabled'   => false,
			'fih_debug_logging_enabled'   => false,
			'fih_batch_size'              => 5,
			'fih_queue_interval'          => 5,
			'fih_log_retention_days'      => 7,
			'fih_default_image_size'      => '1200x630',
			'fih_auto_generate_trigger'   => 'manual',
			'fih_enabled_post_types'      => array( 'post' ),
		);

		foreach ( $default_options as $option_name => $option_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $option_value );
			}
		}

		// Schedule cron job for queue processing.
		if ( ! wp_next_scheduled( 'fih_process_queue' ) ) {
			wp_schedule_event( time(), 'fih_queue_interval', 'fih_process_queue' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 *
	 * Clear scheduled cron jobs.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear scheduled cron jobs.
		$timestamp = wp_next_scheduled( 'fih_process_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'fih_process_queue' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
