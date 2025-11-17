<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 *
 * @since 1.0.0
 */
class FIH_Core {

	/**
	 * The single instance of the class.
	 *
	 * @var FIH_Core
	 */
	protected static $instance = null;

	/**
	 * Admin class instance.
	 *
	 * @var FIH_Admin
	 */
	public $admin;

	/**
	 * Gemini API class instance.
	 *
	 * @var FIH_Gemini
	 */
	public $gemini;

	/**
	 * Queue class instance.
	 *
	 * @var FIH_Queue
	 */
	public $queue;

	/**
	 * Logger class instance.
	 *
	 * @var FIH_Logger
	 */
	public $logger;

	/**
	 * Settings class instance.
	 *
	 * @var FIH_Settings
	 */
	public $settings;

	/**
	 * Main FIH_Core Instance.
	 *
	 * Ensures only one instance of FIH_Core is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return FIH_Core - Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Initialize logger first as other classes may use it.
		$this->logger = new FIH_Logger();

		// Initialize other classes.
		$this->gemini   = new FIH_Gemini();
		$this->queue    = new FIH_Queue();
		$this->settings = new FIH_Settings();

		// Initialize admin only in admin area.
		if ( is_admin() ) {
			$this->admin = new FIH_Admin();
		}
	}

	/**
	 * Run the plugin.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Plugin is initialized via init hook.
	}

	/**
	 * Add custom cron interval for queue processing.
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_interval( $schedules ) {
		$interval = get_option( 'fih_queue_interval', 5 );

		$schedules['fih_queue_interval'] = array(
			'interval' => $interval * 60,
			'display'  => sprintf(
				/* translators: %d: interval in minutes */
				__( 'Every %d Minutes', 'featured-image-helper' ),
				$interval
			),
		);

		return $schedules;
	}

	/**
	 * Get the Gemini API instance.
	 *
	 * @since 1.0.0
	 * @return FIH_Gemini
	 */
	public function get_gemini() {
		return $this->gemini;
	}

	/**
	 * Get the Queue instance.
	 *
	 * @since 1.0.0
	 * @return FIH_Queue
	 */
	public function get_queue() {
		return $this->queue;
	}

	/**
	 * Get the Logger instance.
	 *
	 * @since 1.0.0
	 * @return FIH_Logger
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Get the Settings instance.
	 *
	 * @since 1.0.0
	 * @return FIH_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get the Admin instance.
	 *
	 * @since 1.0.0
	 * @return FIH_Admin|null
	 */
	public function get_admin() {
		return $this->admin;
	}
}
