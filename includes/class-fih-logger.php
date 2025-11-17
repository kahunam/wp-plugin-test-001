<?php
/**
 * Logger Class.
 *
 * Handles debug logging for API requests and responses.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Logger Class.
 *
 * @since 1.0.0
 */
class FIH_Logger {

	/**
	 * Maximum number of log entries to keep.
	 *
	 * @var int
	 */
	private $max_logs = 3;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Schedule log cleanup.
		add_action( 'fih_cleanup_logs', array( $this, 'cleanup_old_logs' ) );

		if ( ! wp_next_scheduled( 'fih_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'fih_cleanup_logs' );
		}
	}

	/**
	 * Log a generation attempt.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Post ID.
	 * @param string $prompt Prompt used.
	 * @param string $status Status (success or error).
	 * @param string $error_message Error message if any.
	 * @param float  $generation_time Time taken in seconds.
	 * @return int|false Log ID on success, false on failure.
	 */
	public function log( $post_id, $prompt, $status, $error_message = '', $generation_time = 0 ) {
		global $wpdb;

		if ( ! get_option( 'fih_debug_logging_enabled', false ) ) {
			return false;
		}

		// Apply filter to log data.
		$log_data = apply_filters(
			'fih_log_entry',
			array(
				'post_id'          => absint( $post_id ),
				'prompt'           => sanitize_textarea_field( $prompt ),
				'response_status'  => sanitize_text_field( $status ),
				'error_message'    => sanitize_textarea_field( $error_message ),
				'generation_time'  => floatval( $generation_time ),
				'created_at'       => current_time( 'mysql' ),
			)
		);

		$table_name = $wpdb->prefix . 'fih_logs';

		$inserted = $wpdb->insert(
			$table_name,
			$log_data,
			array( '%d', '%s', '%s', '%s', '%f', '%s' )
		);

		if ( $inserted ) {
			// Keep only the last N logs.
			$this->limit_logs();
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Limit logs to maximum number of entries.
	 *
	 * @since 1.0.0
	 */
	private function limit_logs() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_logs';

		// Count total logs.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		if ( $count > $this->max_logs ) {
			// Delete oldest logs.
			$delete_count = $count - $this->max_logs;
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $table_name ORDER BY created_at ASC LIMIT %d",
					$delete_count
				)
			);
		}
	}

	/**
	 * Get recent logs.
	 *
	 * @since 1.0.0
	 * @param int $limit Number of logs to retrieve.
	 * @return array Array of log entries.
	 */
	public function get_logs( $limit = 3 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_logs';

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $logs ? $logs : array();
	}

	/**
	 * Clear all logs.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function clear_logs() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_logs';
		$result     = $wpdb->query( "TRUNCATE TABLE $table_name" );

		return false !== $result;
	}

	/**
	 * Cleanup old logs based on retention period.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$retention_days = get_option( 'fih_log_retention_days', 7 );
		$table_name     = $wpdb->prefix . 'fih_logs';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);
	}

	/**
	 * Get log entry by ID.
	 *
	 * @since 1.0.0
	 * @param int $log_id Log ID.
	 * @return array|null Log entry or null if not found.
	 */
	public function get_log( $log_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_logs';

		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$log_id
			),
			ARRAY_A
		);

		return $log;
	}

	/**
	 * Get logs for a specific post.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return array Array of log entries.
	 */
	public function get_logs_by_post( $post_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_logs';

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC",
				$post_id
			),
			ARRAY_A
		);

		return $logs ? $logs : array();
	}

	/**
	 * Log an API event (API key changes, API tests, etc.).
	 *
	 * @since 1.0.0
	 * @param string $event_type Type of API event (api_test, api_key_saved, etc.).
	 * @param string $message Event message.
	 * @param string $status Status (success, error, info).
	 * @return int|false Log ID on success, false on failure.
	 */
	public function log_api_event( $event_type, $message, $status = 'info' ) {
		global $wpdb;

		// Always log API events regardless of debug setting
		// This is important for troubleshooting API issues

		$table_name = $wpdb->prefix . 'fih_logs';

		$log_data = array(
			'post_id'          => 0, // API events are not post-specific
			'prompt'           => sanitize_text_field( $event_type ),
			'response_status'  => sanitize_text_field( $status ),
			'error_message'    => sanitize_textarea_field( $message ),
			'generation_time'  => 0,
			'created_at'       => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert(
			$table_name,
			$log_data,
			array( '%d', '%s', '%s', '%s', '%f', '%s' )
		);

		if ( $inserted ) {
			return $wpdb->insert_id;
		}

		return false;
	}
}
