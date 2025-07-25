<?php
/**
 * Plugin Name: Secure Multi-Quality Video Player
 * Plugin URI: https://github.com/nelamzin/Wordpress-Video-Player
 * Description: A lightweight, secure, cross-browser HTML5 video player plugin that lets editors embed videos in three MP4 quality variants (High = 1080p, Medium = 720p, Low = 480p) via shortcode or Gutenberg block.
 * Version: 1.0.0
 * Author: WordPress Video Player Team
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: secure-video-player
 * Domain Path: /languages
 * Requires at least: 6.7
 * Tested up to: 6.8.2
 * Requires PHP: 8.1
 * Network: false
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SVP_VERSION', '1.0.0' );
define( 'SVP_PLUGIN_FILE', __FILE__ );
define( 'SVP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SVP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SVP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require the loader class
require_once SVP_PLUGIN_DIR . 'includes/class-loader.php';

/**
 * Initialize the plugin
 */
function secure_video_player_init() {
	$loader = new Secure_Video_Player_Loader();
	$loader->run();
}

// Hook into WordPress
add_action( 'plugins_loaded', 'secure_video_player_init' );

/**
 * Activation hook
 */
function secure_video_player_activate() {
	// Generate secret salt if it doesn't exist
	if ( ! get_option( 'svp_secret_salt' ) ) {
		update_option( 'svp_secret_salt', wp_generate_password( 64, true, true ) );
	}
	
	// Initialize the plugin to register CPT before flushing rules
	secure_video_player_init();
	
	// Flush rewrite rules to ensure custom post type URLs work
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'secure_video_player_activate' );

/**
 * Deactivation hook
 */
function secure_video_player_deactivate() {
	// Flush rewrite rules on deactivation
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'secure_video_player_deactivate' );