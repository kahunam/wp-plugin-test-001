<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin data on uninstall.
 */
function fih_uninstall() {
	global $wpdb;

	// Delete custom tables.
	$queue_table = $wpdb->prefix . 'fih_queue';
	$logs_table  = $wpdb->prefix . 'fih_logs';

	$wpdb->query( "DROP TABLE IF EXISTS $queue_table" );
	$wpdb->query( "DROP TABLE IF EXISTS $logs_table" );

	// Delete all plugin options.
	$options = array(
		'fih_gemini_api_key',
		'fih_unsplash_api_key',
		'fih_pexels_api_key',
		'fih_default_prompt_style',
		'fih_content_source',
		'fih_default_image_size',
		'fih_custom_prompt_template',
		'fih_auto_generate_enabled',
		'fih_auto_generate_trigger',
		'fih_enabled_post_types',
		'fih_default_image_id',
		'fih_use_first_image',
		'fih_batch_size',
		'fih_queue_interval',
		'fih_log_retention_days',
		'fih_debug_logging_enabled',
		'fih_send_completion_email',
		'fih_queue_paused',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients.
	delete_transient( 'fih_queue_completion_notified' );

	// Clear scheduled cron jobs.
	$timestamp = wp_next_scheduled( 'fih_process_queue' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'fih_process_queue' );
	}

	$cleanup_timestamp = wp_next_scheduled( 'fih_cleanup_logs' );
	if ( $cleanup_timestamp ) {
		wp_unschedule_event( $cleanup_timestamp, 'fih_cleanup_logs' );
	}

	// Delete post meta created by the plugin.
	$wpdb->query(
		"DELETE FROM $wpdb->postmeta
		WHERE meta_key IN ('_fih_generated', '_fih_source', '_fih_generated_date')"
	);
}

// Run uninstall.
fih_uninstall();
