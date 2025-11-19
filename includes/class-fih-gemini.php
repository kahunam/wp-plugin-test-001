<?php
/**
 * Gemini API Integration Class.
 *
 * Handles all interactions with the Google Gemini API for image generation.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Gemini API Integration Class.
 *
 * @since 1.0.0
 */
class FIH_Gemini {

	/**
	 * Gemini 2.5 Flash API endpoint for image generation (nano banana).
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent';

	/**
	 * Default prompt templates.
	 *
	 * @var array
	 */
	private $prompt_templates = array(
		'photographic' => 'Create a high-quality, professional photograph that represents: {content}. Style: photorealistic, well-lit, sharp focus.',
		'illustration' => 'Create a beautiful illustration that represents: {content}. Style: artistic, colorful, detailed illustration.',
		'abstract'     => 'Create an abstract visual representation of: {content}. Style: modern, abstract art, bold colors.',
		'minimal'      => 'Create a minimal, clean design that represents: {content}. Style: minimalist, simple, elegant.',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Initialize any hooks if needed.
	}

	/**
	 * Generate an image using Gemini API.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Post ID to generate image for.
	 * @param string $style Prompt style (photographic, illustration, abstract, minimal, or custom).
	 * @param string $custom_prompt Optional custom prompt.
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function generate_image( $post_id, $style = 'photographic', $custom_prompt = '' ) {
		$start_time = microtime( true );

		// Check if API key is configured.
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'featured-image-helper' ) );
		}

		// Get post content for prompt.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'featured-image-helper' ) );
		}

		// Allow filtering before generation.
		do_action( 'fih_before_generate_image', $post_id, $style );

		// Build the prompt.
		$prompt = $this->build_prompt( $post, $style, $custom_prompt );

		// Apply filter to prompt.
		$prompt = apply_filters( 'fih_prompt_template', $prompt, $post_id, $style );

		// Get image generation arguments.
		$args = $this->get_generation_args( $prompt );
		$args = apply_filters( 'fih_image_generation_args', $args, $post_id );

		// Make API request with retry logic.
		$response = $this->make_api_request( $args );

		$generation_time = microtime( true ) - $start_time;

		// Handle response.
		if ( is_wp_error( $response ) ) {
			// Log error.
			$this->log_generation(
				$post_id,
				$prompt,
				'error',
				$response->get_error_message(),
				$generation_time
			);

			return $response;
		}

		// Process the image and save to media library.
		$attachment_id = $this->save_generated_image( $response, $post );

		if ( is_wp_error( $attachment_id ) ) {
			// Log error.
			$this->log_generation(
				$post_id,
				$prompt,
				'error',
				$attachment_id->get_error_message(),
				$generation_time
			);

			return $attachment_id;
		}

		// Log success.
		$this->log_generation(
			$post_id,
			$prompt,
			'success',
			'',
			$generation_time
		);

		// Set as featured image.
		set_post_thumbnail( $post_id, $attachment_id );

		// Generate alt text and title.
		$this->generate_image_metadata( $attachment_id, $post );

		// Allow actions after generation.
		do_action( 'fih_after_generate_image', $post_id, $attachment_id, $style );

		return $attachment_id;
	}

	/**
	 * Build prompt from post content.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post Post object.
	 * @param string  $style Prompt style.
	 * @param string  $custom_prompt Custom prompt.
	 * @return string Generated prompt.
	 */
	private function build_prompt( $post, $style, $custom_prompt = '' ) {
		if ( ! empty( $custom_prompt ) ) {
			return $custom_prompt;
		}

		// Get content source (title, excerpt, or full content).
		$content_source = get_option( 'fih_content_source', 'title' );

		switch ( $content_source ) {
			case 'excerpt':
				$content = ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 );
				break;
			case 'content':
				$content = wp_trim_words( $post->post_content, 100 );
				break;
			case 'title':
			default:
				$content = $post->post_title;
				break;
		}

		// Remove HTML tags and clean up.
		$content = wp_strip_all_tags( $content );
		$content = sanitize_text_field( $content );

		// Get template and replace placeholder.
		$template = isset( $this->prompt_templates[ $style ] ) ? $this->prompt_templates[ $style ] : $this->prompt_templates['photographic'];
		$prompt   = str_replace( '{content}', $content, $template );

		return $prompt;
	}

	/**
	 * Get image generation arguments for API request.
	 *
	 * @since 1.0.0
	 * @param string $prompt The prompt text.
	 * @return array API request arguments.
	 */
	private function get_generation_args( $prompt ) {
		// Gemini 2.5 Flash (nano banana) format
		$args = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
					),
				),
			),
			'generationConfig' => array(
				'responseModalities' => array( 'Image' ),
			),
		);

		// Get image size setting and convert to aspect ratio
		$image_size = get_option( 'fih_default_image_size', '1200x630' );
		$aspect_ratio = $this->convert_size_to_aspect_ratio( $image_size );
		if ( $aspect_ratio ) {
			$args['generationConfig']['imageConfig'] = array(
				'aspectRatio' => $aspect_ratio,
			);
		}

		return $args;
	}

	/**
	 * Convert image size (WIDTHxHEIGHT) to aspect ratio string.
	 *
	 * @since 1.0.0
	 * @param string $size Image size in format WIDTHxHEIGHT.
	 * @return string|null Aspect ratio string or null if invalid.
	 */
	private function convert_size_to_aspect_ratio( $size ) {
		$parts = explode( 'x', strtolower( $size ) );
		if ( count( $parts ) !== 2 ) {
			return null;
		}

		$width = intval( $parts[0] );
		$height = intval( $parts[1] );

		if ( $width <= 0 || $height <= 0 ) {
			return null;
		}

		// Calculate GCD to simplify ratio
		$gcd = $this->gcd( $width, $height );
		$ratio_width = $width / $gcd;
		$ratio_height = $height / $gcd;

		// Map common ratios
		$ratio_string = $ratio_width . ':' . $ratio_height;
		$supported_ratios = array( '1:1', '3:4', '4:3', '9:16', '16:9' );

		// Return if it's a supported ratio
		if ( in_array( $ratio_string, $supported_ratios, true ) ) {
			return $ratio_string;
		}

		// Default to closest supported ratio
		$ratio = $width / $height;
		if ( abs( $ratio - 1 ) < 0.1 ) {
			return '1:1';
		} elseif ( abs( $ratio - 0.75 ) < 0.1 ) {
			return '3:4';
		} elseif ( abs( $ratio - 1.33 ) < 0.1 ) {
			return '4:3';
		} elseif ( abs( $ratio - 0.5625 ) < 0.1 ) {
			return '9:16';
		} elseif ( abs( $ratio - 1.78 ) < 0.1 ) {
			return '16:9';
		}

		// Default to 16:9 for wide images
		return $ratio >= 1 ? '16:9' : '9:16';
	}

	/**
	 * Calculate Greatest Common Divisor.
	 *
	 * @since 1.0.0
	 * @param int $a First number.
	 * @param int $b Second number.
	 * @return int GCD.
	 */
	private function gcd( $a, $b ) {
		return $b ? $this->gcd( $b, $a % $b ) : $a;
	}

	/**
	 * Make API request to Gemini with retry logic.
	 *
	 * @since 1.0.0
	 * @param array $args Request arguments.
	 * @param int   $retry_count Current retry attempt.
	 * @return array|WP_Error Response data or error.
	 */
	private function make_api_request( $args, $retry_count = 0 ) {
		$api_key = $this->get_api_key();

		$response = wp_remote_post(
			$this->api_endpoint,
			array(
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $api_key,
				),
				'body'    => wp_json_encode( $args ),
				'timeout' => 60,
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			// Retry up to 3 times with exponential backoff.
			if ( $retry_count < 3 ) {
				sleep( pow( 2, $retry_count ) );
				return $this->make_api_request( $args, $retry_count + 1 );
			}
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$data          = json_decode( $body, true );

		// Handle non-200 responses.
		if ( 200 !== $response_code ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown API error', 'featured-image-helper' );

			// Retry on server errors.
			if ( $response_code >= 500 && $retry_count < 3 ) {
				sleep( pow( 2, $retry_count ) );
				return $this->make_api_request( $args, $retry_count + 1 );
			}

			return new WP_Error( 'api_error', $error_message );
		}

		return $data;
	}

	/**
	 * Save generated image to media library.
	 *
	 * @since 1.0.0
	 * @param array   $response API response data.
	 * @param WP_Post $post Post object.
	 * @return int|WP_Error Attachment ID or error.
	 */
	private function save_generated_image( $response, $post ) {
		// Extract image data from Gemini 2.5 Flash API response.
		// Gemini 2.5 Flash format: candidates[0].content.parts[].inline_data.data
		$image_base64 = null;
		$mime_type = 'image/png';

		// Try to find the image in the response parts
		if ( isset( $response['candidates'][0]['content']['parts'] ) ) {
			foreach ( $response['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['inline_data']['data'] ) ) {
					$image_base64 = $part['inline_data']['data'];
					if ( isset( $part['inline_data']['mime_type'] ) ) {
						$mime_type = $part['inline_data']['mime_type'];
					}
					break;
				}
			}
		}

		if ( empty( $image_base64 ) ) {
			// Log the full response for debugging
			$logger = FIH_Core::get_instance()->get_logger();
			if ( $logger && get_option( 'fih_debug_logging_enabled', false ) ) {
				$logger->log_api_event( 'image_generation', 'Invalid API response structure: ' . wp_json_encode( $response ), 'error' );
			}
			return new WP_Error( 'invalid_response', __( 'Invalid image data returned from API. Please check your API key and ensure it has access to Gemini 2.5 Flash.', 'featured-image-helper' ) );
		}

		$image_data = base64_decode( $image_base64 );

		if ( empty( $image_data ) || $image_data === false ) {
			return new WP_Error( 'empty_image', __( 'Failed to decode image data from API response.', 'featured-image-helper' ) );
		}

		// Generate filename with correct extension
		$extension = 'png';
		if ( strpos( $mime_type, 'jpeg' ) !== false || strpos( $mime_type, 'jpg' ) !== false ) {
			$extension = 'jpg';
		} elseif ( strpos( $mime_type, 'webp' ) !== false ) {
			$extension = 'webp';
		}

		$filename = sanitize_file_name( $post->post_name ) . '-' . time() . '.' . $extension;
		$upload   = wp_upload_bits( $filename, null, $image_data );

		if ( $upload['error'] ) {
			return new WP_Error( 'upload_error', $upload['error'] );
		}

		// Prepare attachment data.
		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => $post->post_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert attachment.
		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post->ID );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return $attachment_id;
	}

	/**
	 * Generate image metadata (alt text, title) using Gemini.
	 *
	 * @since 1.0.0
	 * @param int     $attachment_id Attachment ID.
	 * @param WP_Post $post Post object.
	 */
	private function generate_image_metadata( $attachment_id, $post ) {
		// Generate alt text.
		$alt_text = sprintf(
			/* translators: %s: post title */
			__( 'Featured image for: %s', 'featured-image-helper' ),
			$post->post_title
		);

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

		// Add source metadata.
		update_post_meta( $attachment_id, '_fih_generated', true );
		update_post_meta( $attachment_id, '_fih_source', 'gemini' );
		update_post_meta( $attachment_id, '_fih_generated_date', current_time( 'mysql' ) );
	}

	/**
	 * Log generation attempt.
	 *
	 * @since 1.0.0
	 * @param int    $post_id Post ID.
	 * @param string $prompt Prompt used.
	 * @param string $status Status (success or error).
	 * @param string $error_message Error message if any.
	 * @param float  $generation_time Time taken in seconds.
	 */
	private function log_generation( $post_id, $prompt, $status, $error_message, $generation_time ) {
		if ( ! get_option( 'fih_debug_logging_enabled', false ) ) {
			return;
		}

		$logger = FIH_Core::get_instance()->get_logger();
		if ( $logger ) {
			$logger->log(
				$post_id,
				$prompt,
				$status,
				$error_message,
				$generation_time
			);
		}
	}

	/**
	 * Get API key.
	 *
	 * @since 1.0.0
	 * @return string API key.
	 */
	private function get_api_key() {
		return get_option( 'fih_gemini_api_key', '' );
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection() {
		$start_time = microtime( true );
		$api_key = $this->get_api_key();

		// Get logger instance for logging test results
		$logger = FIH_Core::get_instance()->get_logger();

		if ( empty( $api_key ) ) {
			$error = new WP_Error( 'no_api_key', __( 'API key is not configured.', 'featured-image-helper' ) );

			// Log the test failure
			if ( $logger ) {
				$logger->log_api_event( 'api_test', 'API test failed: No API key configured', 'error' );
			}

			return $error;
		}

		// Make a simple test request using Gemini 2.5 Flash format.
		$args = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => 'A simple blue sky with white clouds',
						),
					),
				),
			),
			'generationConfig' => array(
				'responseModalities' => array( 'Image' ),
			),
		);

		$response = $this->make_api_request( $args );
		$test_time = microtime( true ) - $start_time;

		if ( is_wp_error( $response ) ) {
			// Log the test failure
			if ( $logger ) {
				$logger->log_api_event(
					'api_test',
					'API test failed: ' . $response->get_error_message() . ' (Time: ' . number_format( $test_time, 2 ) . 's)',
					'error'
				);
			}

			return $response;
		}

		// Check if response has the expected Gemini 2.5 Flash structure
		$has_valid_image = false;
		if ( isset( $response['candidates'][0]['content']['parts'] ) ) {
			foreach ( $response['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['inline_data']['data'] ) ) {
					$has_valid_image = true;
					break;
				}
			}
		}

		if ( ! $has_valid_image ) {
			$error = new WP_Error( 'invalid_response', __( 'API returned an unexpected response format. Please verify your API key has access to Gemini 2.5 Flash for image generation.', 'featured-image-helper' ) );

			// Log the test failure
			if ( $logger ) {
				$logger->log_api_event( 'api_test', 'API test failed: Invalid response format (Time: ' . number_format( $test_time, 2 ) . 's)', 'error' );
			}

			return $error;
		}

		// Log successful test
		if ( $logger ) {
			$logger->log_api_event(
				'api_test',
				'API test successful! Gemini 2.5 Flash (nano banana) API is working correctly and can generate images. (Time: ' . number_format( $test_time, 2 ) . 's)',
				'success'
			);
		}

		return true;
	}
}
