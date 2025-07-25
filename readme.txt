=== Secure Multi-Quality Video Player ===
Contributors: wordpressvideoplayer
Tags: video, player, secure, quality, streaming, HTML5, responsive
Requires at least: 6.7
Tested up to: 6.8.2
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, secure, cross-browser HTML5 video player plugin that lets editors embed videos in three MP4 quality variants via shortcode or Gutenberg block.

== Description ==

Secure Multi-Quality Video Player is a WordPress plugin that provides a secure and feature-rich video playback experience. It allows you to upload videos in multiple quality variants (HD 1080p, SD 720p, LD 480p) and embed them using either shortcodes or Gutenberg blocks.

= Key Features =

* **Multiple Quality Options**: Support for HD (1080p), SD (720p), and LD (480p) video variants
* **Secure Streaming**: Token-based video delivery to prevent casual downloading
* **Responsive Design**: Works perfectly on desktop and mobile devices
* **Accessibility**: WCAG 2.2 AA compliant with proper ARIA labels and keyboard navigation
* **Custom Controls**: Play/pause, seek, volume, quality switching, speed control, and fullscreen
* **WordPress Integration**: Custom post type for video management
* **Gutenberg Block**: Native block editor support
* **Shortcode Support**: Easy embedding with `[secure_video id="123"]`

= Security Features =

* JWT token-based video streaming
* Rate limiting for API requests
* Input sanitization and output escaping
* Capability-based permissions
* Context menu disabled on video elements

= Browser Support =

* Chrome 90+
* Firefox 88+
* Safari 14+
* Edge 90+
* Mobile browsers (iOS Safari, Chrome Mobile)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/secure-video-player` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to 'Secure Videos' in your WordPress admin to create your first video.
4. Add video URLs for different quality levels (HD is required, SD and LD are optional).
5. Use the generated shortcode or Gutenberg block to embed videos in your content.

== Frequently Asked Questions ==

= What video formats are supported? =

The plugin supports MP4 videos. We recommend H.264 encoding for best compatibility across browsers.

= How does the secure streaming work? =

Videos are served through a secure endpoint that generates temporary JWT tokens. This prevents direct linking to video files while still allowing legitimate playback.

= Can I use external video URLs? =

Yes, you can use external video URLs, but the secure streaming features will only work with locally hosted files in your WordPress uploads directory.

= Is the plugin mobile-friendly? =

Yes, the player is fully responsive and optimized for mobile devices with touch-friendly controls.

= How do I customize the player appearance? =

You can add custom CSS to modify the player appearance. The plugin follows WordPress coding standards and uses BEM CSS methodology for easy customization.

== Screenshots ==

1. Video management interface in WordPress admin
2. Gutenberg block for embedding videos
3. Responsive video player with custom controls
4. Quality selection dropdown
5. Speed control options

== Changelog ==

= 1.0.0 =
* Initial release
* Custom post type for video management
* Secure token-based streaming
* Multiple quality support (HD, SD, LD)
* Gutenberg block integration
* Shortcode support
* Responsive design with accessibility features
* Cross-browser compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Secure Multi-Quality Video Player plugin.

== Technical Requirements ==

* WordPress 6.7 or higher
* PHP 8.1 or higher
* Modern web browser with HTML5 video support

== Support ==

For support and feature requests, please visit our GitHub repository or contact us through the WordPress.org support forums.

== Credits ==

This plugin follows WordPress coding standards and best practices for security and performance.