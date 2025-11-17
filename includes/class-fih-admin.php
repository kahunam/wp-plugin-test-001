<?php
/**
 * Admin Interface Class.
 *
 * Handles admin pages, dashboard widgets, and bulk actions.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin Interface Class.
 *
 * @since 1.0.0
 */
class FIH_Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'featured-image-helper';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Add dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_fih_get_posts_without_images', array( $this, 'ajax_get_posts_without_images' ) );
		add_action( 'wp_ajax_fih_generate_single_image', array( $this, 'ajax_generate_single_image' ) );
		add_action( 'wp_ajax_fih_add_to_queue', array( $this, 'ajax_add_to_queue' ) );
		add_action( 'wp_ajax_fih_get_queue_stats', array( $this, 'ajax_get_queue_stats' ) );
		add_action( 'wp_ajax_fih_pause_queue', array( $this, 'ajax_pause_queue' ) );
		add_action( 'wp_ajax_fih_resume_queue', array( $this, 'ajax_resume_queue' ) );
		add_action( 'wp_ajax_fih_dashboard_stats', array( $this, 'ajax_dashboard_stats' ) );

		// Handle bulk actions.
		add_action( 'admin_post_fih_bulk_generate', array( $this, 'handle_bulk_generate' ) );
		add_action( 'admin_post_fih_bulk_add_to_queue', array( $this, 'handle_bulk_add_to_queue' ) );

		// Auto-generation hooks.
		$this->setup_auto_generation();
	}

	/**
	 * Setup auto-generation hooks.
	 *
	 * @since 1.0.0
	 */
	private function setup_auto_generation() {
		if ( ! get_option( 'fih_auto_generate_enabled', false ) ) {
			return;
		}

		$trigger = get_option( 'fih_auto_generate_trigger', 'manual' );

		switch ( $trigger ) {
			case 'publish':
				add_action( 'publish_post', array( $this, 'auto_generate_on_publish' ), 10, 2 );
				break;
			case 'update':
				add_action( 'save_post', array( $this, 'auto_generate_on_save' ), 10, 2 );
				break;
		}
	}

	/**
	 * Auto-generate featured image on post publish.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public function auto_generate_on_publish( $post_id, $post ) {
		// Check if post type is enabled.
		$enabled_post_types = get_option( 'fih_enabled_post_types', array( 'post' ) );
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return;
		}

		// Check if already has featured image.
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		// Add to queue.
		$queue = FIH_Core::get_instance()->get_queue();
		$queue->add_to_queue( $post_id, 10 );
	}

	/**
	 * Auto-generate featured image on post save.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public function auto_generate_on_save( $post_id, $post ) {
		// Avoid autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if post type is enabled.
		$enabled_post_types = get_option( 'fih_enabled_post_types', array( 'post' ) );
		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return;
		}

		// Only for published posts.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Check if already has featured image.
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		// Add to queue.
		$queue = FIH_Core::get_instance()->get_queue();
		$queue->add_to_queue( $post_id, 5 );
	}

	/**
	 * Add admin menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_media_page(
			__( 'Featured Image Helper', 'featured-image-helper' ),
			__( 'Featured Images', 'featured-image-helper' ),
			'edit_posts',
			$this->page_slug,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Add dashboard widget.
	 *
	 * @since 1.0.0
	 */
	public function add_dashboard_widget() {
		if ( current_user_can( 'edit_posts' ) ) {
			wp_add_dashboard_widget(
				'fih_dashboard_widget',
				__( 'Featured Images Status', 'featured-image-helper' ),
				array( $this, 'render_dashboard_widget' )
			);
		}
	}

	/**
	 * Render dashboard widget.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard_widget() {
		$stats = $this->get_missing_images_stats();
		?>
		<div class="fih-dashboard-widget">
			<div class="fih-stats">
				<p><strong><?php esc_html_e( 'Posts Without Featured Images:', 'featured-image-helper' ); ?></strong></p>
				<ul>
					<?php foreach ( $stats as $post_type => $count ) : ?>
						<li>
							<?php
							$post_type_obj = get_post_type_object( $post_type );
							echo esc_html( $post_type_obj->labels->name );
							?>: <strong><?php echo absint( $count ); ?></strong>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><a href="<?php echo esc_url( admin_url( 'upload.php?page=' . $this->page_slug ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Manage Featured Images', 'featured-image-helper' ); ?>
				</a></p>
			</div>
			<div class="fih-refresh">
				<button type="button" class="button fih-refresh-stats" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_dashboard_stats' ) ); ?>">
					<?php esc_html_e( 'Refresh', 'featured-image-helper' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Get statistics of posts without featured images.
	 *
	 * @since 1.0.0
	 * @return array Statistics by post type.
	 */
	private function get_missing_images_stats() {
		global $wpdb;

		$enabled_post_types = get_option( 'fih_enabled_post_types', array( 'post' ) );
		$stats              = array();

		foreach ( $enabled_post_types as $post_type ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(p.ID)
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
					WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND pm.meta_id IS NULL",
					$post_type
				)
			);

			$stats[ $post_type ] = absint( $count );
		}

		return $stats;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only enqueue on our plugin pages.
		$allowed_pages = array(
			'media_page_' . $this->page_slug,
			'index.php', // Dashboard.
			'settings_page_featured-image-helper-settings',
		);

		if ( ! in_array( $hook, $allowed_pages, true ) ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'fih-admin-css',
			FIH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FIH_VERSION
		);

		// Enqueue JS.
		wp_enqueue_script(
			'fih-admin-js',
			FIH_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FIH_VERSION,
			true
		);

		// Enqueue media library for image selection.
		wp_enqueue_media();

		// Localize script.
		wp_localize_script(
			'fih-admin-js',
			'fihAdmin',
			array(
				'ajaxUrl'                  => admin_url( 'admin-ajax.php' ),
				'nonce'                    => wp_create_nonce( 'fih_ajax_nonce' ),
				'strings'                  => array(
					'generating'    => __( 'Generating...', 'featured-image-helper' ),
					'success'       => __( 'Image generated successfully!', 'featured-image-helper' ),
					'error'         => __( 'Error generating image.', 'featured-image-helper' ),
					'selectImage'   => __( 'Select Default Image', 'featured-image-helper' ),
					'useThisImage'  => __( 'Use This Image', 'featured-image-helper' ),
					'confirmClear'  => __( 'Are you sure you want to clear all logs?', 'featured-image-helper' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'featured-image-helper' ) );
		}

		// Get filter parameters.
		$post_type   = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post';
		$paged       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page    = 25;

		// Get posts without featured images.
		$posts_query = $this->get_posts_without_images( $post_type, $paged, $per_page );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Posts Without Featured Images', 'featured-image-helper' ); ?></h1>

			<form method="get" class="fih-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_slug ); ?>" />

				<select name="post_type">
					<?php
					$enabled_post_types = get_option( 'fih_enabled_post_types', array( 'post' ) );
					foreach ( $enabled_post_types as $pt ) {
						$pt_obj = get_post_type_object( $pt );
						?>
						<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $post_type, $pt ); ?>>
							<?php echo esc_html( $pt_obj->labels->name ); ?>
						</option>
						<?php
					}
					?>
				</select>

				<?php submit_button( __( 'Filter', 'featured-image-helper' ), 'secondary', 'filter', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fih_bulk_action', 'fih_bulk_nonce' ); ?>
				<input type="hidden" name="action" value="fih_bulk_add_to_queue" />

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<button type="submit" class="button action"><?php esc_html_e( 'Add Selected to Queue', 'featured-image-helper' ); ?></button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="fih-select-all" /></td>
							<th><?php esc_html_e( 'Title', 'featured-image-helper' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'featured-image-helper' ); ?></th>
							<th><?php esc_html_e( 'Date', 'featured-image-helper' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'featured-image-helper' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $posts_query->have_posts() ) : ?>
							<?php while ( $posts_query->have_posts() ) : ?>
								<?php
								$posts_query->the_post();
								$post_id = get_the_ID();
								?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( $post_id ); ?>" />
									</th>
									<td>
										<strong>
											<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
												<?php the_title(); ?>
											</a>
										</strong>
									</td>
									<td>
										<?php
										$pt_obj = get_post_type_object( get_post_type() );
										echo esc_html( $pt_obj->labels->singular_name );
										?>
									</td>
									<td><?php echo esc_html( get_the_date() ); ?></td>
									<td>
										<button type="button" class="button fih-generate-single" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_generate_' . $post_id ) ); ?>">
											<?php esc_html_e( 'Generate Now', 'featured-image-helper' ); ?>
										</button>
									</td>
								</tr>
							<?php endwhile; ?>
							<?php wp_reset_postdata(); ?>
						<?php else : ?>
							<tr>
								<td colspan="5"><?php esc_html_e( 'No posts found without featured images.', 'featured-image-helper' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<?php
					$total_pages = $posts_query->max_num_pages;
					if ( $total_pages > 1 ) {
						$page_links = paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => __( '&laquo;', 'featured-image-helper' ),
								'next_text' => __( '&raquo;', 'featured-image-helper' ),
								'total'     => $total_pages,
								'current'   => $paged,
							)
						);

						if ( $page_links ) {
							echo '<div class="tablenav-pages">' . wp_kses_post( $page_links ) . '</div>';
						}
					}
					?>
				</div>
			</form>

			<div class="fih-queue-status" style="margin-top: 30px;">
				<h2><?php esc_html_e( 'Queue Status', 'featured-image-helper' ); ?></h2>
				<div class="fih-queue-stats" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_queue_stats' ) ); ?>">
					<?php $this->render_queue_stats(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render queue statistics.
	 *
	 * @since 1.0.0
	 */
	private function render_queue_stats() {
		$queue = FIH_Core::get_instance()->get_queue();
		$stats = $queue->get_stats();
		?>
		<table class="widefat">
			<tr>
				<td><?php esc_html_e( 'Pending:', 'featured-image-helper' ); ?></td>
				<td><strong><?php echo absint( $stats['pending'] ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Processing:', 'featured-image-helper' ); ?></td>
				<td><strong><?php echo absint( $stats['processing'] ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Completed:', 'featured-image-helper' ); ?></td>
				<td><strong><?php echo absint( $stats['completed'] ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Failed:', 'featured-image-helper' ); ?></td>
				<td><strong><?php echo absint( $stats['failed'] ); ?></strong></td>
			</tr>
		</table>

		<?php if ( $queue->is_paused() ) : ?>
			<button type="button" class="button fih-resume-queue" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_resume_queue' ) ); ?>">
				<?php esc_html_e( 'Resume Queue', 'featured-image-helper' ); ?>
			</button>
		<?php else : ?>
			<button type="button" class="button fih-pause-queue" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_pause_queue' ) ); ?>">
				<?php esc_html_e( 'Pause Queue', 'featured-image-helper' ); ?>
			</button>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get posts without featured images.
	 *
	 * @since 1.0.0
	 * @param string $post_type Post type to query.
	 * @param int    $paged Current page.
	 * @param int    $per_page Posts per page.
	 * @return WP_Query Query object.
	 */
	private function get_posts_without_images( $post_type = 'post', $paged = 1, $per_page = 25 ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'meta_query'     => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		return new WP_Query( $args );
	}

	/**
	 * Handle bulk add to queue.
	 *
	 * @since 1.0.0
	 */
	public function handle_bulk_add_to_queue() {
		// Verify nonce.
		if ( ! isset( $_POST['fih_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fih_bulk_nonce'] ) ), 'fih_bulk_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'featured-image-helper' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'featured-image-helper' ) );
		}

		// Get post IDs.
		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();

		if ( empty( $post_ids ) ) {
			wp_die( esc_html__( 'No posts selected.', 'featured-image-helper' ) );
		}

		// Add to queue.
		$queue = FIH_Core::get_instance()->get_queue();
		$added = $queue->add_bulk_to_queue( $post_ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => $this->page_slug,
					'message' => 'added_to_queue',
					'count'   => $added,
				),
				admin_url( 'upload.php' )
			)
		);
		exit;
	}

	/**
	 * AJAX: Generate single image.
	 *
	 * @since 1.0.0
	 */
	public function ajax_generate_single_image() {
		// Verify nonce.
		check_ajax_referer( 'fih_ajax_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'featured-image-helper' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'featured-image-helper' ) ) );
		}

		// Generate image.
		$gemini = FIH_Core::get_instance()->get_gemini();
		$style  = get_option( 'fih_default_prompt_style', 'photographic' );
		$result = $gemini->generate_image( $post_id, $style );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'       => __( 'Featured image generated successfully!', 'featured-image-helper' ),
				'attachment_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Get queue statistics.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_queue_stats() {
		// Verify nonce.
		check_ajax_referer( 'fih_ajax_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'featured-image-helper' ) ) );
		}

		$queue = FIH_Core::get_instance()->get_queue();
		$stats = $queue->get_stats();

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Pause queue.
	 *
	 * @since 1.0.0
	 */
	public function ajax_pause_queue() {
		// Verify nonce.
		check_ajax_referer( 'fih_ajax_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'featured-image-helper' ) ) );
		}

		$queue = FIH_Core::get_instance()->get_queue();
		$queue->pause();

		wp_send_json_success( array( 'message' => __( 'Queue paused.', 'featured-image-helper' ) ) );
	}

	/**
	 * AJAX: Resume queue.
	 *
	 * @since 1.0.0
	 */
	public function ajax_resume_queue() {
		// Verify nonce.
		check_ajax_referer( 'fih_ajax_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'featured-image-helper' ) ) );
		}

		$queue = FIH_Core::get_instance()->get_queue();
		$queue->resume();

		wp_send_json_success( array( 'message' => __( 'Queue resumed.', 'featured-image-helper' ) ) );
	}

	/**
	 * AJAX: Get dashboard statistics.
	 *
	 * @since 1.0.0
	 */
	public function ajax_dashboard_stats() {
		// Verify nonce.
		check_ajax_referer( 'fih_ajax_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'featured-image-helper' ) ) );
		}

		$stats = $this->get_missing_images_stats();

		wp_send_json_success( $stats );
	}
}
