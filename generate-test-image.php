<?php
/**
 * Generate a test image using Gemini API
 */

// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Load test bootstrap to get WordPress mocks
require_once __DIR__ . '/tests/bootstrap.php';

// Create output directory
$output_dir = __DIR__ . '/test-images';
if (!file_exists($output_dir)) {
    mkdir($output_dir, 0755, true);
}

echo "Generating test image using Gemini 2.5 Flash API...\n\n";

// Create Gemini instance
$gemini = new FIH_Gemini();

// Define the prompt
$prompts = [
    'A serene mountain landscape at sunset with vibrant orange and purple skies',
    'A futuristic cityscape with flying cars and neon lights',
    'A cozy coffee shop interior with warm lighting and people working on laptops',
    'An astronaut exploring a colorful alien planet with strange plants',
    'A beautiful underwater scene with coral reefs and tropical fish',
];

echo "Choose a prompt:\n";
foreach ($prompts as $i => $prompt) {
    echo ($i + 1) . ". $prompt\n";
}
echo "\nOr press Enter to use prompt 1: ";

$choice = trim(fgets(STDIN));
$choice = empty($choice) ? 1 : (int)$choice;
$choice = max(1, min(count($prompts), $choice));

$prompt = $prompts[$choice - 1];

echo "\nGenerating image with prompt:\n\"$prompt\"\n\n";

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

// Override wp_remote_post to make real HTTP request
global $wp_remote_post_override;
$wp_remote_post_override = function($url, $args = array()) {
    echo "Making API request to: " . parse_url($url, PHP_URL_HOST) . "\n";
    echo "Request size: " . strlen($args['body']) . " bytes\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: ' . $args['headers']['Content-Type'],
        'x-goog-api-key: ' . $args['headers']['x-goog-api-key'],
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout']);

    $start = microtime(true);
    $response = curl_exec($ch);
    $duration = microtime(true) - $start;

    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "Response received in " . number_format($duration, 2) . " seconds\n";
    echo "HTTP Status: $code\n";

    if ($error) {
        return new WP_Error('http_request_failed', $error);
    }

    return array(
        'response' => array('code' => $code),
        'body' => $response,
    );
};

// Make the API request using reflection to access private method
$start_time = microtime(true);
$reflection = new ReflectionClass($gemini);
$method = $reflection->getMethod('make_api_request');
$method->setAccessible(true);
$response = $method->invoke($gemini, $args);

if (is_wp_error($response)) {
    echo "\nError: " . $response->get_error_message() . "\n";
    exit(1);
}

// Extract image data
$image_data = null;
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
    echo "\nError: No image data found in response\n";
    echo "Response structure:\n";
    print_r($response);
    exit(1);
}

// Decode and save image
$image_binary = base64_decode($image_data);
$extension = ($mime_type === 'image/jpeg') ? 'jpg' : 'png';
$filename = 'gemini-' . date('Y-m-d-His') . '.' . $extension;
$filepath = $output_dir . '/' . $filename;

file_put_contents($filepath, $image_binary);

$total_time = microtime(true) - $start_time;
$file_size = filesize($filepath);

echo "\n✅ Success!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Image saved to: $filepath\n";
echo "File size: " . number_format($file_size / 1024, 2) . " KB\n";
echo "Total time: " . number_format($total_time, 2) . " seconds\n";
echo "Dimensions: " . getimagesize($filepath)[0] . " x " . getimagesize($filepath)[1] . " pixels\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\nTo view the image:\n";
echo "  open $filepath\n";
