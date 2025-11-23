<?php
/**
 * Integration tests for Gemini API.
 *
 * These tests make real API calls and require a valid API key in .env
 * Run with: composer test -- --group integration
 *
 * @package Featured_Image_Helper
 */

namespace FIH\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integration test case for FIH_Gemini class.
 *
 * @group integration
 */
class GeminiApiIntegrationTest extends TestCase {

	/**
	 * Gemini instance.
	 *
	 * @var \FIH_Gemini
	 */
	private $gemini;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( empty( $_ENV['GEMINI_API_KEY'] ) ) {
			$this->markTestSkipped( 'GEMINI_API_KEY not set in environment. Skipping integration tests.' );
		}

		$this->gemini = new \FIH_Gemini();
	}

	/**
	 * Test API connection.
	 *
	 * This test actually calls the Gemini API to verify connectivity.
	 */
	public function test_api_connection() {
		// Override wp_remote_post to make actual HTTP request for integration tests
		global $wp_remote_post_override;
		$wp_remote_post_override = function( $url, $args = array() ) {
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $args['body'] );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: ' . $args['headers']['Content-Type'],
				'x-goog-api-key: ' . $args['headers']['x-goog-api-key'],
			) );
			curl_setopt( $ch, CURLOPT_TIMEOUT, $args['timeout'] );

			$response = curl_exec( $ch );
			$code     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$error    = curl_error( $ch );
			curl_close( $ch );

			if ( $error ) {
				return new \WP_Error( 'http_request_failed', $error );
			}

			return array(
				'response' => array( 'code' => $code ),
				'body'     => $response,
			);
		};

		$result = $this->gemini->test_connection();

		if ( is_wp_error( $result ) ) {
			// Output the actual error for debugging
			echo "\n\n=== API Connection Test Failed ===\n";
			echo "Error Code: " . $result->get_error_code() . "\n";
			echo "Error Message: " . $result->get_error_message() . "\n";
			echo "================================\n\n";

			$this->fail( 'API connection failed: ' . $result->get_error_message() );
		}

		$this->assertTrue( $result, 'API connection should return true on success' );
	}

	/**
	 * Test image generation with actual API call.
	 *
	 * @group expensive
	 */
	public function test_actual_image_generation() {
		$this->markTestSkipped( 'Skipped by default to avoid API costs. Remove this line to run.' );

		// This test is skipped by default to avoid unnecessary API calls
		// Remove the markTestSkipped line above to run this test

		// Mock required WordPress functions
		$this->mockWordPressFunctions();

		// Create a mock post
		$post               = new \stdClass();
		$post->ID           = 1;
		$post->post_title   = 'Beautiful Sunset Over Mountains';
		$post->post_excerpt = 'A stunning view of the sun setting behind mountain peaks';
		$post->post_content = 'The golden hour paints the sky with vibrant colors as the sun descends.';
		$post->post_name    = 'beautiful-sunset-mountains';

		// Test image generation
		$result = $this->gemini->generate_image( 1, 'photographic' );

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Image generation failed: ' . $result->get_error_message() );
		}

		$this->assertIsInt( $result, 'Should return attachment ID' );
		$this->assertGreaterThan( 0, $result, 'Attachment ID should be positive' );
	}

	/**
	 * Mock WordPress functions for integration tests.
	 */
	private function mockWordPressFunctions() {
		if ( ! function_exists( 'get_post' ) ) {
			function get_post( $post_id ) {
				$post               = new \stdClass();
				$post->ID           = $post_id;
				$post->post_title   = 'Beautiful Sunset Over Mountains';
				$post->post_excerpt = 'A stunning view';
				$post->post_content = 'The golden hour paints the sky.';
				$post->post_name    = 'beautiful-sunset-mountains';
				return $post;
			}
		}

		if ( ! function_exists( 'do_action' ) ) {
			function do_action( $hook, ...$args ) {
				// Mock implementation
			}
		}

		if ( ! function_exists( 'apply_filters' ) ) {
			function apply_filters( $hook, $value, ...$args ) {
				return $value;
			}
		}

		if ( ! function_exists( 'wp_strip_all_tags' ) ) {
			function wp_strip_all_tags( $string ) {
				return strip_tags( $string );
			}
		}

		if ( ! function_exists( 'wp_trim_words' ) ) {
			function wp_trim_words( $text, $num_words = 55 ) {
				$words = explode( ' ', $text );
				return implode( ' ', array_slice( $words, 0, $num_words ) );
			}
		}

		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $type ) {
				return date( 'Y-m-d H:i:s' );
			}
		}

		if ( ! function_exists( 'wp_upload_bits' ) ) {
			function wp_upload_bits( $filename, $deprecated, $bits ) {
				$upload_dir = '/tmp/';
				$file_path  = $upload_dir . $filename;
				file_put_contents( $file_path, $bits );
				return array(
					'file'  => $file_path,
					'url'   => 'http://example.com/wp-content/uploads/' . $filename,
					'error' => false,
				);
			}
		}

		if ( ! function_exists( 'wp_insert_attachment' ) ) {
			function wp_insert_attachment( $attachment, $file, $parent_id = 0 ) {
				return rand( 1000, 9999 );
			}
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			function wp_generate_attachment_metadata( $attachment_id, $file ) {
				return array(
					'width'  => 1200,
					'height' => 630,
					'file'   => basename( $file ),
				);
			}
		}

		if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
			function wp_update_attachment_metadata( $attachment_id, $data ) {
				return true;
			}
		}

		if ( ! function_exists( 'set_post_thumbnail' ) ) {
			function set_post_thumbnail( $post_id, $attachment_id ) {
				return true;
			}
		}

		if ( ! function_exists( 'update_post_meta' ) ) {
			function update_post_meta( $post_id, $key, $value ) {
				return true;
			}
		}
	}

	/**
	 * Test prompt generation output.
	 */
	public function test_prompt_generation_output() {
		$reflection = new \ReflectionClass( $this->gemini );
		$method     = $reflection->getMethod( 'build_prompt' );
		$method->setAccessible( true );

		$post               = new \stdClass();
		$post->post_title   = 'Artificial Intelligence in Modern Healthcare';
		$post->post_excerpt = 'How AI is transforming medical diagnosis';
		$post->post_content = 'Artificial intelligence is revolutionizing healthcare...';

		$prompt = $method->invoke( $this->gemini, $post, 'photographic', '' );

		echo "\n\nGenerated Prompt:\n";
		echo "=================\n";
		echo $prompt . "\n";
		echo "=================\n\n";

		$this->assertNotEmpty( $prompt );
	}

	/**
	 * Test API request structure.
	 */
	public function test_api_request_structure() {
		$reflection = new \ReflectionClass( $this->gemini );
		$method     = $reflection->getMethod( 'get_generation_args' );
		$method->setAccessible( true );

		$prompt = 'Create a photorealistic image of a mountain landscape';
		$args   = $method->invoke( $this->gemini, $prompt );

		echo "\n\nAPI Request Structure:\n";
		echo "=====================\n";
		echo json_encode( $args, JSON_PRETTY_PRINT ) . "\n";
		echo "=====================\n\n";

		$this->assertIsArray( $args );
		$this->assertArrayHasKey( 'contents', $args );
		$this->assertArrayHasKey( 'generationConfig', $args );
	}
}
