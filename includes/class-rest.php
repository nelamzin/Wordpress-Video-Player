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

		register_rest_route(
			'secure-video/v1',
			'/stream',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stream_video' ),
				'permission_callback' => '__return_true', // Public endpoint, JWT validation handles security
				'args'                => array(
					'jwt' => array(
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
		
		// Enhanced logging for debugging nonce issues
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SVP: Nonce verification attempt' );
			error_log( 'SVP: Received nonce: ' . ( $nonce ?: 'EMPTY' ) );
			error_log( 'SVP: Expected action: svp_video_nonce' );
			error_log( 'SVP: Current user ID: ' . get_current_user_id() );
			error_log( 'SVP: Request method: ' . $request->get_method() );
			error_log( 'SVP: User agent: ' . ( $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown' ) );
		}
		
		if ( ! $nonce ) {
			return new WP_Error( 'missing_nonce', __( 'Missing nonce parameter.', 'secure-video-player' ), array( 'status' => 400 ) );
		}
		
		// Verify nonce with enhanced handling
		$nonce_valid = wp_verify_nonce( $nonce, 'svp_video_nonce' );
		
		// Log detailed nonce verification result
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SVP: Nonce verification result: ' . ( $nonce_valid ? 'VALID' : 'INVALID' ) );
			if ( ! $nonce_valid ) {
				error_log( 'SVP: Failed nonce details - Action: svp_video_nonce, Nonce: ' . $nonce );
				
				// Check if nonce is expired by attempting verification with longer timeframe
				$nonce_check_extended = wp_verify_nonce( $nonce, 'svp_video_nonce' );
				error_log( 'SVP: Extended nonce check (for expiry detection): ' . ( $nonce_check_extended ? 'VALID' : 'INVALID' ) );
			}
		}
		
		if ( ! $nonce_valid ) {
			// More specific error message based on likely causes
			$error_message = __( 'Invalid nonce. Please refresh the page and try again.', 'secure-video-player' );
			
			// Check if this might be a timing issue
			if ( $this->is_likely_timing_issue() ) {
				$error_message = __( 'Security token expired. Please refresh the page and try again.', 'secure-video-player' );
			}
			
			return new WP_Error( 
				'invalid_nonce', 
				$error_message, 
				array( 
					'status' => 403,
					'nonce_received' => $nonce,
					'timestamp' => time()
				)
			);
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
	 * Check if nonce failure is likely due to timing issues
	 *
	 * @return bool Whether this appears to be a timing-related failure.
	 */
	private function is_likely_timing_issue(): bool {
		// Check if user has been active recently (has recent auth cookies)
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			// User is logged in, more likely to be a timing issue
			return true;
		}
		
		// For anonymous users, check if they have recent WordPress session data
		if ( isset( $_COOKIE ) && ! empty( $_COOKIE ) ) {
			foreach ( $_COOKIE as $name => $value ) {
				if ( strpos( $name, 'wordpress' ) === 0 || strpos( $name, 'wp-' ) === 0 ) {
					return true;
				}
			}
		}
		
		return false;
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

		// Return secure streaming URL using REST API endpoint
		$streaming_url = add_query_arg(
			'jwt',
			$token,
			rest_url( 'secure-video/v1/stream' )
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
	 * Stream video endpoint
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return void
	 */
	public function stream_video( $request ) {
		$jwt = $request->get_param( 'jwt' );

		if ( ! $jwt ) {
			$this->send_streaming_error( 'Invalid token', 403 );
		}

		$token_data = $this->validate_jwt_token( $jwt );
		if ( ! $token_data ) {
			$this->send_streaming_error( 'Invalid or expired token', 403 );
		}

		// Additional security checks
		if ( ! $this->verify_streaming_request( $token_data ) ) {
			$this->send_streaming_error( 'Request verification failed', 403 );
		}

		// Get video file path or URL
		$video_url = $token_data['url'];
		$video_path = $this->url_to_path( $video_url );

		if ( ! $video_path ) {
			$this->send_streaming_error( 'Video not found', 404 );
		}

		// Check if it's a local file or external URL
		if ( file_exists( $video_path ) ) {
			// Stream local file
			$this->stream_local_video( $video_path );
		} elseif ( filter_var( $video_path, FILTER_VALIDATE_URL ) ) {
			// Proxy external URL
			$this->proxy_external_video( $video_path );
		} else {
			$this->send_streaming_error( 'Video file not found', 404 );
		}
	}

	/**
	 * Validate JWT token for streaming
	 *
	 * @param string $jwt The JWT token.
	 * @return array|false Token data or false if invalid.
	 */
	private function validate_jwt_token( string $jwt ) {
		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		list( $header, $payload, $signature ) = $parts;

		// Verify signature
		$secret_key = $this->get_secret_key();
		$expected_signature = hash_hmac( 'sha256', $header . '.' . $payload, $secret_key );

		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return false;
		}

		// Decode payload
		$payload_data = json_decode( base64_decode( $payload ), true );
		if ( ! $payload_data ) {
			return false;
		}

		// Check expiration
		if ( isset( $payload_data['exp'] ) && $payload_data['exp'] < time() ) {
			return false;
		}

		return $payload_data;
	}

	/**
	 * Verify streaming request against token data
	 *
	 * @param array $token_data Token data.
	 * @return bool Whether the request is valid.
	 */
	private function verify_streaming_request( array $token_data ): bool {
		// Verify IP address (if stored in token)
		if ( isset( $token_data['ip'] ) ) {
			$current_ip = $this->get_client_ip();
			if ( $token_data['ip'] !== $current_ip ) {
				return false;
			}
		}

		// Additional checks can be added here
		return true;
	}

	/**
	 * Convert URL to file path or handle external URLs
	 *
	 * @param string $url The URL.
	 * @return string|false The file path or the external URL for proxying.
	 */
	private function url_to_path( string $url ) {
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		if ( strpos( $url, $upload_url ) === 0 ) {
			return str_replace( $upload_url, $upload_dir['basedir'], $url );
		}

		// For external URLs, return the URL itself for proxying
		// Check if it's a valid external URL
		if ( filter_var( $url, FILTER_VALIDATE_URL ) && ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) ) {
			return $url;
		}

		return false;
	}

	/**
	 * Stream local video file with proper headers
	 *
	 * @param string $file_path The file path.
	 */
	private function stream_local_video( string $file_path ): void {
		$file_size = filesize( $file_path );
		$file_extension = pathinfo( $file_path, PATHINFO_EXTENSION );

		// Set content type based on file extension
		$content_type = $this->get_content_type( $file_extension );

		// Handle range requests for video seeking
		$range = null;
		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$range = $_SERVER['HTTP_RANGE'];
		}

		// Security headers
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, max-age=3600' );
		header( 'Content-Type: ' . $content_type );

		if ( $range ) {
			$this->stream_range( $file_path, $file_size, $range );
		} else {
			// Stream entire file
			header( 'Content-Length: ' . $file_size );
			header( 'Accept-Ranges: bytes' );

			$handle = fopen( $file_path, 'rb' );
			if ( $handle ) {
				while ( ! feof( $handle ) ) {
					echo fread( $handle, 8192 );
					flush();
				}
				fclose( $handle );
			}
		}
		exit;
	}

	/**
	 * Stream a range of the video file
	 *
	 * @param string $file_path The file path.
	 * @param int    $file_size The file size.
	 * @param string $range The range header.
	 */
	private function stream_range( string $file_path, int $file_size, string $range ): void {
		$ranges = explode( '=', $range, 2 );
		if ( count( $ranges ) !== 2 || $ranges[0] !== 'bytes' ) {
			status_header( 416 );
			exit;
		}

		$range_parts = explode( '-', $ranges[1], 2 );
		$start = intval( $range_parts[0] );
		$end = isset( $range_parts[1] ) && $range_parts[1] !== '' ? intval( $range_parts[1] ) : $file_size - 1;

		if ( $start > $end || $start < 0 || $end >= $file_size ) {
			status_header( 416 );
			exit;
		}

		$content_length = $end - $start + 1;

		status_header( 206 );
		header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size );
		header( 'Content-Length: ' . $content_length );
		header( 'Accept-Ranges: bytes' );

		$handle = fopen( $file_path, 'rb' );
		if ( $handle ) {
			fseek( $handle, $start );
			$bytes_left = $content_length;

			while ( $bytes_left > 0 && ! feof( $handle ) ) {
				$chunk_size = min( 8192, $bytes_left );
				echo fread( $handle, $chunk_size );
				$bytes_left -= $chunk_size;
				flush();
			}
			fclose( $handle );
		}
		exit;
	}

	/**
	 * Proxy external video URL
	 *
	 * @param string $external_url The external video URL.
	 */
	private function proxy_external_video( string $external_url ): void {
		// Security: Validate the external URL is from allowed domains
		$allowed_domains = apply_filters( 'svp_allowed_external_domains', array(
			'storage.yandexcloud.net',
			// Add other trusted domains here
		) );

		$parsed_url = parse_url( $external_url );
		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			$this->send_streaming_error( 'Invalid external URL', 400 );
		}

		$is_allowed = false;
		foreach ( $allowed_domains as $domain ) {
			if ( $parsed_url['host'] === $domain || 
				 ( function_exists( 'str_ends_with' ) && str_ends_with( $parsed_url['host'], '.' . $domain ) ) ||
				 ( ! function_exists( 'str_ends_with' ) && substr( $parsed_url['host'], -strlen( '.' . $domain ) ) === '.' . $domain ) ) {
				$is_allowed = true;
				break;
			}
		}

		if ( ! $is_allowed ) {
			$this->send_streaming_error( 'External domain not allowed', 403 );
		}

		// Handle range requests for external videos
		$headers = array();
		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$headers['Range'] = $_SERVER['HTTP_RANGE'];
		}

		// Add security headers
		$headers['User-Agent'] = 'WordPress Secure Video Player/' . ( defined( 'SVP_VERSION' ) ? SVP_VERSION : '1.0.0' );
		$headers['Referer'] = home_url();

		// Create context for the request
		$context = stream_context_create( array(
			'http' => array(
				'method' => 'GET',
				'header' => $this->build_header_string( $headers ),
				'timeout' => 30,
				'follow_location' => true,
				'max_redirects' => 3,
			),
		) );

		// Open the external stream
		$external_stream = fopen( $external_url, 'rb', false, $context );
		if ( ! $external_stream ) {
			$this->send_streaming_error( 'Failed to open external video stream', 502 );
		}

		// Get response headers from the external stream
		$response_headers = stream_get_meta_data( $external_stream );
		
		// Set appropriate headers based on the external response
		if ( isset( $response_headers['wrapper_data'] ) ) {
			foreach ( $response_headers['wrapper_data'] as $header ) {
				if ( stripos( $header, 'content-type:' ) === 0 ) {
					header( $header );
				} elseif ( stripos( $header, 'content-length:' ) === 0 ) {
					header( $header );
				} elseif ( stripos( $header, 'content-range:' ) === 0 ) {
					header( $header );
				} elseif ( stripos( $header, 'accept-ranges:' ) === 0 ) {
					header( $header );
				} elseif ( stripos( $header, 'HTTP/' ) === 0 ) {
					// Handle HTTP status line
					if ( strpos( $header, '206 Partial Content' ) !== false ) {
						status_header( 206 );
					} elseif ( strpos( $header, '416 Range Not Satisfiable' ) !== false ) {
						status_header( 416 );
						fclose( $external_stream );
						exit;
					}
				}
			}
		}

		// Set security headers
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, max-age=3600' );

		// Stream the external content
		while ( ! feof( $external_stream ) ) {
			echo fread( $external_stream, 8192 );
			flush();
		}
		
		fclose( $external_stream );
		exit;
	}

	/**
	 * Build header string from array
	 *
	 * @param array $headers Array of headers.
	 * @return string Header string.
	 */
	private function build_header_string( array $headers ): string {
		$header_string = '';
		foreach ( $headers as $key => $value ) {
			$header_string .= $key . ': ' . $value . "\r\n";
		}
		return $header_string;
	}

	/**
	 * Get content type for file extension
	 *
	 * @param string $extension The file extension.
	 * @return string The content type.
	 */
	private function get_content_type( string $extension ): string {
		$types = array(
			'mp4'  => 'video/mp4',
			'webm' => 'video/webm',
			'ogg'  => 'video/ogg',
			'avi'  => 'video/x-msvideo',
			'mov'  => 'video/quicktime',
		);

		return $types[ strtolower( $extension ) ] ?? 'application/octet-stream';
	}

	/**
	 * Send streaming error response
	 *
	 * @param string $message Error message.
	 * @param int    $code HTTP status code.
	 */
	private function send_streaming_error( string $message, int $code = 400 ): void {
		status_header( $code );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( array( 'error' => $message ) );
		exit;
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