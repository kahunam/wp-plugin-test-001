<?php
/**
 * Test actual image generation workflow as it would work in WordPress
 */

// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Load test bootstrap
require_once __DIR__ . '/tests/bootstrap.php';

// Create output directory
$output_dir = __DIR__ . '/test-images';
if (!file_exists($output_dir)) {
    mkdir($output_dir, 0755, true);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  FEATURED IMAGE HELPER - GENERATION TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Get the test post title from .env
$post_title = $_ENV['TEST_POST_TITLE'] ?? 'Royal Mail fined £20,000 under PECR for marketing automation gone wrong';

// Create a mock WordPress post
$post = new stdClass();
$post->ID = 1;
$post->post_title = $post_title;
$post->post_excerpt = 'The UK data protection authority has imposed a fine on Royal Mail for sending unsolicited marketing emails without proper consent, highlighting the risks of automated marketing systems.';
$post->post_content = 'Royal Mail, one of the UK\'s largest postal service providers, has been fined £20,000 by the Information Commissioner\'s Office (ICO) for breaching the Privacy and Electronic Communications Regulations (PECR). The fine stems from the company\'s automated marketing system sending promotional emails to customers who had not consented to receive them. According to the ICO\'s investigation, Royal Mail\'s email marketing automation tool failed to properly verify customer consent before sending marketing communications. The issue affected thousands of customers who received emails despite having previously opted out or never opted in to marketing communications. This case serves as a reminder to organizations about the importance of properly configuring and monitoring automated marketing systems to ensure compliance with data protection regulations.';
$post->post_name = 'royal-mail-fined-pecr-marketing-automation';

echo "Test Post Details:\n";
echo "Title: $post->post_title\n";
echo "Excerpt: " . substr($post->post_excerpt, 0, 100) . "...\n\n";

// Test with different prompt styles
$styles = ['photographic', 'illustration', 'abstract', 'minimal'];
$content_sources = ['title', 'excerpt', 'content'];

echo "Choose content source:\n";
foreach ($content_sources as $i => $source) {
    echo ($i + 1) . ". $source\n";
}
echo "\nEnter choice (1-3, default=1): ";
$choice = trim(fgets(STDIN));
$content_source = $content_sources[empty($choice) ? 0 : max(0, min(2, (int)$choice - 1))];

echo "\nChoose prompt style:\n";
foreach ($styles as $i => $style) {
    echo ($i + 1) . ". $style\n";
}
echo "\nEnter choice (1-4, default=1): ";
$choice = trim(fgets(STDIN));
$style = $styles[empty($choice) ? 0 : max(0, min(3, (int)$choice - 1))];

// Set environment variable to simulate WordPress option
$_ENV['CONTENT_SOURCE'] = $content_source;

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Configuration:\n";
echo "  Content Source: $content_source\n";
echo "  Prompt Style: $style\n";
echo "  Aspect Ratio: 16:9\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Override wp_remote_post FIRST before any API calls
global $wp_remote_post_override;
$wp_remote_post_override = function($url, $args = array()) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);

    $headers = array();
    foreach ($args['headers'] as $key => $value) {
        $headers[] = "$key: $value";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout'] ?? 30);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return new WP_Error('http_request_failed', $error);
    }

    return array(
        'response' => array('code' => $code),
        'body' => $response,
    );
};

// Create Gemini instance
$gemini = new FIH_Gemini();
$reflection = new ReflectionClass($gemini);

// Step 1: Generate visual concept
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 1: Semantic Analysis & Visual Concept\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$generate_concept_method = $reflection->getMethod('generate_visual_concept');
$generate_concept_method->setAccessible(true);

// Get the content based on source
$raw_content = ($content_source === 'title') ? $post->post_title :
               (($content_source === 'excerpt') ? $post->post_excerpt :
               substr($post->post_content, 0, 200));

echo "Original Content: $raw_content\n\n";
echo "Analyzing and generating visual concept...\n";

$visual_concept = $generate_concept_method->invoke($gemini, $raw_content);

if (is_wp_error($visual_concept)) {
    echo "⚠ Concept generation failed: " . $visual_concept->get_error_message() . "\n";
    echo "Falling back to literal content...\n\n";
    $visual_concept = $raw_content;
} else {
    echo "\n✅ Visual Concept Generated:\n";
    echo "┌" . str_repeat("─", 78) . "┐\n";
    $wrapped = wordwrap($visual_concept, 76);
    foreach (explode("\n", $wrapped) as $line) {
        echo "│ " . str_pad($line, 77) . "│\n";
    }
    echo "└" . str_repeat("─", 78) . "┘\n\n";
}

// Step 2: Build full prompt with SPLICE template
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 2: SPLICE Template Application\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$build_prompt_method = $reflection->getMethod('build_prompt');
$build_prompt_method->setAccessible(true);
$prompt = $build_prompt_method->invoke($gemini, $post, $style, '');

echo "Final Prompt:\n";
echo "┌" . str_repeat("─", 78) . "┐\n";
$wrapped = wordwrap($prompt, 76);
foreach (explode("\n", $wrapped) as $line) {
    echo "│ " . str_pad($line, 77) . "│\n";
}
echo "└" . str_repeat("─", 78) . "┘\n\n";

// Build API request
$args = array(
    'contents' => array(
        array(
            'parts' => array(
                array('text' => $prompt),
            ),
        ),
    ),
    'generationConfig' => array(
        'responseModalities' => array('Image'),
        'imageConfig' => array(
            'aspectRatio' => '16:9',
        ),
    ),
);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "STEP 3: Image Generation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "Sending request to Gemini Image API...\n";

// Make the API request
$start_time = microtime(true);
$make_request_method = $reflection->getMethod('make_api_request');
$make_request_method->setAccessible(true);
$response = $make_request_method->invoke($gemini, $args);

if (is_wp_error($response)) {
    echo "❌ Error: " . $response->get_error_message() . "\n";
    exit(1);
}

// Extract image data
$image_data = null;
$mime_type = 'image/png';
if (isset($response['candidates'][0]['content']['parts'])) {
    foreach ($response['candidates'][0]['content']['parts'] as $part) {
        if (isset($part['inlineData']['data'])) {
            $image_data = $part['inlineData']['data'];
            $mime_type = $part['inlineData']['mimeType'] ?? 'image/png';
            break;
        }
    }
}

if (!$image_data) {
    echo "❌ Error: No image data found in response\n";
    exit(1);
}

// Decode and save image
$image_binary = base64_decode($image_data);
$extension = ($mime_type === 'image/jpeg') ? 'jpg' : 'png';
$filename = $style . '-' . $content_source . '-' . date('YmdHis') . '.' . $extension;
$filepath = $output_dir . '/' . $filename;

file_put_contents($filepath, $image_binary);

$total_time = microtime(true) - $start_time;
$file_size = filesize($filepath);
$dimensions = getimagesize($filepath);
$actual_ratio = round($dimensions[0] / $dimensions[1], 2);
$expected_ratio = round(16/9, 2);

echo "✅ Image Generated Successfully!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "File: $filename\n";
echo "Path: $filepath\n";
echo "Size: " . number_format($file_size / 1024, 2) . " KB\n";
echo "Dimensions: {$dimensions[0]} x {$dimensions[1]} pixels\n";
echo "Aspect Ratio: $actual_ratio (expected: $expected_ratio) ";
echo ($actual_ratio == $expected_ratio ? "✓" : "⚠") . "\n";
echo "Generation Time: " . number_format($total_time, 2) . " seconds\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\nTo view the image:\n";
echo "  open $filepath\n\n";
