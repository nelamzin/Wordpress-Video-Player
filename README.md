# Secure Multi-Quality Video Player

A lightweight, secure, cross-browser HTML5 video player plugin for WordPress 6.7+ that lets editors embed videos in three MP4 quality variants (High = 1080p, Medium = 720p, Low = 480p) via shortcode or Gutenberg block.

## Features

- **Multiple Quality Support**: HD (1080p), SD (720p), and LD (480p) video variants
- **Secure Streaming**: JWT token-based video delivery with rate limiting
- **Custom Post Type**: Dedicated video management interface
- **Shortcode**: `[secure_video id="123"]` for easy embedding
- **Gutenberg Block**: Native block editor integration
- **Responsive Design**: Mobile-friendly with touch controls
- **Accessibility**: WCAG 2.2 AA compliant with proper ARIA attributes
- **Cross-browser**: Chrome, Firefox, Safari, Edge support
- **Security**: Input sanitization, output escaping, capability checks

## Installation

1. Upload the plugin to `/wp-content/plugins/secure-video-player/`
2. Activate through the WordPress 'Plugins' menu
3. Navigate to 'Secure Videos' in your admin panel
4. Create your first video with quality URLs
5. Use the shortcode or Gutenberg block to embed videos

## File Structure

```
secure-video-player/
├── readme.txt                 # WordPress.org plugin readme
├── secure-video-player.php    # Main plugin file
├── serve-video.php            # Secure video streaming endpoint
├── assets/
│   ├── css/
│   │   ├── player.css         # Frontend player styles
│   │   └── admin.css          # Admin interface styles
│   └── js/
│       ├── player.js          # Video player functionality
│       └── block.js           # Gutenberg block
├── includes/
│   ├── class-loader.php       # Plugin orchestrator
│   ├── class-cpt.php          # Custom post type & meta boxes
│   ├── class-shortcode.php    # Shortcode handler
│   ├── class-block.php        # Gutenberg block registration
│   ├── class-rest.php         # JWT token API endpoint
│   └── class-assets.php       # CSS/JS enqueuing
└── templates/
    └── player.php             # Player HTML template
```

## Usage

### Creating Videos

1. Go to **Secure Videos** → **Add New**
2. Enter a title for your video
3. Add video URLs for different qualities:
   - **HD URL (1080p)**: Required
   - **SD URL (720p)**: Optional
   - **LD URL (480p)**: Optional
4. Publish the video

### Embedding Videos

#### Shortcode
```php
[secure_video id="123"]
[secure_video id="123" autoplay="true" preload="metadata" class="my-custom-class"]
```

#### Gutenberg Block
1. Add a "Secure Video Player" block
2. Select your video from the dropdown
3. The block will render on the frontend

### PHP Usage
```php
// Get video player HTML
$shortcode = new Secure_Video_Player_Shortcode();
$html = $shortcode->render_shortcode(['id' => 123]);
echo $html;
```

## Security Features

- **JWT Tokens**: Videos served through secure endpoint with 60-second expiry
- **Rate Limiting**: 60 requests per minute per IP
- **Capability Checks**: `manage_secure_videos` capability required
- **Input Sanitization**: All inputs sanitized with `esc_url_raw()`, `sanitize_text_field()`
- **Output Escaping**: All outputs escaped with `esc_attr()`, `esc_html()`
- **Nonce Verification**: CSRF protection on all form submissions
- **IP Validation**: Token requests validated against client IP

## Player Controls

- **Play/Pause**: Click button or spacebar
- **Seek**: Click progress bar or arrow keys (±5s)
- **Volume**: Click volume slider or up/down arrows
- **Quality**: Dropdown with available qualities
- **Speed**: 0.5× to 2× in 0.25 increments
- **Fullscreen**: Button or 'F' key
- **Mute**: Button or 'M' key

## Hooks & Filters

The plugin provides several hooks for customization:

```php
// Modify video player HTML
add_filter('svp_player_html', function($html, $video_id) {
    // Customize player HTML
    return $html;
}, 10, 2);

// Modify JWT token payload
add_filter('svp_jwt_payload', function($payload, $video_id) {
    // Add custom data to token
    return $payload;
}, 10, 2);

// Custom video URL validation
add_filter('svp_validate_video_url', function($is_valid, $url, $quality) {
    // Custom validation logic
    return $is_valid;
}, 10, 3);
```

## Customization

### Custom CSS
```css
/* Customize player appearance */
.secure-video-player {
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.svp-controls {
    background: linear-gradient(transparent, rgba(0,0,0,0.9));
}
```

### Custom JavaScript
```javascript
// Listen for player events
document.addEventListener('svp:quality-changed', function(e) {
    console.log('Quality changed to:', e.detail.quality);
});

document.addEventListener('svp:player-ready', function(e) {
    console.log('Player ready:', e.detail.player);
});
```

## Development

### Requirements
- WordPress 6.7+
- PHP 8.1+
- Node.js 16+ (for development)

### Setup
```bash
# Clone the repository
git clone https://github.com/nelamzin/Wordpress-Video-Player.git
cd Wordpress-Video-Player

# No build process required - assets are production-ready
```

### Testing
```bash
# Check PHP syntax
find . -name "*.php" -exec php -l {} \;

# WordPress Coding Standards (if phpcs is installed)
phpcs --standard=WordPress .
```

## Browser Support

| Browser | Version | Status |
|---------|---------|--------|
| Chrome | 90+ | ✅ Fully supported |
| Firefox | 88+ | ✅ Fully supported |
| Safari | 14+ | ✅ Fully supported |
| Edge | 90+ | ✅ Fully supported |
| iOS Safari | 14+ | ✅ Mobile optimized |
| Chrome Mobile | 90+ | ✅ Touch controls |

## Performance

- **Bundle Size**: < 55KB gzipped (JS + CSS)
- **Lighthouse Score**: 90+ (Performance & Best Practices)
- **Lazy Loading**: Player assets only loaded when needed
- **Caching**: Aggressive caching with proper cache headers

## License

GPL-2.0-or-later - See [LICENSE](LICENSE) file.

## Support

- **Issues**: [GitHub Issues](https://github.com/nelamzin/Wordpress-Video-Player/issues)
- **Documentation**: [Plugin Documentation](https://github.com/nelamzin/Wordpress-Video-Player/wiki)
- **WordPress.org**: [Plugin Page](https://wordpress.org/plugins/secure-video-player/)

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

**Made with ❤️ for the WordPress community**