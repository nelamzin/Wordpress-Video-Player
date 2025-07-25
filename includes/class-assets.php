<?php
/**
 * Assets Class
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin assets (CSS and JavaScript)
 */
class Secure_Video_Player_Assets {

	/**
	 * Initialize assets
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'svp_force_asset_localization', array( $this, 'force_localize_script' ) );
		
		// Add AJAX endpoint for nonce refresh
		add_action( 'wp_ajax_svp_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_svp_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets(): void {
		// Only enqueue if we have secure video players on the page
		if ( $this->has_video_players() ) {
			wp_enqueue_style(
				'secure-video-player',
				SVP_PLUGIN_URL . 'assets/css/player.css',
				array(),
				SVP_VERSION,
				'all'
			);

			wp_enqueue_script(
				'secure-video-player',
				SVP_PLUGIN_URL . 'assets/js/player.js',
				array(),
				SVP_VERSION,
				true
			);

			// Localize script with API endpoint and nonce
			$this->localize_player_script();
		}
	}

	/**
	 * Force localization of the script (called by shortcode when needed)
	 */
	public function force_localize_script(): void {
		if ( wp_script_is( 'secure-video-player', 'enqueued' ) ) {
			$this->localize_player_script();
		}
	}

	/**
	 * Localize the player script with consistent data
	 */
	private function localize_player_script(): void {
		// Create a single nonce for the entire page request
		static $nonce = null;
		if ( null === $nonce ) {
			$nonce = wp_create_nonce( 'svp_video_nonce' );
		}

		wp_localize_script(
			'secure-video-player',
			'svpAjax',
			array(
				'apiUrl'    => rest_url( 'secure-video/v1/token' ),
				'nonce'     => $nonce,
				'homeUrl'   => home_url(),
				'strings'   => array(
					'loading'         => __( 'Loading...', 'secure-video-player' ),
					'error'           => __( 'Error loading video', 'secure-video-player' ),
					'play'            => __( 'Play', 'secure-video-player' ),
					'pause'           => __( 'Pause', 'secure-video-player' ),
					'mute'            => __( 'Mute', 'secure-video-player' ),
					'unmute'          => __( 'Unmute', 'secure-video-player' ),
					'enterFullscreen' => __( 'Enter fullscreen', 'secure-video-player' ),
					'exitFullscreen'  => __( 'Exit fullscreen', 'secure-video-player' ),
					'qualityHD'       => __( 'HD (1080p)', 'secure-video-player' ),
					'qualitySD'       => __( 'SD (720p)', 'secure-video-player' ),
					'qualityLD'       => __( 'LD (480p)', 'secure-video-player' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only enqueue on video edit pages
		global $post_type;
		if ( 'video_player' === $post_type ) {
			if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
				wp_enqueue_style(
					'secure-video-player-admin',
					SVP_PLUGIN_URL . 'assets/css/admin.css',
					array(),
					SVP_VERSION,
					'all'
				);
			} elseif ( 'edit.php' === $hook ) {
				// Add inline script for copying shortcodes in admin list
				wp_add_inline_script( 'jquery', $this->get_admin_list_script() );
			}
		}
	}

	/**
	 * Get admin list script for copying shortcodes
	 *
	 * @return string JavaScript code.
	 */
	private function get_admin_list_script(): string {
		return "
		function copyToClipboard(text, button) {
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(() => {
					showCopySuccess(button);
				}).catch(() => {
					fallbackCopy(text, button);
				});
			} else {
				fallbackCopy(text, button);
			}
		}
		
		function fallbackCopy(text, button) {
			const textArea = document.createElement('textarea');
			textArea.value = text;
			textArea.style.position = 'fixed';
			textArea.style.opacity = '0';
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();
			try {
				document.execCommand('copy');
				showCopySuccess(button);
			} catch (err) {
				button.textContent = '" . esc_js( __( 'Failed', 'secure-video-player' ) ) . "';
				setTimeout(() => { button.textContent = '" . esc_js( __( 'Copy', 'secure-video-player' ) ) . "'; }, 2000);
			}
			document.body.removeChild(textArea);
		}
		
		function showCopySuccess(button) {
			const originalText = button.textContent;
			button.textContent = '" . esc_js( __( 'Copied!', 'secure-video-player' ) ) . "';
			button.style.backgroundColor = '#46b450';
			button.style.color = '#fff';
			setTimeout(() => {
				button.textContent = originalText;
				button.style.backgroundColor = '';
				button.style.color = '';
			}, 2000);
		}
		";
	}

	/**
	 * Check if the current page has video players
	 *
	 * @return bool Whether the page has video players.
	 */
	private function has_video_players(): bool {
		global $post;

		// Always load on video player admin pages
		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'video_player' === $screen->post_type ) {
				return true;
			}
		}

		if ( ! $post ) {
			return false;
		}

		// Check if post content contains secure_video shortcode
		if ( has_shortcode( $post->post_content, 'secure_video' ) ) {
			return true;
		}

		// Check if post content contains Gutenberg video blocks
		if ( has_blocks( $post->post_content ) ) {
			$blocks = parse_blocks( $post->post_content );
			if ( $this->has_video_blocks( $blocks ) ) {
				return true;
			}
		}

		// Check if this is a video player post type
		if ( 'video_player' === get_post_type( $post ) ) {
			return true;
		}

		// Check for widgets or other content areas that might contain shortcodes
		if ( is_active_sidebar( 'sidebar-1' ) || is_active_sidebar( 'footer-1' ) ) {
			// For performance, we'll assume any active widgets might contain video shortcodes
			// This is a safe assumption for ensuring assets are loaded when needed
			return true;
		}

		return false;
	}

	/**
	 * Recursively check for video blocks in parsed blocks
	 *
	 * @param array $blocks Array of parsed blocks.
	 * @return bool Whether video blocks are found.
	 */
	private function has_video_blocks( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( 'secure-video-player/video' === $block['blockName'] ) {
				return true;
			}
			
			// Check inner blocks recursively
			if ( ! empty( $block['innerBlocks'] ) && $this->has_video_blocks( $block['innerBlocks'] ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * AJAX handler for nonce refresh
	 */
	public function ajax_refresh_nonce(): void {
		// Create a new nonce
		$new_nonce = wp_create_nonce( 'svp_video_nonce' );
		
		wp_send_json_success( array(
			'nonce' => $new_nonce,
			'timestamp' => time()
		) );
	}
}