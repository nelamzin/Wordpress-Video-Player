<?php
/**
 * Gutenberg Block Class
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the Gutenberg block for secure video player
 */
class Secure_Video_Player_Block {

	/**
	 * Initialize the block
	 */
	public function init(): void {
		// Register the block directly since we're already in the init hook
		$this->register_block();
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_assets' ) );
	}

	/**
	 * Register the Gutenberg block
	 */
	public function register_block(): void {
		register_block_type(
			'secure-video-player/video',
			array(
				'attributes'      => array(
					'videoId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render_callback' => array( $this, 'render_block' ),
				'editor_script'   => 'secure-video-player-block',
				'supports'        => array(
					'align' => array( 'wide', 'full' ),
				),
			)
		);
	}

	/**
	 * Enqueue block editor assets
	 */
	public function enqueue_block_assets(): void {
		wp_enqueue_script(
			'secure-video-player-block',
			SVP_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data' ),
			SVP_VERSION,
			true
		);

		// Localize script with video posts data
		$videos = get_posts(
			array(
				'post_type'      => 'video_player',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'meta_query'     => array(
					array(
						'key'     => '_svp_hd_url',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$video_options = array();
		foreach ( $videos as $video ) {
			$video_options[] = array(
				'value' => $video->ID,
				'label' => $video->post_title,
			);
		}

		wp_localize_script(
			'secure-video-player-block',
			'svpBlockData',
			array(
				'videos' => $video_options,
				'strings' => array(
					'title'          => __( 'Secure Video Player', 'secure-video-player' ),
					'description'    => __( 'Embed a secure video with quality options.', 'secure-video-player' ),
					'selectVideo'    => __( 'Select a video', 'secure-video-player' ),
					'noVideos'       => __( 'No videos found. Please create a video first.', 'secure-video-player' ),
					'videoSelected'  => __( 'Video selected', 'secure-video-player' ),
					'changeVideo'    => __( 'Change video', 'secure-video-player' ),
					'preview'        => __( 'Preview', 'secure-video-player' ),
				),
			)
		);
	}

	/**
	 * Render the block on frontend
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block HTML.
	 */
	public function render_block( array $attributes ): string {
		$video_id = isset( $attributes['videoId'] ) ? intval( $attributes['videoId'] ) : 0;

		if ( ! $video_id ) {
			return '';
		}

		// Use shortcode functionality to render
		$shortcode = new Secure_Video_Player_Shortcode();
		return $shortcode->render_shortcode( array( 'id' => $video_id ) );
	}
}