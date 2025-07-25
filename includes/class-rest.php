<?php
/**
 * REST API Class
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for secure video streaming
 */
class Secure_Video_Player_REST {

	/**
	 * Initialize REST API
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes(): void {
		register_rest_route(
			'secure-video/v1',
			'/token',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_video_token' ),
				'permission_callback' => array( $this, 'check_token_permissions' ),
				'args'                => array(
					'post'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => array( $this, 'validate_post_id' ),
					),
					'quality' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( $this, 'validate_quality' ),
					),
					'nonce'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permissions for token endpoint
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return bool|WP_Error Whether the user has permission.
	 */
	public function check_token_permissions( $request ) {
		// Verify nonce
		$nonce = $request->get_param( 'nonce' );
		
		// Log nonce verification for debugging (remove in production)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SVP: Nonce verification - Received: ' . $nonce );
		}
		
		if ( ! $nonce ) {
			return new WP_Error( 'missing_nonce', __( 'Missing nonce parameter.', 'secure-video-player' ), array( 'status' => 400 ) );
		}
		
		if ( ! wp_verify_nonce( $nonce, 'svp_video_nonce' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce. Please refresh the page and try again.', 'secure-video-player' ), array( 'status' => 403 ) );
		}

		// Rate limiting check (basic implementation)
		$ip = $this->get_client_ip();
		$transient_key = 'svp_rate_limit_' . md5( $ip );
		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
		} else {
			$requests++;
			if ( $requests > 60 ) { // Max 60 requests per minute
				return new WP_Error( 'rate_limit_exceeded', __( 'Rate limit exceeded. Please try again later.', 'secure-video-player' ), array( 'status' => 429 ) );
			}
			set_transient( $transient_key, $requests, MINUTE_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Get video token
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error The response.
	 */
	public function get_video_token( $request ) {
		$post_id = $request->get_param( 'post' );
		$quality = $request->get_param( 'quality' );

		// Verify post exists and is a video
		$post = get_post( $post_id );
		if ( ! $post || 'video_player' !== $post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Invalid video post.', 'secure-video-player' ), array( 'status' => 404 ) );
		}

		// Check if user can view this video
		if ( ! $this->can_view_video( $post_id ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'Insufficient permissions.', 'secure-video-player' ), array( 'status' => 403 ) );
		}

		// Get video URL for the requested quality
		$video_url = $this->get_video_url( $post_id, $quality );
		if ( ! $video_url ) {
			return new WP_Error( 'video_not_found', __( 'Video not found for requested quality.', 'secure-video-player' ), array( 'status' => 404 ) );
		}

		// Generate JWT token
		$token = $this->generate_jwt_token( $post_id, $quality, $video_url );

		// Return secure streaming URL
		$streaming_url = add_query_arg(
			'jwt',
			$token,
			home_url( '/wp-content/plugins/secure-video-player/serve-video.php' )
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'url'     => $streaming_url,
				'expires' => time() + 60, // 60 seconds TTL
			),
			200
		);
	}

	/**
	 * Validate post ID
	 *
	 * @param mixed $value The value to validate.
	 * @return bool Whether the value is valid.
	 */
	public function validate_post_id( $value ): bool {
		return is_numeric( $value ) && $value > 0;
	}

	/**
	 * Validate quality parameter
	 *
	 * @param mixed $value The value to validate.
	 * @return bool Whether the value is valid.
	 */
	public function validate_quality( $value ): bool {
		return in_array( $value, array( 'hd', 'sd', 'ld' ), true );
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

	/**
	 * Get video URL for quality
	 *
	 * @param int    $post_id The post ID.
	 * @param string $quality The quality (hd, sd, ld).
	 * @return string|false The video URL or false if not found.
	 */
	private function get_video_url( int $post_id, string $quality ) {
		$meta_key = '_svp_' . $quality . '_url';
		$url = get_post_meta( $post_id, $meta_key, true );

		return ! empty( $url ) ? $url : false;
	}

	/**
	 * Generate JWT token
	 *
	 * @param int    $post_id The post ID.
	 * @param string $quality The quality.
	 * @param string $video_url The video URL.
	 * @return string The JWT token.
	 */
	private function generate_jwt_token( int $post_id, string $quality, string $video_url ): string {
		$payload = array(
			'video_id' => $post_id,
			'quality'  => $quality,
			'url'      => $video_url,
			'exp'      => time() + 60, // Expires in 60 seconds
			'iat'      => time(),
			'ip'       => $this->get_client_ip(),
			'user_id'  => get_current_user_id(),
		);

		// Simple JWT-like token (for production, use a proper JWT library)
		$header = base64_encode( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'HS256' ) ) );
		$payload_encoded = base64_encode( wp_json_encode( $payload ) );
		$signature = hash_hmac( 'sha256', $header . '.' . $payload_encoded, $this->get_secret_key() );

		return $header . '.' . $payload_encoded . '.' . $signature;
	}

	/**
	 * Get client IP address
	 *
	 * @return string The client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	}

	/**
	 * Get secret key for JWT signing
	 *
	 * @return string The secret key.
	 */
	private function get_secret_key(): string {
		// Use WordPress auth key/salt or generate one
		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'secure-video-player-secret';
		return $key . get_option( 'svp_secret_salt', wp_generate_password( 64, true, true ) );
	}
}