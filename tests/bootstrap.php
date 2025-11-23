<?php
/**
 * PHPUnit bootstrap file for Featured Image Helper tests.
 *
 * @package Featured_Image_Helper
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Define test constants
define('FIH_TESTS_DIR', __DIR__);
define('FIH_PLUGIN_DIR', dirname(__DIR__));

// Define WordPress constants for standalone testing
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Mock WordPress functions for testing
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        // Mock implementation - just return the value
        return $value;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Return from environment or default
        $env_key = strtoupper(str_replace('fih_', '', $option));
        return $_ENV[$env_key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        // Allow override for integration tests
        global $wp_remote_post_override;
        if (isset($wp_remote_post_override) && is_callable($wp_remote_post_override)) {
            return $wp_remote_post_override($url, $args);
        }

        // Default mock implementation
        return array();
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response) || !is_array($response)) {
            return 0;
        }
        return isset($response['response']['code']) ? $response['response']['code'] : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response) || !is_array($response)) {
            return '';
        }
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false) {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
        $string = strip_tags($string);
        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }
        return trim($string);
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null) {
        if (null === $more) {
            $more = '&hellip;';
        }
        $text = wp_strip_all_tags($text);
        $words = preg_split('/[\n\r\t ]+/', $text, $num_words + 1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > $num_words) {
            array_pop($words);
            $text = implode(' ', $words);
            $text = $text . $more;
        } else {
            $text = implode(' ', $words);
        }
        return $text;
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }

            if (isset($this->errors[$code][0])) {
                return $this->errors[$code][0];
            }

            return '';
        }

        public function get_error_code() {
            if (empty($this->errors)) {
                return '';
            }

            return key($this->errors);
        }
    }
}

// Mock FIH_Core class for tests that need it
if (!class_exists('FIH_Core')) {
    class FIH_Core {
        private static $instance = null;
        private $logger = null;

        public static function get_instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function get_logger() {
            if (is_null($this->logger)) {
                // Return a mock logger
                $this->logger = new class {
                    public function log_api_event($type, $message, $status) {
                        // Mock implementation
                        return true;
                    }
                };
            }
            return $this->logger;
        }
    }
}

// Load plugin files
require_once FIH_PLUGIN_DIR . '/includes/class-fih-gemini.php';
