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

		// Get video file path
		$video_url = $token_data['url'];
		$video_path = self::url_to_path( $video_url );

		if ( ! $video_path || ! file_exists( $video_path ) ) {
			self::send_error( 'Video file not found', 404 );
		}

		// Stream the video
		self::stream_video( $video_path );
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
	 * Convert URL to file path
	 *
	 * @param string $url The URL.
	 * @return string|false The file path or false if not local.
	 */
	private static function url_to_path( string $url ) {
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		if ( strpos( $url, $upload_url ) === 0 ) {
			return str_replace( $upload_url, $upload_dir['basedir'], $url );
		}

		// For external URLs, we can't serve directly
		// In a production environment, you might want to proxy these
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