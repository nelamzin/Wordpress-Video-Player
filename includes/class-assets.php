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
		add_action( 'wp_footer', array( $this, 'add_nonce_script' ) );
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
			wp_localize_script(
				'secure-video-player',
				'svpAjax',
				array(
					'apiUrl'    => rest_url( 'secure-video/v1/token' ),
					'nonce'     => wp_create_nonce( 'svp_video_nonce' ),
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
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only enqueue on video edit pages
		global $post_type;
		if ( 'video_player' === $post_type && ( 'post.php' === $hook || 'post-new.php' === $hook ) ) {
			wp_enqueue_style(
				'secure-video-player-admin',
				SVP_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				SVP_VERSION,
				'all'
			);
		}
	}

	/**
	 * Add nonce script to footer
	 */
	public function add_nonce_script(): void {
		if ( $this->has_video_players() ) {
			?>
			<script type="text/javascript">
				window.svpNonce = '<?php echo esc_js( wp_create_nonce( 'svp_video_nonce' ) ); ?>';
			</script>
			<?php
		}
	}

	/**
	 * Check if the current page has video players
	 *
	 * @return bool Whether the page has video players.
	 */
	private function has_video_players(): bool {
		global $post;

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
			foreach ( $blocks as $block ) {
				if ( 'secure-video-player/video' === $block['blockName'] ) {
					return true;
				}
			}
		}

		return false;
	}
}