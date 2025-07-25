<?php
/**
 * Video Player Template
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="<?php echo esc_attr( $css_classes ); ?>"<?php echo $data_attrs_string; ?>>
	<div class="svp-loading-container">
		<div class="svp-loading-spinner" role="status" aria-label="<?php esc_attr_e( 'Loading video...', 'secure-video-player' ); ?>">
			<div class="svp-spinner"></div>
		</div>
	</div>
	
	<video class="svp-video" preload="<?php echo esc_attr( $atts['preload'] ); ?>" <?php echo $poster_url ? 'poster="' . esc_url( $poster_url ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $video_title ); ?>">
		<p><?php esc_html_e( 'Your browser does not support the video tag.', 'secure-video-player' ); ?></p>
	</video>
	
	<div class="svp-controls" role="toolbar" aria-label="<?php esc_attr_e( 'Video controls', 'secure-video-player' ); ?>">
		<button class="svp-play-pause" type="button" aria-label="<?php esc_attr_e( 'Play/Pause', 'secure-video-player' ); ?>">
			<span class="svp-icon-play" aria-hidden="true">▶</span>
			<span class="svp-icon-pause" aria-hidden="true">⏸</span>
			<span class="svp-sr-only"><?php esc_html_e( 'Play', 'secure-video-player' ); ?></span>
		</button>
		
		<div class="svp-time-display" role="timer" aria-live="off">
			<span class="svp-current-time">0:00</span>
			<span class="svp-time-separator">/</span>
			<span class="svp-duration">0:00</span>
		</div>
		
		<div class="svp-progress-container">
			<div class="svp-progress-bar" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e( 'Video progress', 'secure-video-player' ); ?>" tabindex="0">
				<div class="svp-progress-buffer"></div>
				<div class="svp-progress-played"></div>
				<div class="svp-progress-handle"></div>
			</div>
		</div>
		
		<div class="svp-volume-container">
			<button class="svp-mute" type="button" aria-label="<?php esc_attr_e( 'Mute/Unmute', 'secure-video-player' ); ?>">
				<span class="svp-icon-volume" aria-hidden="true">🔊</span>
				<span class="svp-icon-mute" aria-hidden="true">🔇</span>
			</button>
			<div class="svp-volume-slider" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" aria-label="<?php esc_attr_e( 'Volume', 'secure-video-player' ); ?>" tabindex="0">
				<div class="svp-volume-bar">
					<div class="svp-volume-fill"></div>
					<div class="svp-volume-handle"></div>
				</div>
			</div>
		</div>
		
		<div class="svp-quality-container">
			<button class="svp-quality-btn" type="button" aria-label="<?php esc_attr_e( 'Video quality', 'secure-video-player' ); ?>" aria-haspopup="true">
				<span class="svp-quality-text">HD</span>
			</button>
			<div class="svp-quality-menu" role="menu" aria-label="<?php esc_attr_e( 'Select video quality', 'secure-video-player' ); ?>">
				<!-- Quality options will be populated by JavaScript -->
			</div>
		</div>
		
		<div class="svp-speed-container">
			<button class="svp-speed-btn" type="button" aria-label="<?php esc_attr_e( 'Playback speed', 'secure-video-player' ); ?>" aria-haspopup="true">
				<span class="svp-speed-text">1×</span>
			</button>
			<div class="svp-speed-menu" role="menu" aria-label="<?php esc_attr_e( 'Select playback speed', 'secure-video-player' ); ?>">
				<div class="svp-speed-option" role="menuitem" data-speed="0.5" tabindex="0">0.5×</div>
				<div class="svp-speed-option" role="menuitem" data-speed="0.75" tabindex="0">0.75×</div>
				<div class="svp-speed-option svp-speed-active" role="menuitem" data-speed="1" tabindex="0">1×</div>
				<div class="svp-speed-option" role="menuitem" data-speed="1.25" tabindex="0">1.25×</div>
				<div class="svp-speed-option" role="menuitem" data-speed="1.5" tabindex="0">1.5×</div>
				<div class="svp-speed-option" role="menuitem" data-speed="1.75" tabindex="0">1.75×</div>
				<div class="svp-speed-option" role="menuitem" data-speed="2" tabindex="0">2×</div>
			</div>
		</div>
		
		<button class="svp-fullscreen" type="button" aria-label="<?php esc_attr_e( 'Enter fullscreen', 'secure-video-player' ); ?>">
			<span class="svp-icon-fullscreen" aria-hidden="true">⛶</span>
			<span class="svp-icon-exit-fullscreen" aria-hidden="true">⇲</span>
		</button>
	</div>
	
	<div class="svp-error-message" role="alert" aria-live="assertive" style="display: none;">
		<p><?php esc_html_e( 'Error loading video. Please try again.', 'secure-video-player' ); ?></p>
	</div>
</div>