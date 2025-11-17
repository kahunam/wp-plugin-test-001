<?php
/**
 * Queue System Class.
 *
 * Handles batch processing of featured image generation.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Queue System Class.
 *
 * @since 1.0.0
 */
class FIH_Queue {

	/**
	 * Queue status constants.
	 */
	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_COMPLETED  = 'completed';
	const STATUS_FAILED     = 'failed';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'fih_process_queue', array( $this, 'process_queue' ) );
	}

	/**
	 * Add post to queue.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID to add to queue.
	 * @param int $priority Priority (higher = processed first).
	 * @return int|false Queue item ID on success, false on failure.
	 */
	public function add_to_queue( $post_id, $priority = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';

		// Check if already in queue.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE post_id = %d AND status IN (%s, %s)",
				$post_id,
				self::STATUS_PENDING,
				self::STATUS_PROCESSING
			)
		);

		if ( $exists ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'post_id'    => absint( $post_id ),
				'status'     => self::STATUS_PENDING,
				'priority'   => absint( $priority ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Add multiple posts to queue.
	 *
	 * @since 1.0.0
	 * @param array $post_ids Array of post IDs.
	 * @param int   $priority Priority for all posts.
	 * @return int Number of posts added.
	 */
	public function add_bulk_to_queue( $post_ids, $priority = 0 ) {
		$added = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $this->add_to_queue( $post_id, $priority ) ) {
				$added++;
			}
		}

		return $added;
	}

	/**
	 * Process queue items.
	 *
	 * @since 1.0.0
	 */
	public function process_queue() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';
		$batch_size = apply_filters( 'fih_batch_size', get_option( 'fih_batch_size', 5 ) );

		// Get pending items ordered by priority.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE status = %s ORDER BY priority DESC, created_at ASC LIMIT %d",
				self::STATUS_PENDING,
				$batch_size
			)
		);

		if ( empty( $items ) ) {
			return;
		}

		$gemini = FIH_Core::get_instance()->get_gemini();

		foreach ( $items as $item ) {
			// Update status to processing.
			$this->update_status( $item->id, self::STATUS_PROCESSING );

			// Get default style.
			$style = get_option( 'fih_default_prompt_style', 'photographic' );

			// Generate image.
			$result = $gemini->generate_image( $item->post_id, $style );

			// Update status based on result.
			if ( is_wp_error( $result ) ) {
				$this->update_status( $item->id, self::STATUS_FAILED );
			} else {
				$this->update_status( $item->id, self::STATUS_COMPLETED );
			}
		}

		// Check if all items are processed and send notification.
		$this->check_completion_notification();
	}

	/**
	 * Update queue item status.
	 *
	 * @since 1.0.0
	 * @param int    $item_id Queue item ID.
	 * @param string $status New status.
	 * @return bool True on success, false on failure.
	 */
	public function update_status( $item_id, $status ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'       => $status,
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get queue statistics.
	 *
	 * @since 1.0.0
	 * @return array Queue statistics.
	 */
	public function get_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';

		$stats = array(
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
			'total'      => 0,
		);

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
			ARRAY_A
		);

		foreach ( $results as $row ) {
			$stats[ $row['status'] ] = absint( $row['count'] );
			$stats['total']         += absint( $row['count'] );
		}

		return $stats;
	}

	/**
	 * Clear completed and failed items.
	 *
	 * @since 1.0.0
	 * @return int Number of items cleared.
	 */
	public function clear_processed() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE status IN (%s, %s)",
				self::STATUS_COMPLETED,
				self::STATUS_FAILED
			)
		);

		return $deleted;
	}

	/**
	 * Pause queue processing.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public function pause() {
		update_option( 'fih_queue_paused', true );
		return true;
	}

	/**
	 * Resume queue processing.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public function resume() {
		update_option( 'fih_queue_paused', false );
		return true;
	}

	/**
	 * Check if queue is paused.
	 *
	 * @since 1.0.0
	 * @return bool True if paused.
	 */
	public function is_paused() {
		return (bool) get_option( 'fih_queue_paused', false );
	}

	/**
	 * Get pending queue items.
	 *
	 * @since 1.0.0
	 * @param int $limit Number of items to retrieve.
	 * @return array Array of queue items.
	 */
	public function get_pending_items( $limit = 100 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE status = %s ORDER BY priority DESC, created_at ASC LIMIT %d",
				self::STATUS_PENDING,
				$limit
			)
		);

		return $items ? $items : array();
	}

	/**
	 * Remove item from queue.
	 *
	 * @since 1.0.0
	 * @param int $item_id Queue item ID.
	 * @return bool True on success.
	 */
	public function remove_item( $item_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';

		$deleted = $wpdb->delete(
			$table_name,
			array( 'id' => $item_id ),
			array( '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Clear entire queue.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public function clear_queue() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fih_queue';
		$result     = $wpdb->query( "TRUNCATE TABLE $table_name" );

		return false !== $result;
	}

	/**
	 * Check if queue is complete and send notification.
	 *
	 * @since 1.0.0
	 */
	private function check_completion_notification() {
		$stats = $this->get_stats();

		// If no pending or processing items, send notification.
		if ( 0 === $stats['pending'] && 0 === $stats['processing'] && $stats['total'] > 0 ) {
			$this->send_completion_notification( $stats );
		}
	}

	/**
	 * Send completion notification email.
	 *
	 * @since 1.0.0
	 * @param array $stats Queue statistics.
	 */
	private function send_completion_notification( $stats ) {
		// Check if notifications are enabled.
		if ( ! get_option( 'fih_send_completion_email', true ) ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$subject     = __( 'Featured Image Generation Complete', 'featured-image-helper' );

		$message = sprintf(
			/* translators: 1: completed count, 2: failed count */
			__( "Featured image generation has completed.\n\nCompleted: %1\$d\nFailed: %2\$d\n\nView details in your WordPress admin.", 'featured-image-helper' ),
			$stats['completed'],
			$stats['failed']
		);

		wp_mail( $admin_email, $subject, $message );

		// Clear the completed notification flag.
		delete_transient( 'fih_queue_completion_notified' );
	}
}
