<?php
/**
 * Plugin Loader Class
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main loader class that initializes all plugin components
 */
class Secure_Video_Player_Loader {

	/**
	 * Initialize the plugin
	 */
	public function run(): void {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required dependencies
	 */
	private function load_dependencies(): void {
		require_once SVP_PLUGIN_DIR . 'includes/class-cpt.php';
		require_once SVP_PLUGIN_DIR . 'includes/class-shortcode.php';
		require_once SVP_PLUGIN_DIR . 'includes/class-rest.php';
		require_once SVP_PLUGIN_DIR . 'includes/class-assets.php';
		require_once SVP_PLUGIN_DIR . 'includes/class-block.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'init_components' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize plugin components
	 */
	public function init_components(): void {
		// Initialize Custom Post Type
		$cpt = new Secure_Video_Player_CPT();
		$cpt->init();

		// Initialize Shortcode
		$shortcode = new Secure_Video_Player_Shortcode();
		$shortcode->init();

		// Initialize REST API
		$rest = new Secure_Video_Player_REST();
		$rest->init();

		// Initialize Assets
		$assets = new Secure_Video_Player_Assets();
		$assets->init();

		// Initialize Gutenberg Block
		if ( function_exists( 'register_block_type' ) ) {
			$block = new Secure_Video_Player_Block();
			$block->init();
		}
	}

	/**
	 * Load plugin textdomain for internationalization
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'secure-video-player',
			false,
			dirname( SVP_PLUGIN_BASENAME ) . '/languages'
		);
	}
}