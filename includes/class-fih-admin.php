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
	 * Settings page slug.
	 *
	 * @var string
	 */
	private $settings_slug = 'featured-image-helper-settings';

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
		add_action( 'wp_ajax_fih_bulk_generate_all', array( $this, 'ajax_bulk_generate_all' ) );

		// Handle bulk actions.
		add_action( 'admin_post_fih_bulk_generate', array( $this, 'handle_bulk_generate' ) );
		add_action( 'admin_post_fih_bulk_add_to_queue', array( $this, 'handle_bulk_add_to_queue' ) );

		// Handle API test.
		add_action( 'admin_post_fih_test_api', array( $this, 'handle_test_api' ) );

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
		// Add main menu page.
		add_menu_page(
			__( 'Featured Image Helper', 'featured-image-helper' ),
			__( 'Featured Images', 'featured-image-helper' ),
			'edit_posts',
			$this->page_slug,
			array( $this, 'render_dashboard_page' ),
			'dashicons-format-image',
			26
		);

		// Add Dashboard submenu.
		add_submenu_page(
			$this->page_slug,
			__( 'Dashboard', 'featured-image-helper' ),
			__( 'Dashboard', 'featured-image-helper' ),
			'edit_posts',
			$this->page_slug,
			array( $this, 'render_dashboard_page' )
		);

		// Add Settings submenu.
		add_submenu_page(
			$this->page_slug,
			__( 'Settings', 'featured-image-helper' ),
			__( 'Settings', 'featured-image-helper' ),
			'manage_options',
			$this->settings_slug,
			array( $this, 'render_settings_page' )
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
			'toplevel_page_' . $this->page_slug,
			'featured-images_page_' . $this->settings_slug,
			'index.php', // Dashboard.
		);

		if ( ! in_array( $hook, $allowed_pages, true ) ) {
			return;
		}

		// Check if we're on the settings page - use React UI.
		if ( 'featured-images_page_' . $this->settings_slug === $hook ) {
			$this->enqueue_react_settings();
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
	 * Enqueue React-based settings page assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_react_settings() {
		$asset_file = FIH_PLUGIN_DIR . 'build/settings.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Enqueue WordPress component styles.
		wp_enqueue_style( 'wp-components' );

		// Enqueue built React app.
		wp_enqueue_script(
			'fih-settings-js',
			FIH_PLUGIN_URL . 'build/settings.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue React app styles.
		wp_enqueue_style(
			'fih-settings-css',
			FIH_PLUGIN_URL . 'build/settings.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// Set up REST API.
		wp_localize_script(
			'fih-settings-js',
			'fihSettings',
			array(
				'restUrl'   => rest_url(),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'featured-image-helper' ) );
		}

		// Get filter parameters.
		$post_type   = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post';
		$paged       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page    = 25;

		// Get posts without featured images.
		$posts_query = $this->get_posts_without_images( $post_type, $paged, $per_page );
		$stats       = $this->get_missing_images_stats();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Featured Image Helper', 'featured-image-helper' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Stats Cards -->
			<div class="fih-stats-grid">
				<?php foreach ( $stats as $pt => $count ) : ?>
					<?php
					$pt_obj = get_post_type_object( $pt );
					?>
					<div class="postbox">
						<div class="postbox-header">
							<h2 class="hndle"><?php echo esc_html( $pt_obj->labels->name ); ?></h2>
						</div>
						<div class="inside">
							<div class="main">
								<p class="fih-stat-count"><?php echo absint( $count ); ?></p>
								<p class="description"><?php esc_html_e( 'without featured images', 'featured-image-helper' ); ?></p>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Filter and Bulk Actions -->
			<div class="fih-toolbar">
				<form method="get" style="display: inline-block;">
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
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'featured-image-helper' ); ?></button>
				</form>

				<button type="button" class="button button-primary fih-bulk-generate-all" data-post-type="<?php echo esc_attr( $post_type ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_bulk_generate_all' ) ); ?>" style="margin-left: 10px;">
					<?php esc_html_e( 'Generate All Featured Images', 'featured-image-helper' ); ?>
				</button>
			</div>

			<!-- Posts Table -->
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fih-posts-form">
				<?php wp_nonce_field( 'fih_bulk_action', 'fih_bulk_nonce' ); ?>
				<input type="hidden" name="action" value="fih_bulk_add_to_queue" />

				<div class="postbox" style="margin-top: 20px;">
					<div class="postbox-header">
						<h2><?php esc_html_e( 'Posts & Pages Needing Featured Images', 'featured-image-helper' ); ?></h2>
						<div class="postbox-title-action">
							<button type="submit" class="button"><?php esc_html_e( 'Add Selected to Queue', 'featured-image-helper' ); ?></button>
						</div>
					</div>

					<div class="inside">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<td class="manage-column column-cb check-column">
										<input type="checkbox" id="fih-select-all" />
									</td>
									<th class="manage-column column-primary"><?php esc_html_e( 'Title', 'featured-image-helper' ); ?></th>
									<th class="manage-column"><?php esc_html_e( 'Type', 'featured-image-helper' ); ?></th>
									<th class="manage-column"><?php esc_html_e( 'Date', 'featured-image-helper' ); ?></th>
									<th class="manage-column"><?php esc_html_e( 'Actions', 'featured-image-helper' ); ?></th>
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
											<td class="column-primary" data-colname="<?php esc_attr_e( 'Title', 'featured-image-helper' ); ?>">
												<strong>
													<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
														<?php the_title(); ?>
													</a>
												</strong>
											</td>
											<td data-colname="<?php esc_attr_e( 'Type', 'featured-image-helper' ); ?>">
												<?php
												$pt_obj = get_post_type_object( get_post_type() );
												echo esc_html( $pt_obj->labels->singular_name );
												?>
											</td>
											<td data-colname="<?php esc_attr_e( 'Date', 'featured-image-helper' ); ?>">
												<?php echo esc_html( get_the_date() ); ?>
											</td>
											<td data-colname="<?php esc_attr_e( 'Actions', 'featured-image-helper' ); ?>">
												<button type="button" class="button button-small fih-generate-single" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_generate_' . $post_id ) ); ?>">
													<?php esc_html_e( 'Generate', 'featured-image-helper' ); ?>
												</button>
											</td>
										</tr>
									<?php endwhile; ?>
									<?php wp_reset_postdata(); ?>
								<?php else : ?>
									<tr>
										<td colspan="5" style="text-align: center; padding: 2em;">
											<?php esc_html_e( 'No posts found without featured images.', 'featured-image-helper' ); ?>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>

						<?php
						$total_pages = $posts_query->max_num_pages;
						if ( $total_pages > 1 ) :
							?>
							<div class="tablenav bottom">
								<div class="tablenav-pages">
									<?php
									echo paginate_links(
										array(
											'base'      => add_query_arg( 'paged', '%#%' ),
											'format'    => '',
											'prev_text' => __( '&laquo;', 'featured-image-helper' ),
											'next_text' => __( '&raquo;', 'featured-image-helper' ),
											'total'     => $total_pages,
											'current'   => $paged,
										)
									);
									?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</form>

			<!-- Queue Status -->
			<div class="postbox" style="margin-top: 20px;">
				<div class="postbox-header">
					<h2><?php esc_html_e( 'Queue Status', 'featured-image-helper' ); ?></h2>
				</div>
				<div class="inside">
					<div class="fih-queue-stats" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_queue_stats' ) ); ?>">
						<?php $this->render_queue_stats(); ?>
					</div>
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
		<div class="fih-queue-grid">
			<div class="fih-queue-stat">
				<strong><?php esc_html_e( 'Pending:', 'featured-image-helper' ); ?></strong> <?php echo absint( $stats['pending'] ); ?>
			</div>
			<div class="fih-queue-stat">
				<strong><?php esc_html_e( 'Processing:', 'featured-image-helper' ); ?></strong> <?php echo absint( $stats['processing'] ); ?>
			</div>
			<div class="fih-queue-stat">
				<strong style="color: #46b450;"><?php esc_html_e( 'Completed:', 'featured-image-helper' ); ?></strong> <span style="color: #46b450;"><?php echo absint( $stats['completed'] ); ?></span>
			</div>
			<div class="fih-queue-stat">
				<strong style="color: #dc3232;"><?php esc_html_e( 'Failed:', 'featured-image-helper' ); ?></strong> <span style="color: #dc3232;"><?php echo absint( $stats['failed'] ); ?></span>
			</div>
		</div>

		<div class="fih-queue-controls" style="margin-top: 15px;">
			<?php if ( $queue->is_paused() ) : ?>
				<button type="button" class="button button-primary fih-resume-queue" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_resume_queue' ) ); ?>">
					<?php esc_html_e( 'Resume Queue', 'featured-image-helper' ); ?>
				</button>
			<?php else : ?>
				<button type="button" class="button fih-pause-queue" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fih_pause_queue' ) ); ?>">
					<?php esc_html_e( 'Pause Queue', 'featured-image-helper' ); ?>
				</button>
			<?php endif; ?>
		</div>
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

	/**
	 * AJAX: Bulk generate all featured images.
	 *
	 * @since 1.0.0
	 */
	public function ajax_bulk_generate_all() {
		// Verify nonce.
		check_ajax_referer( 'fih_ajax_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'featured-image-helper' ) ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'post';

		// Get all posts without featured images.
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$post_ids = get_posts( $args );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No posts found without featured images.', 'featured-image-helper' ) ) );
		}

		// Add all posts to queue.
		$queue = FIH_Core::get_instance()->get_queue();
		$added = $queue->add_bulk_to_queue( $post_ids );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of posts added */
					__( '%d posts added to the queue for featured image generation.', 'featured-image-helper' ),
					$added
				),
				'count'   => $added,
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'featured-image-helper' ) );
		}

		// Get settings instance to register settings.
		$settings = FIH_Core::get_instance()->get_settings();

		// Handle settings save.
		if ( isset( $_POST['fih_save_settings'] ) && check_admin_referer( 'fih_settings_nonce', 'fih_settings_nonce' ) ) {
			$this->save_settings();
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'api';
		?>
		<div class="wrap fih-wrap fih-settings-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Featured Image Helper Settings', 'featured-image-helper' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <?php esc_html_e( 'Settings saved successfully!', 'featured-image-helper' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['test_result'] ) && isset( $_GET['test_message'] ) ) : ?>
				<?php
				$test_result  = sanitize_text_field( wp_unslash( $_GET['test_result'] ) );
				$test_message = rawurldecode( sanitize_text_field( wp_unslash( $_GET['test_message'] ) ) );
				$notice_class = 'success' === $test_result ? 'notice-success' : 'notice-error';
				$icon_class = 'success' === $test_result ? 'dashicons-yes-alt' : 'dashicons-warning';
				$icon_color = 'success' === $test_result ? '#46b450' : '#d63638';
				?>
				<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
					<p><span class="dashicons <?php echo esc_attr( $icon_class ); ?>" style="color: <?php echo esc_attr( $icon_color ); ?>;"></span> <?php echo esc_html( $test_message ); ?></p>
				</div>
			<?php endif; ?>

			<div class="fih-tabs">
				<a href="?page=<?php echo esc_attr( $this->settings_slug ); ?>&tab=api" class="fih-tab <?php echo 'api' === $active_tab ? 'fih-tab-active' : ''; ?>">
					<?php esc_html_e( 'API Configuration', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->settings_slug ); ?>&tab=generation" class="fih-tab <?php echo 'generation' === $active_tab ? 'fih-tab-active' : ''; ?>">
					<?php esc_html_e( 'Generation', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->settings_slug ); ?>&tab=autofill" class="fih-tab <?php echo 'autofill' === $active_tab ? 'fih-tab-active' : ''; ?>">
					<?php esc_html_e( 'Auto-Fill', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->settings_slug ); ?>&tab=fallback" class="fih-tab <?php echo 'fallback' === $active_tab ? 'fih-tab-active' : ''; ?>">
					<?php esc_html_e( 'Fallback', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->settings_slug ); ?>&tab=advanced" class="fih-tab <?php echo 'advanced' === $active_tab ? 'fih-tab-active' : ''; ?>">
					<?php esc_html_e( 'Advanced', 'featured-image-helper' ); ?>
				</a>
			</div>

			<div class="fih-card fih-settings-card">
				<?php
				switch ( $active_tab ) {
					case 'api':
						$this->render_api_settings_tab();
						break;
					case 'generation':
						$this->render_generation_settings_tab();
						break;
					case 'autofill':
						$this->render_autofill_settings_tab();
						break;
					case 'fallback':
						$this->render_fallback_settings_tab();
						break;
					case 'advanced':
						$this->render_advanced_settings_tab();
						break;
					default:
						$this->render_api_settings_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render API settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_api_settings_tab() {
		?>
		<form method="post" action="" class="fih-settings-form">
			<?php wp_nonce_field( 'fih_settings_nonce', 'fih_settings_nonce' ); ?>
			<input type="hidden" name="fih_save_settings" value="api" />

			<div class="fih-form-group">
				<label for="fih_gemini_api_key" class="fih-label">
					<?php esc_html_e( 'Gemini API Key', 'featured-image-helper' ); ?>
					<span class="fih-required">*</span>
				</label>
				<?php $has_api_key = ! empty( get_option( 'fih_gemini_api_key' ) ); ?>
				<input type="password" id="fih_gemini_api_key" name="fih_gemini_api_key" value="" class="fih-input" placeholder="<?php echo $has_api_key ? esc_attr__( 'Enter new API key to update', 'featured-image-helper' ) : esc_attr__( 'Enter your Gemini API key', 'featured-image-helper' ); ?>" />
				<p class="fih-help-text">
					<?php esc_html_e( 'This plugin uses Gemini 2.5 Flash (nano banana) for AI image generation. Get your API key from Google AI Studio.', 'featured-image-helper' ); ?>
					<a href="https://aistudio.google.com/app/apikey" target="_blank" class="fih-link"><?php esc_html_e( 'Get API Key', 'featured-image-helper' ); ?></a>
				</p>
				<?php if ( $has_api_key ) : ?>
					<p class="fih-help-text" style="color: #46b450; font-weight: 600;">
						✓ <?php esc_html_e( 'API key is configured. Leave blank to keep current key, or enter a new key to update.', 'featured-image-helper' ); ?>
					</p>
				<?php else : ?>
					<p class="fih-help-text" style="color: #dc3232;">
						⚠ <?php esc_html_e( 'No API key configured. You must enter an API key to use this plugin.', 'featured-image-helper' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="fih-form-actions">
				<button type="submit" class="fih-button fih-button-primary"><?php esc_html_e( 'Save Settings', 'featured-image-helper' ); ?></button>
			</div>
		</form>

		<?php if ( get_option( 'fih_gemini_api_key' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
				<?php wp_nonce_field( 'fih_test_api', 'fih_test_api_nonce' ); ?>
				<input type="hidden" name="action" value="fih_test_api" />
				<button type="submit" class="fih-button"><?php esc_html_e( 'Test API Connection', 'featured-image-helper' ); ?></button>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render generation settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_generation_settings_tab() {
		?>
		<form method="post" action="" class="fih-settings-form">
			<?php wp_nonce_field( 'fih_settings_nonce', 'fih_settings_nonce' ); ?>
			<input type="hidden" name="fih_save_settings" value="generation" />

			<div class="fih-form-group">
				<label for="fih_default_prompt_style" class="fih-label"><?php esc_html_e( 'Default Prompt Style', 'featured-image-helper' ); ?></label>
				<select id="fih_default_prompt_style" name="fih_default_prompt_style" class="fih-select">
					<option value="photographic" <?php selected( get_option( 'fih_default_prompt_style', 'photographic' ), 'photographic' ); ?>><?php esc_html_e( 'Photographic', 'featured-image-helper' ); ?></option>
					<option value="illustration" <?php selected( get_option( 'fih_default_prompt_style' ), 'illustration' ); ?>><?php esc_html_e( 'Illustration', 'featured-image-helper' ); ?></option>
					<option value="abstract" <?php selected( get_option( 'fih_default_prompt_style' ), 'abstract' ); ?>><?php esc_html_e( 'Abstract', 'featured-image-helper' ); ?></option>
					<option value="minimal" <?php selected( get_option( 'fih_default_prompt_style' ), 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'featured-image-helper' ); ?></option>
				</select>
			</div>

			<div class="fih-form-group">
				<label for="fih_content_source" class="fih-label"><?php esc_html_e( 'Content Source for Prompt', 'featured-image-helper' ); ?></label>
				<select id="fih_content_source" name="fih_content_source" class="fih-select">
					<option value="title" <?php selected( get_option( 'fih_content_source', 'title' ), 'title' ); ?>><?php esc_html_e( 'Post Title', 'featured-image-helper' ); ?></option>
					<option value="excerpt" <?php selected( get_option( 'fih_content_source' ), 'excerpt' ); ?>><?php esc_html_e( 'Post Excerpt', 'featured-image-helper' ); ?></option>
					<option value="content" <?php selected( get_option( 'fih_content_source' ), 'content' ); ?>><?php esc_html_e( 'Post Content (first 100 words)', 'featured-image-helper' ); ?></option>
				</select>
			</div>

			<div class="fih-form-group">
				<label for="fih_default_image_size" class="fih-label"><?php esc_html_e( 'Default Image Size', 'featured-image-helper' ); ?></label>
				<input type="text" id="fih_default_image_size" name="fih_default_image_size" value="<?php echo esc_attr( get_option( 'fih_default_image_size', '1200x630' ) ); ?>" class="fih-input" />
				<p class="fih-help-text"><?php esc_html_e( 'Format: WIDTHxHEIGHT (e.g., 1200x630)', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-group">
				<label for="fih_custom_prompt_template" class="fih-label"><?php esc_html_e( 'Custom Prompt Template', 'featured-image-helper' ); ?></label>
				<textarea id="fih_custom_prompt_template" name="fih_custom_prompt_template" rows="5" class="fih-textarea"><?php echo esc_textarea( get_option( 'fih_custom_prompt_template', '' ) ); ?></textarea>
				<p class="fih-help-text"><?php esc_html_e( 'Use {content} as placeholder for post content. Leave empty to use default templates.', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-actions">
				<button type="submit" class="fih-button fih-button-primary"><?php esc_html_e( 'Save Settings', 'featured-image-helper' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Render auto-fill settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_autofill_settings_tab() {
		?>
		<form method="post" action="" class="fih-settings-form">
			<?php wp_nonce_field( 'fih_settings_nonce', 'fih_settings_nonce' ); ?>
			<input type="hidden" name="fih_save_settings" value="autofill" />

			<div class="fih-form-group">
				<label class="fih-checkbox-label">
					<input type="checkbox" name="fih_auto_generate_enabled" value="1" <?php checked( get_option( 'fih_auto_generate_enabled', false ), true ); ?> />
					<span><?php esc_html_e( 'Enable Auto-Generation', 'featured-image-helper' ); ?></span>
				</label>
				<p class="fih-help-text"><?php esc_html_e( 'Automatically generate featured images for new posts.', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-group">
				<label for="fih_auto_generate_trigger" class="fih-label"><?php esc_html_e( 'Generation Trigger', 'featured-image-helper' ); ?></label>
				<select id="fih_auto_generate_trigger" name="fih_auto_generate_trigger" class="fih-select">
					<option value="manual" <?php selected( get_option( 'fih_auto_generate_trigger', 'manual' ), 'manual' ); ?>><?php esc_html_e( 'Manual Only', 'featured-image-helper' ); ?></option>
					<option value="publish" <?php selected( get_option( 'fih_auto_generate_trigger' ), 'publish' ); ?>><?php esc_html_e( 'On Post Publish', 'featured-image-helper' ); ?></option>
					<option value="update" <?php selected( get_option( 'fih_auto_generate_trigger' ), 'update' ); ?>><?php esc_html_e( 'On Post Update', 'featured-image-helper' ); ?></option>
				</select>
			</div>

			<div class="fih-form-group">
				<label class="fih-label"><?php esc_html_e( 'Enabled Post Types', 'featured-image-helper' ); ?></label>
				<?php
				$post_types         = get_post_types( array( 'public' => true ), 'objects' );
				$enabled_post_types = get_option( 'fih_enabled_post_types', array( 'post' ) );

				foreach ( $post_types as $post_type ) {
					if ( 'attachment' === $post_type->name ) {
						continue;
					}
					?>
					<label class="fih-checkbox-label">
						<input type="checkbox" name="fih_enabled_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled_post_types, true ) ); ?> />
						<span><?php echo esc_html( $post_type->labels->name ); ?></span>
					</label>
					<?php
				}
				?>
			</div>

			<div class="fih-form-actions">
				<button type="submit" class="fih-button fih-button-primary"><?php esc_html_e( 'Save Settings', 'featured-image-helper' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Render fallback settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_fallback_settings_tab() {
		$default_image_id = get_option( 'fih_default_image_id', 0 );
		?>
		<form method="post" action="" class="fih-settings-form">
			<?php wp_nonce_field( 'fih_settings_nonce', 'fih_settings_nonce' ); ?>
			<input type="hidden" name="fih_save_settings" value="fallback" />

			<div class="fih-form-group">
				<label for="fih_default_image_id" class="fih-label"><?php esc_html_e( 'Default Fallback Image', 'featured-image-helper' ); ?></label>
				<div class="fih-image-preview">
					<?php
					if ( $default_image_id ) {
						echo wp_get_attachment_image( $default_image_id, 'thumbnail' );
					}
					?>
				</div>
				<input type="hidden" id="fih_default_image_id" name="fih_default_image_id" value="<?php echo esc_attr( $default_image_id ); ?>" />
				<div class="fih-button-group">
					<button type="button" class="fih-button fih-button-secondary fih-upload-image"><?php esc_html_e( 'Select Image', 'featured-image-helper' ); ?></button>
					<button type="button" class="fih-button fih-button-secondary fih-remove-image" <?php echo $default_image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove Image', 'featured-image-helper' ); ?></button>
				</div>
			</div>

			<div class="fih-form-group">
				<label class="fih-checkbox-label">
					<input type="checkbox" name="fih_use_first_image" value="1" <?php checked( get_option( 'fih_use_first_image', false ), true ); ?> />
					<span><?php esc_html_e( 'Use First Image in Content', 'featured-image-helper' ); ?></span>
				</label>
				<p class="fih-help-text"><?php esc_html_e( 'Try to use the first image from post content as fallback.', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-actions">
				<button type="submit" class="fih-button fih-button-primary"><?php esc_html_e( 'Save Settings', 'featured-image-helper' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Render advanced settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_advanced_settings_tab() {
		?>
		<form method="post" action="" class="fih-settings-form">
			<?php wp_nonce_field( 'fih_settings_nonce', 'fih_settings_nonce' ); ?>
			<input type="hidden" name="fih_save_settings" value="advanced" />

			<div class="fih-form-group">
				<label for="fih_batch_size" class="fih-label"><?php esc_html_e( 'Batch Size', 'featured-image-helper' ); ?></label>
				<input type="number" id="fih_batch_size" name="fih_batch_size" value="<?php echo esc_attr( get_option( 'fih_batch_size', 5 ) ); ?>" min="1" max="20" class="fih-input" />
				<p class="fih-help-text"><?php esc_html_e( 'Number of images to process per batch (1-20).', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-group">
				<label for="fih_queue_interval" class="fih-label"><?php esc_html_e( 'Queue Processing Interval', 'featured-image-helper' ); ?></label>
				<input type="number" id="fih_queue_interval" name="fih_queue_interval" value="<?php echo esc_attr( get_option( 'fih_queue_interval', 5 ) ); ?>" min="1" max="60" class="fih-input" />
				<p class="fih-help-text"><?php esc_html_e( 'How often the queue should be processed (1-60 minutes).', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-group">
				<label for="fih_log_retention_days" class="fih-label"><?php esc_html_e( 'Log Retention', 'featured-image-helper' ); ?></label>
				<input type="number" id="fih_log_retention_days" name="fih_log_retention_days" value="<?php echo esc_attr( get_option( 'fih_log_retention_days', 7 ) ); ?>" min="1" max="90" class="fih-input" />
				<p class="fih-help-text"><?php esc_html_e( 'How long to keep logs before auto-deletion (1-90 days).', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-group">
				<label class="fih-checkbox-label">
					<input type="checkbox" name="fih_debug_logging_enabled" value="1" <?php checked( get_option( 'fih_debug_logging_enabled', false ), true ); ?> />
					<span><?php esc_html_e( 'Enable Debug Logging', 'featured-image-helper' ); ?></span>
				</label>
			</div>

			<div class="fih-form-group">
				<label class="fih-checkbox-label">
					<input type="checkbox" name="fih_send_completion_email" value="1" <?php checked( get_option( 'fih_send_completion_email', true ), true ); ?> />
					<span><?php esc_html_e( 'Send Completion Email', 'featured-image-helper' ); ?></span>
				</label>
				<p class="fih-help-text"><?php esc_html_e( 'Send email notification when batch processing completes.', 'featured-image-helper' ); ?></p>
			</div>

			<div class="fih-form-actions">
				<button type="submit" class="fih-button fih-button-primary"><?php esc_html_e( 'Save Settings', 'featured-image-helper' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Handle API test request.
	 *
	 * @since 1.0.0
	 */
	public function handle_test_api() {
		// Verify nonce.
		if ( ! isset( $_POST['fih_test_api_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fih_test_api_nonce'] ) ), 'fih_test_api' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'featured-image-helper' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'featured-image-helper' ) );
		}

		$gemini = FIH_Core::get_instance()->get_gemini();
		$result = $gemini->test_connection();

		$message_type = 'error';
		$message      = '';

		if ( is_wp_error( $result ) ) {
			$message = sprintf(
				/* translators: %s: error message */
				__( 'API connection failed: %s', 'featured-image-helper' ),
				$result->get_error_message()
			);
		} else {
			$message_type = 'success';
			$message      = __( 'API connection successful! Your API key is working correctly and can generate images with Imagen 3.', 'featured-image-helper' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => $this->settings_slug,
					'tab'          => 'api',
					'test_result'  => $message_type,
					'test_message' => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 */
	private function save_settings() {
		if ( ! isset( $_POST['fih_save_settings'] ) ) {
			return;
		}

		$tab = sanitize_text_field( wp_unslash( $_POST['fih_save_settings'] ) );

		switch ( $tab ) {
			case 'api':
				// Handle Gemini API key - only update if a new value is provided
				if ( isset( $_POST['fih_gemini_api_key'] ) ) {
					$api_key = sanitize_text_field( wp_unslash( $_POST['fih_gemini_api_key'] ) );
					if ( ! empty( $api_key ) ) {
						update_option( 'fih_gemini_api_key', $api_key );

						// Log the API key save attempt
						$logger = FIH_Core::get_instance()->get_logger();
						if ( $logger ) {
							$logger->log_api_event( 'api_key_saved', 'Gemini API key was updated', 'success' );
						}
					}
				}
				break;

			case 'generation':
				if ( isset( $_POST['fih_default_prompt_style'] ) ) {
					update_option( 'fih_default_prompt_style', sanitize_text_field( wp_unslash( $_POST['fih_default_prompt_style'] ) ) );
				}
				if ( isset( $_POST['fih_content_source'] ) ) {
					update_option( 'fih_content_source', sanitize_text_field( wp_unslash( $_POST['fih_content_source'] ) ) );
				}
				if ( isset( $_POST['fih_default_image_size'] ) ) {
					update_option( 'fih_default_image_size', sanitize_text_field( wp_unslash( $_POST['fih_default_image_size'] ) ) );
				}
				if ( isset( $_POST['fih_custom_prompt_template'] ) ) {
					update_option( 'fih_custom_prompt_template', sanitize_textarea_field( wp_unslash( $_POST['fih_custom_prompt_template'] ) ) );
				}
				break;

			case 'autofill':
				update_option( 'fih_auto_generate_enabled', isset( $_POST['fih_auto_generate_enabled'] ) );
				if ( isset( $_POST['fih_auto_generate_trigger'] ) ) {
					update_option( 'fih_auto_generate_trigger', sanitize_text_field( wp_unslash( $_POST['fih_auto_generate_trigger'] ) ) );
				}
				if ( isset( $_POST['fih_enabled_post_types'] ) ) {
					$post_types = array_map( 'sanitize_text_field', wp_unslash( $_POST['fih_enabled_post_types'] ) );
					update_option( 'fih_enabled_post_types', $post_types );
				} else {
					update_option( 'fih_enabled_post_types', array() );
				}
				break;

			case 'fallback':
				if ( isset( $_POST['fih_default_image_id'] ) ) {
					update_option( 'fih_default_image_id', absint( $_POST['fih_default_image_id'] ) );
				}
				update_option( 'fih_use_first_image', isset( $_POST['fih_use_first_image'] ) );
				break;

			case 'advanced':
				if ( isset( $_POST['fih_batch_size'] ) ) {
					update_option( 'fih_batch_size', absint( $_POST['fih_batch_size'] ) );
				}
				if ( isset( $_POST['fih_queue_interval'] ) ) {
					update_option( 'fih_queue_interval', absint( $_POST['fih_queue_interval'] ) );
				}
				if ( isset( $_POST['fih_log_retention_days'] ) ) {
					update_option( 'fih_log_retention_days', absint( $_POST['fih_log_retention_days'] ) );
				}
				update_option( 'fih_debug_logging_enabled', isset( $_POST['fih_debug_logging_enabled'] ) );
				update_option( 'fih_send_completion_email', isset( $_POST['fih_send_completion_email'] ) );
				break;
		}

		// Redirect to avoid form resubmission.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => $this->settings_slug,
					'tab'               => $tab,
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
