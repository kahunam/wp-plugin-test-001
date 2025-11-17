<?php
/**
 * Settings Page Class.
 *
 * Handles the plugin settings page and options.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Settings Page Class.
 *
 * @since 1.0.0
 */
class FIH_Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'featured-image-helper-settings';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_fih_test_api', array( $this, 'test_api_connection' ) );
		add_action( 'admin_post_fih_clear_logs', array( $this, 'clear_logs' ) );
	}

	/**
	 * Add settings page to WordPress admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Featured Image Helper Settings', 'featured-image-helper' ),
			__( 'Featured Image Helper', 'featured-image-helper' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// API Configuration.
		register_setting( 'fih_api_settings', 'fih_gemini_api_key', array( $this, 'sanitize_api_key' ) );
		register_setting( 'fih_api_settings', 'fih_unsplash_api_key', 'sanitize_text_field' );
		register_setting( 'fih_api_settings', 'fih_pexels_api_key', 'sanitize_text_field' );

		// Generation Settings.
		register_setting( 'fih_generation_settings', 'fih_default_prompt_style', 'sanitize_text_field' );
		register_setting( 'fih_generation_settings', 'fih_content_source', 'sanitize_text_field' );
		register_setting( 'fih_generation_settings', 'fih_default_image_size', 'sanitize_text_field' );
		register_setting( 'fih_generation_settings', 'fih_custom_prompt_template', 'sanitize_textarea_field' );

		// Auto-Fill Rules.
		register_setting( 'fih_autofill_settings', 'fih_auto_generate_enabled', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'fih_autofill_settings', 'fih_auto_generate_trigger', 'sanitize_text_field' );
		register_setting( 'fih_autofill_settings', 'fih_enabled_post_types', array( $this, 'sanitize_post_types' ) );

		// Fallback Options.
		register_setting( 'fih_fallback_settings', 'fih_default_image_id', 'absint' );
		register_setting( 'fih_fallback_settings', 'fih_use_first_image', array( $this, 'sanitize_checkbox' ) );

		// Advanced Settings.
		register_setting( 'fih_advanced_settings', 'fih_batch_size', 'absint' );
		register_setting( 'fih_advanced_settings', 'fih_queue_interval', 'absint' );
		register_setting( 'fih_advanced_settings', 'fih_log_retention_days', 'absint' );
		register_setting( 'fih_advanced_settings', 'fih_debug_logging_enabled', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'fih_advanced_settings', 'fih_send_completion_email', array( $this, 'sanitize_checkbox' ) );
	}

	/**
	 * Sanitize API key.
	 *
	 * @since 1.0.0
	 * @param string $value API key value.
	 * @return string Encrypted API key.
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );

		if ( empty( $value ) ) {
			return '';
		}

		// Encrypt the API key.
		$gemini = FIH_Core::get_instance()->get_gemini();
		return $gemini->encrypt_api_key( $value );
	}

	/**
	 * Sanitize checkbox value.
	 *
	 * @since 1.0.0
	 * @param mixed $value Checkbox value.
	 * @return bool Sanitized boolean.
	 */
	public function sanitize_checkbox( $value ) {
		return (bool) $value;
	}

	/**
	 * Sanitize post types array.
	 *
	 * @since 1.0.0
	 * @param mixed $value Post types value.
	 * @return array Sanitized post types.
	 */
	public function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return array( 'post' );
		}

		return array_map( 'sanitize_text_field', $value );
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

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'api';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=<?php echo esc_attr( $this->page_slug ); ?>&tab=api" class="nav-tab <?php echo 'api' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'API Configuration', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->page_slug ); ?>&tab=generation" class="nav-tab <?php echo 'generation' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Generation Settings', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->page_slug ); ?>&tab=autofill" class="nav-tab <?php echo 'autofill' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Auto-Fill Rules', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->page_slug ); ?>&tab=fallback" class="nav-tab <?php echo 'fallback' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Fallback Options', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->page_slug ); ?>&tab=logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logs', 'featured-image-helper' ); ?>
				</a>
				<a href="?page=<?php echo esc_attr( $this->page_slug ); ?>&tab=advanced" class="nav-tab <?php echo 'advanced' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Advanced', 'featured-image-helper' ); ?>
				</a>
			</h2>

			<div class="fih-settings-content">
				<?php
				switch ( $active_tab ) {
					case 'api':
						$this->render_api_settings();
						break;
					case 'generation':
						$this->render_generation_settings();
						break;
					case 'autofill':
						$this->render_autofill_settings();
						break;
					case 'fallback':
						$this->render_fallback_settings();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					case 'advanced':
						$this->render_advanced_settings();
						break;
					default:
						$this->render_api_settings();
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
	private function render_api_settings() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'fih_api_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fih_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<input type="text" id="fih_gemini_api_key" name="fih_gemini_api_key" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your Gemini API key', 'featured-image-helper' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Get your API key from Google AI Studio.', 'featured-image-helper' ); ?>
							<a href="https://makersuite.google.com/app/apikey" target="_blank"><?php esc_html_e( 'Get API Key', 'featured-image-helper' ); ?></a>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_unsplash_api_key"><?php esc_html_e( 'Unsplash API Key (Optional)', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<input type="text" id="fih_unsplash_api_key" name="fih_unsplash_api_key" value="<?php echo esc_attr( get_option( 'fih_unsplash_api_key', '' ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'For smart suggestions from Unsplash.', 'featured-image-helper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_pexels_api_key"><?php esc_html_e( 'Pexels API Key (Optional)', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<input type="text" id="fih_pexels_api_key" name="fih_pexels_api_key" value="<?php echo esc_attr( get_option( 'fih_pexels_api_key', '' ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'For smart suggestions from Pexels.', 'featured-image-helper' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'fih_test_api', 'fih_test_api_nonce' ); ?>
			<input type="hidden" name="action" value="fih_test_api" />
			<?php submit_button( __( 'Test API Connection', 'featured-image-helper' ), 'secondary', 'test_api' ); ?>
		</form>
		<?php
	}

	/**
	 * Render generation settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_generation_settings() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'fih_generation_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fih_default_prompt_style"><?php esc_html_e( 'Default Prompt Style', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<select id="fih_default_prompt_style" name="fih_default_prompt_style">
							<option value="photographic" <?php selected( get_option( 'fih_default_prompt_style', 'photographic' ), 'photographic' ); ?>><?php esc_html_e( 'Photographic', 'featured-image-helper' ); ?></option>
							<option value="illustration" <?php selected( get_option( 'fih_default_prompt_style' ), 'illustration' ); ?>><?php esc_html_e( 'Illustration', 'featured-image-helper' ); ?></option>
							<option value="abstract" <?php selected( get_option( 'fih_default_prompt_style' ), 'abstract' ); ?>><?php esc_html_e( 'Abstract', 'featured-image-helper' ); ?></option>
							<option value="minimal" <?php selected( get_option( 'fih_default_prompt_style' ), 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'featured-image-helper' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_content_source"><?php esc_html_e( 'Content Source for Prompt', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<select id="fih_content_source" name="fih_content_source">
							<option value="title" <?php selected( get_option( 'fih_content_source', 'title' ), 'title' ); ?>><?php esc_html_e( 'Post Title', 'featured-image-helper' ); ?></option>
							<option value="excerpt" <?php selected( get_option( 'fih_content_source' ), 'excerpt' ); ?>><?php esc_html_e( 'Post Excerpt', 'featured-image-helper' ); ?></option>
							<option value="content" <?php selected( get_option( 'fih_content_source' ), 'content' ); ?>><?php esc_html_e( 'Post Content (first 100 words)', 'featured-image-helper' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_default_image_size"><?php esc_html_e( 'Default Image Size', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<input type="text" id="fih_default_image_size" name="fih_default_image_size" value="<?php echo esc_attr( get_option( 'fih_default_image_size', '1200x630' ) ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( 'Format: WIDTHxHEIGHT (e.g., 1200x630)', 'featured-image-helper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_custom_prompt_template"><?php esc_html_e( 'Custom Prompt Template (Optional)', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<textarea id="fih_custom_prompt_template" name="fih_custom_prompt_template" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'fih_custom_prompt_template', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Use {content} as placeholder for post content. Leave empty to use default templates.', 'featured-image-helper' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render auto-fill settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_autofill_settings() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'fih_autofill_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Enable Auto-Generation', 'featured-image-helper' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="fih_auto_generate_enabled" value="1" <?php checked( get_option( 'fih_auto_generate_enabled', false ), true ); ?> />
							<?php esc_html_e( 'Automatically generate featured images', 'featured-image-helper' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_auto_generate_trigger"><?php esc_html_e( 'Generation Trigger', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<select id="fih_auto_generate_trigger" name="fih_auto_generate_trigger">
							<option value="manual" <?php selected( get_option( 'fih_auto_generate_trigger', 'manual' ), 'manual' ); ?>><?php esc_html_e( 'Manual Only', 'featured-image-helper' ); ?></option>
							<option value="publish" <?php selected( get_option( 'fih_auto_generate_trigger' ), 'publish' ); ?>><?php esc_html_e( 'On Post Publish', 'featured-image-helper' ); ?></option>
							<option value="update" <?php selected( get_option( 'fih_auto_generate_trigger' ), 'update' ); ?>><?php esc_html_e( 'On Post Update', 'featured-image-helper' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Enabled Post Types', 'featured-image-helper' ); ?>
					</th>
					<td>
						<?php
						$post_types         = get_post_types( array( 'public' => true ), 'objects' );
						$enabled_post_types = get_option( 'fih_enabled_post_types', array( 'post' ) );

						foreach ( $post_types as $post_type ) {
							if ( 'attachment' === $post_type->name ) {
								continue;
							}
							?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="fih_enabled_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled_post_types, true ) ); ?> />
								<?php echo esc_html( $post_type->labels->name ); ?>
							</label>
							<?php
						}
						?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render fallback settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_fallback_settings() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'fih_fallback_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fih_default_image_id"><?php esc_html_e( 'Default Fallback Image', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<?php
						$default_image_id = get_option( 'fih_default_image_id', 0 );
						if ( $default_image_id ) {
							echo wp_get_attachment_image( $default_image_id, 'thumbnail' );
						}
						?>
						<input type="hidden" id="fih_default_image_id" name="fih_default_image_id" value="<?php echo esc_attr( $default_image_id ); ?>" />
						<button type="button" class="button fih-upload-image"><?php esc_html_e( 'Select Image', 'featured-image-helper' ); ?></button>
						<button type="button" class="button fih-remove-image" <?php echo $default_image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove Image', 'featured-image-helper' ); ?></button>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Use First Image in Content', 'featured-image-helper' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="fih_use_first_image" value="1" <?php checked( get_option( 'fih_use_first_image', false ), true ); ?> />
							<?php esc_html_e( 'Try to use the first image from post content as fallback', 'featured-image-helper' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render logs tab.
	 *
	 * @since 1.0.0
	 */
	private function render_logs_tab() {
		$logger = FIH_Core::get_instance()->get_logger();
		$logs   = $logger->get_logs( 50 );
		?>
		<h2><?php esc_html_e( 'Recent Activity Logs', 'featured-image-helper' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'featured-image-helper' ); ?></th>
					<th><?php esc_html_e( 'Type', 'featured-image-helper' ); ?></th>
					<th><?php esc_html_e( 'Post', 'featured-image-helper' ); ?></th>
					<th><?php esc_html_e( 'Details', 'featured-image-helper' ); ?></th>
					<th><?php esc_html_e( 'Status', 'featured-image-helper' ); ?></th>
					<th><?php esc_html_e( 'Time (s)', 'featured-image-helper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No logs available.', 'featured-image-helper' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<?php
						// Determine if this is an API event or generation log
						$is_api_event = ( 0 === (int) $log['post_id'] );
						$status_class = 'success' === $log['response_status'] ? 'style="color: #46b450;"' : ( 'error' === $log['response_status'] ? 'style="color: #dc3232;"' : '' );
						?>
						<tr>
							<td><?php echo esc_html( $log['created_at'] ); ?></td>
							<td>
								<?php
								if ( $is_api_event ) {
									echo '<span style="color: #2271b1; font-weight: 600;">⚙ ' . esc_html( ucfirst( str_replace( '_', ' ', $log['prompt'] ) ) ) . '</span>';
								} else {
									echo '<span style="color: #50575e;">🖼 ' . esc_html__( 'Image Generation', 'featured-image-helper' ) . '</span>';
								}
								?>
							</td>
							<td>
								<?php
								if ( $log['post_id'] ) {
									$post_title = get_the_title( $log['post_id'] );
									echo esc_html( $post_title ? $post_title : __( 'Unknown', 'featured-image-helper' ) );
								} else {
									echo '<em>' . esc_html__( 'N/A', 'featured-image-helper' ) . '</em>';
								}
								?>
							</td>
							<td>
								<?php
								if ( $is_api_event ) {
									echo esc_html( $log['error_message'] );
								} else {
									echo esc_html( wp_trim_words( $log['prompt'], 10 ) );
									if ( ! empty( $log['error_message'] ) ) {
										echo '<br><span style="color: #dc3232; font-size: 0.9em;">' . esc_html( $log['error_message'] ) . '</span>';
									}
								}
								?>
							</td>
							<td <?php echo $status_class; ?>>
								<strong><?php echo esc_html( ucfirst( $log['response_status'] ) ); ?></strong>
							</td>
							<td><?php echo esc_html( number_format( $log['generation_time'], 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 20px;">
			<?php wp_nonce_field( 'fih_clear_logs', 'fih_clear_logs_nonce' ); ?>
			<input type="hidden" name="action" value="fih_clear_logs" />
			<?php submit_button( __( 'Clear All Logs', 'featured-image-helper' ), 'delete', 'clear_logs', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render advanced settings tab.
	 *
	 * @since 1.0.0
	 */
	private function render_advanced_settings() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'fih_advanced_settings' );
			?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fih_batch_size"><?php esc_html_e( 'Batch Size', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<input type="number" id="fih_batch_size" name="fih_batch_size" value="<?php echo esc_attr( get_option( 'fih_batch_size', 5 ) ); ?>" min="1" max="20" class="small-text" />
						<p class="description"><?php esc_html_e( 'Number of images to process per batch (1-20).', 'featured-image-helper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_queue_interval"><?php esc_html_e( 'Queue Processing Interval (minutes)', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<input type="number" id="fih_queue_interval" name="fih_queue_interval" value="<?php echo esc_attr( get_option( 'fih_queue_interval', 5 ) ); ?>" min="1" max="60" class="small-text" />
						<p class="description"><?php esc_html_e( 'How often the queue should be processed (1-60 minutes).', 'featured-image-helper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fih_log_retention_days"><?php esc_html_e( 'Log Retention (days)', 'featured-image-helper' ); ?></label>
					</th>
					<td>
						<input type="number" id="fih_log_retention_days" name="fih_log_retention_days" value="<?php echo esc_attr( get_option( 'fih_log_retention_days', 7 ) ); ?>" min="1" max="90" class="small-text" />
						<p class="description"><?php esc_html_e( 'How long to keep logs before auto-deletion (1-90 days).', 'featured-image-helper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Debug Logging', 'featured-image-helper' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="fih_debug_logging_enabled" value="1" <?php checked( get_option( 'fih_debug_logging_enabled', false ), true ); ?> />
							<?php esc_html_e( 'Enable debug logging', 'featured-image-helper' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Completion Email', 'featured-image-helper' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" name="fih_send_completion_email" value="1" <?php checked( get_option( 'fih_send_completion_email', true ), true ); ?> />
							<?php esc_html_e( 'Send email notification when batch processing completes', 'featured-image-helper' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.0.0
	 */
	public function test_api_connection() {
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

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'fih_messages',
				'fih_api_test',
				sprintf(
					/* translators: %s: error message */
					__( 'API connection failed: %s', 'featured-image-helper' ),
					$result->get_error_message()
				),
				'error'
			);
		} else {
			add_settings_error(
				'fih_messages',
				'fih_api_test',
				__( 'API connection successful!', 'featured-image-helper' ),
				'success'
			);
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => $this->page_slug,
					'tab'               => 'api',
					'settings-updated' => 'true',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Clear all logs.
	 *
	 * @since 1.0.0
	 */
	public function clear_logs() {
		// Verify nonce.
		if ( ! isset( $_POST['fih_clear_logs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fih_clear_logs_nonce'] ) ), 'fih_clear_logs' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'featured-image-helper' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'featured-image-helper' ) );
		}

		$logger = FIH_Core::get_instance()->get_logger();
		$logger->clear_logs();

		add_settings_error(
			'fih_messages',
			'fih_logs_cleared',
			__( 'All logs have been cleared.', 'featured-image-helper' ),
			'success'
		);

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => $this->page_slug,
					'tab'               => 'logs',
					'settings-updated' => 'true',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
