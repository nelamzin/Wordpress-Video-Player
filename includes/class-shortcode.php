<?php
/**
 * Shortcode Class
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the secure_video shortcode
 */
class Secure_Video_Player_Shortcode {

	/**
	 * Initialize the shortcode
	 */
	public function init(): void {
		add_shortcode( 'secure_video', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string The rendered shortcode HTML.
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'autoplay' => 'false',
				'preload'  => 'metadata',
				'class'    => '',
			),
			$atts,
			'secure_video'
		);

		$video_id = intval( $atts['id'] );

		if ( ! $video_id || ! $this->can_view_video( $video_id ) ) {
			return '';
		}

		// Get video metadata
		$hd_url = get_post_meta( $video_id, '_svp_hd_url', true );
		$sd_url = get_post_meta( $video_id, '_svp_sd_url', true );
		$ld_url = get_post_meta( $video_id, '_svp_ld_url', true );

		if ( empty( $hd_url ) ) {
			return '';
		}

		// Get video title and poster
		$video_post = get_post( $video_id );
		$video_title = $video_post ? esc_attr( $video_post->post_title ) : '';
		$poster_url = get_the_post_thumbnail_url( $video_id, 'large' );

		// Prepare data attributes for JavaScript
		$data_attrs = array(
			'data-video-id'  => $video_id,
			'data-autoplay'  => 'true' === $atts['autoplay'] ? 'true' : 'false',
			'data-preload'   => esc_attr( $atts['preload'] ),
			'data-title'     => $video_title,
		);

		// Add quality URLs as data attributes (they will be replaced with token URLs by JS)
		if ( ! empty( $hd_url ) ) {
			$data_attrs['data-src-hd'] = esc_url( $hd_url );
		}
		if ( ! empty( $sd_url ) ) {
			$data_attrs['data-src-sd'] = esc_url( $sd_url );
		}
		if ( ! empty( $ld_url ) ) {
			$data_attrs['data-src-ld'] = esc_url( $ld_url );
		}

		// Add poster if available
		if ( $poster_url ) {
			$data_attrs['data-poster'] = esc_url( $poster_url );
		}

		$css_classes = 'secure-video-player';
		if ( ! empty( $atts['class'] ) ) {
			$css_classes .= ' ' . esc_attr( $atts['class'] );
		}

		// Build data attributes string
		$data_attrs_string = '';
		foreach ( $data_attrs as $key => $value ) {
			$data_attrs_string .= sprintf( ' %s="%s"', $key, $value );
		}

		// Include the player template
		ob_start();
		include SVP_PLUGIN_DIR . 'templates/player.php';
		$output = ob_get_clean();

		// Ensure assets are enqueued
		if ( ! wp_script_is( 'secure-video-player', 'enqueued' ) ) {
			wp_enqueue_style( 'secure-video-player' );
			wp_enqueue_script( 'secure-video-player' );
			
			// Manually localize script if not already done
			if ( ! wp_script_is( 'secure-video-player', 'done' ) ) {
				wp_localize_script(
					'secure-video-player',
					'svpAjax',
					array(
						'apiUrl'    => rest_url( 'secure-video/v1/token' ),
						'nonce'     => wp_create_nonce( 'svp_video_nonce' ),
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
		}

		return $output;
	}

	/**
	 * Check if current user can view the video
	 *
	 * @param int $video_id The video post ID.
	 * @return bool Whether the user can view the video.
	 */
	private function can_view_video( int $video_id ): bool {
		$post = get_post( $video_id );

		if ( ! $post || 'video_player' !== $post->post_type ) {
			return false;
		}

		// Check if video is published or if user has permission to view private posts
		if ( 'publish' === $post->post_status ) {
			return true;
		}

		// For non-published videos, check if user has read_private_posts capability
		return current_user_can( 'read_private_posts' ) || current_user_can( 'manage_secure_videos' );
	}
}