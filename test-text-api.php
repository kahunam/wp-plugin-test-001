<?php
/**
 * Test text API endpoint
 */

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$api_key = $_ENV['GEMINI_API_KEY'];
$model = 'gemini-2.5-flash';
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";

echo "Testing Gemini Text API...\n";
echo "Endpoint: $endpoint\n";
echo "API Key: " . substr($api_key, 0, 10) . "...\n\n";

$request_body = array(
    'contents' => array(
        array(
            'parts' => array(
                array('text' => 'Say hello in exactly 3 words.'),
            ),
        ),
    ),
);

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'x-goog-api-key: ' . $api_key,
));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Sending request...\n";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";

if ($error) {
    echo "cURL Error: $error\n";
    exit(1);
}

echo "\nRaw Response:\n";
echo "=============\n";
echo $response . "\n";
echo "=============\n\n";

$data = json_decode($response, true);

if ($http_code === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    echo "✅ Success!\n";
    echo "Response: " . $data['candidates'][0]['content']['parts'][0]['text'] . "\n";
} else {
    echo "❌ Failed\n";
    if (isset($data['error'])) {
        print_r($data['error']);
    }
}
