<?php
/**
 * Tests for the Gemini API Integration.
 *
 * @package Featured_Image_Helper
 */

namespace FIH\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Test case for FIH_Gemini class.
 */
class GeminiApiTest extends TestCase {

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
		$this->gemini = new \FIH_Gemini();
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test that API key is loaded from environment.
	 */
	public function test_api_key_loaded_from_environment() {
		$this->assertNotEmpty( $_ENV['GEMINI_API_KEY'], 'GEMINI_API_KEY should be set in .env file' );
	}

	/**
	 * Test aspect ratio conversion for common sizes.
	 *
	 * @dataProvider aspect_ratio_provider
	 */
	public function test_aspect_ratio_conversion( $input, $expected ) {
		$reflection = new \ReflectionClass( $this->gemini );
		$method     = $reflection->getMethod( 'convert_size_to_aspect_ratio' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->gemini, $input );

		$this->assertEquals( $expected, $result, "Failed to convert $input to correct aspect ratio" );
	}

	/**
	 * Data provider for aspect ratio tests.
	 *
	 * @return array
	 */
	public function aspect_ratio_provider() {
		return array(
			'1:1 square'          => array( '1200x1200', '1:1' ),
			'16:9 landscape'      => array( '1920x1080', '16:9' ),
			'9:16 portrait'       => array( '1080x1920', '9:16' ),
			'4:3 classic'         => array( '1600x1200', '4:3' ),
			'3:4 portrait'        => array( '1200x1600', '3:4' ),
			'16:9 social'         => array( '1200x630', '16:9' ),
			'1:1 from different'  => array( '800x800', '1:1' ),
			'invalid format'      => array( 'invalid', null ),
			'single dimension'    => array( '1200', null ),
		);
	}

	/**
	 * Test GCD calculation.
	 */
	public function test_gcd_calculation() {
		$reflection = new \ReflectionClass( $this->gemini );
		$method     = $reflection->getMethod( 'gcd' );
		$method->setAccessible( true );

		$this->assertEquals( 4, $method->invoke( $this->gemini, 8, 12 ) );
		$this->assertEquals( 5, $method->invoke( $this->gemini, 10, 15 ) );
		$this->assertEquals( 1, $method->invoke( $this->gemini, 7, 13 ) );
		$this->assertEquals( 100, $method->invoke( $this->gemini, 100, 200 ) );
	}

	/**
	 * Test generation args structure.
	 */
	public function test_generation_args_structure() {
		$reflection = new \ReflectionClass( $this->gemini );
		$method     = $reflection->getMethod( 'get_generation_args' );
		$method->setAccessible( true );

		$prompt = 'Test prompt for image generation';
		$args   = $method->invoke( $this->gemini, $prompt );

		// Test basic structure
		$this->assertIsArray( $args );
		$this->assertArrayHasKey( 'contents', $args );
		$this->assertArrayHasKey( 'generationConfig', $args );

		// Test contents structure
		$this->assertIsArray( $args['contents'] );
		$this->assertCount( 1, $args['contents'] );
		$this->assertArrayHasKey( 'parts', $args['contents'][0] );
		$this->assertEquals( $prompt, $args['contents'][0]['parts'][0]['text'] );

		// Test generation config
		$this->assertArrayHasKey( 'responseModalities', $args['generationConfig'] );
		$this->assertEquals( array( 'Image' ), $args['generationConfig']['responseModalities'] );
	}

	/**
	 * Test prompt building with different content sources.
	 */
	public function test_prompt_building_with_title() {
		$reflection = new \ReflectionClass( $this->gemini );
		$method     = $reflection->getMethod( 'build_prompt' );
		$method->setAccessible( true );

		// Mock post object
		$post              = new \stdClass();
		$post->post_title  = 'Test Post Title';
		$post->post_excerpt = 'This is a test excerpt';
		$post->post_content = 'This is the full post content with more details.';

		// Test with title as content source
		$_ENV['CONTENT_SOURCE'] = 'title';
		$prompt = $method->invoke( $this->gemini, $post, 'photographic', '' );

		$this->assertStringContainsString( 'Test Post Title', $prompt );
		$this->assertStringContainsString( 'photorealistic', $prompt );
	}

	/**
	 * Test custom prompt override.
	 */
	public function test_custom_prompt_override() {
		$reflection = new \ReflectionClass( $this->gemini );
		$method     = $reflection->getMethod( 'build_prompt' );
		$method->setAccessible( true );

		$post               = new \stdClass();
		$post->post_title   = 'Test Post Title';
		$post->post_excerpt = 'Test excerpt';
		$post->post_content = 'Test content';

		$custom_prompt = 'Custom prompt that should override everything';
		$prompt        = $method->invoke( $this->gemini, $post, 'photographic', $custom_prompt );

		$this->assertEquals( $custom_prompt, $prompt );
	}

	/**
	 * Test prompt templates exist.
	 */
	public function test_prompt_templates_exist() {
		$reflection = new \ReflectionClass( $this->gemini );
		$property   = $reflection->getProperty( 'prompt_templates' );
		$property->setAccessible( true );
		$templates = $property->getValue( $this->gemini );

		$this->assertIsArray( $templates );
		$this->assertArrayHasKey( 'photographic', $templates );
		$this->assertArrayHasKey( 'illustration', $templates );
		$this->assertArrayHasKey( 'abstract', $templates );
		$this->assertArrayHasKey( 'minimal', $templates );

		// Test template contains placeholder
		foreach ( $templates as $template ) {
			$this->assertStringContainsString( '{content}', $template );
		}
	}

	/**
	 * Test API endpoint is correct.
	 */
	public function test_api_endpoint_is_correct() {
		$reflection = new \ReflectionClass( $this->gemini );
		$property   = $reflection->getProperty( 'api_endpoint' );
		$property->setAccessible( true );
		$endpoint = $property->getValue( $this->gemini );

		$this->assertStringContainsString( 'generativelanguage.googleapis.com', $endpoint );
		$this->assertStringContainsString( 'gemini-2.5-flash-image', $endpoint );
		$this->assertStringContainsString( 'generateContent', $endpoint );
	}
}
