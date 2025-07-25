<?php
/**
 * Secure Video Serving Script
 *
 * @package SecureVideoPlayer
 */

// Load WordPress
$wp_load_path = dirname( __FILE__ ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	// Try alternative path
	$wp_load_path = dirname( __FILE__ ) . '/../../../../wp-load.php';
}

if ( file_exists( $wp_load_path ) ) {
	require_once $wp_load_path;
} else {
	http_response_code( 500 );
	exit( 'WordPress not found' );
}

// Prevent direct access without token
if ( ! isset( $_GET['jwt'] ) ) {
	http_response_code( 403 );
	header( 'Content-Type: application/json' );
	echo wp_json_encode( array( 'error' => 'Access denied' ) );
	exit;
}

/**
 * Secure video server class
 */
class Secure_Video_Server {

	/**
	 * Serve the video
	 */
	public static function serve(): void {
		$jwt = sanitize_text_field( $_GET['jwt'] );

		if ( ! $jwt ) {
			self::send_error( 'Invalid token', 403 );
		}

		$token_data = self::validate_token( $jwt );
		if ( ! $token_data ) {
			self::send_error( 'Invalid or expired token', 403 );
		}

		// Additional security checks
		if ( ! self::verify_request( $token_data ) ) {
			self::send_error( 'Request verification failed', 403 );
		}

		// Get video file path or URL
		$video_url = $token_data['url'];
		$video_path = self::url_to_path( $video_url );

		if ( ! $video_path ) {
			self::send_error( 'Video not found', 404 );
		}

		// Check if it's a local file or external URL
		if ( file_exists( $video_path ) ) {
			// Stream local file
			self::stream_video( $video_path );
		} elseif ( filter_var( $video_path, FILTER_VALIDATE_URL ) ) {
			// Proxy external URL
			self::proxy_external_video( $video_path );
		} else {
			self::send_error( 'Video file not found', 404 );
		}
	}

	/**
	 * Validate JWT token
	 *
	 * @param string $jwt The JWT token.
	 * @return array|false Token data or false if invalid.
	 */
	private static function validate_token( string $jwt ) {
		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		list( $header, $payload, $signature ) = $parts;

		// Verify signature
		$secret_key = self::get_secret_key();
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
	 * Verify request against token data
	 *
	 * @param array $token_data Token data.
	 * @return bool Whether the request is valid.
	 */
	private static function verify_request( array $token_data ): bool {
		// Verify IP address (if stored in token)
		if ( isset( $token_data['ip'] ) ) {
			$current_ip = self::get_client_ip();
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
	private static function url_to_path( string $url ) {
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
	 * Stream video file with proper headers
	 *
	 * @param string $file_path The file path.
	 */
	private static function stream_video( string $file_path ): void {
		$file_size = filesize( $file_path );
		$file_extension = pathinfo( $file_path, PATHINFO_EXTENSION );

		// Set content type based on file extension
		$content_type = self::get_content_type( $file_extension );

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
			self::stream_range( $file_path, $file_size, $range );
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
	private static function stream_range( string $file_path, int $file_size, string $range ): void {
		$ranges = explode( '=', $range, 2 );
		if ( count( $ranges ) !== 2 || $ranges[0] !== 'bytes' ) {
			header( 'HTTP/1.1 416 Range Not Satisfiable' );
			exit;
		}

		$range_parts = explode( '-', $ranges[1], 2 );
		$start = intval( $range_parts[0] );
		$end = isset( $range_parts[1] ) && $range_parts[1] !== '' ? intval( $range_parts[1] ) : $file_size - 1;

		if ( $start > $end || $start < 0 || $end >= $file_size ) {
			header( 'HTTP/1.1 416 Range Not Satisfiable' );
			exit;
		}

		$content_length = $end - $start + 1;

		header( 'HTTP/1.1 206 Partial Content' );
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
	}

	/**
	 * Proxy external video URL
	 *
	 * @param string $external_url The external video URL.
	 */
	private static function proxy_external_video( string $external_url ): void {
		// Security: Validate the external URL is from allowed domains
		$allowed_domains = apply_filters( 'svp_allowed_external_domains', array(
			'storage.yandexcloud.net',
			// Add other trusted domains here
		) );

		$parsed_url = parse_url( $external_url );
		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			self::send_error( 'Invalid external URL', 400 );
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
			self::send_error( 'External domain not allowed', 403 );
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
				'header' => self::build_header_string( $headers ),
				'timeout' => 30,
				'follow_location' => true,
				'max_redirects' => 3,
			),
		) );

		// Open the external stream
		$external_stream = fopen( $external_url, 'rb', false, $context );
		if ( ! $external_stream ) {
			self::send_error( 'Failed to open external video stream', 502 );
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
						header( 'HTTP/1.1 206 Partial Content' );
					} elseif ( strpos( $header, '416 Range Not Satisfiable' ) !== false ) {
						header( 'HTTP/1.1 416 Range Not Satisfiable' );
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
	private static function build_header_string( array $headers ): string {
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
	private static function get_content_type( string $extension ): string {
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
	 * Get client IP address
	 *
	 * @return string The client IP.
	 */
	private static function get_client_ip(): string {
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
	 * Get secret key
	 *
	 * @return string The secret key.
	 */
	private static function get_secret_key(): string {
		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'secure-video-player-secret';
		return $key . get_option( 'svp_secret_salt', wp_generate_password( 64, true, true ) );
	}

	/**
	 * Send error response
	 *
	 * @param string $message Error message.
	 * @param int    $code HTTP status code.
	 */
	private static function send_error( string $message, int $code = 400 ): void {
		http_response_code( $code );
		header( 'Content-Type: application/json' );
		echo wp_json_encode( array( 'error' => $message ) );
		exit;
	}
}

// Serve the video
Secure_Video_Server::serve();