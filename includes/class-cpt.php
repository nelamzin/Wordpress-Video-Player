<?php
/**
 * Custom Post Type Class
 *
 * @package SecureVideoPlayer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the video custom post type and meta boxes
 */
class Secure_Video_Player_CPT {

	/**
	 * Initialize the CPT
	 */
	public function init(): void {
		// Register the post type directly since we're already in the init hook
		$this->register_post_type();
		
		// Set up other hooks
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
		add_action( 'edit_form_after_title', array( $this, 'add_shortcode_display' ) );
		add_filter( 'manage_video_player_posts_columns', array( $this, 'add_shortcode_column' ) );
		add_action( 'manage_video_player_posts_custom_column', array( $this, 'show_shortcode_column' ), 10, 2 );
	}

	/**
	 * Register the video post type
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Videos', 'Post Type General Name', 'secure-video-player' ),
			'singular_name'         => _x( 'Video', 'Post Type Singular Name', 'secure-video-player' ),
			'menu_name'             => __( 'Secure Videos', 'secure-video-player' ),
			'name_admin_bar'        => __( 'Video', 'secure-video-player' ),
			'archives'              => __( 'Video Archives', 'secure-video-player' ),
			'attributes'            => __( 'Video Attributes', 'secure-video-player' ),
			'parent_item_colon'     => __( 'Parent Video:', 'secure-video-player' ),
			'all_items'             => __( 'All Videos', 'secure-video-player' ),
			'add_new_item'          => __( 'Add New Video', 'secure-video-player' ),
			'add_new'               => __( 'Add New', 'secure-video-player' ),
			'new_item'              => __( 'New Video', 'secure-video-player' ),
			'edit_item'             => __( 'Edit Video', 'secure-video-player' ),
			'update_item'           => __( 'Update Video', 'secure-video-player' ),
			'view_item'             => __( 'View Video', 'secure-video-player' ),
			'view_items'            => __( 'View Videos', 'secure-video-player' ),
			'search_items'          => __( 'Search Video', 'secure-video-player' ),
			'not_found'             => __( 'Not found', 'secure-video-player' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'secure-video-player' ),
			'featured_image'        => __( 'Video Poster', 'secure-video-player' ),
			'set_featured_image'    => __( 'Set video poster', 'secure-video-player' ),
			'remove_featured_image' => __( 'Remove video poster', 'secure-video-player' ),
			'use_featured_image'    => __( 'Use as video poster', 'secure-video-player' ),
			'insert_into_item'      => __( 'Insert into video', 'secure-video-player' ),
			'uploaded_to_this_item' => __( 'Uploaded to this video', 'secure-video-player' ),
			'items_list'            => __( 'Videos list', 'secure-video-player' ),
			'items_list_navigation' => __( 'Videos list navigation', 'secure-video-player' ),
			'filter_items_list'     => __( 'Filter videos list', 'secure-video-player' ),
		);

		$args = array(
			'label'                 => __( 'Video', 'secure-video-player' ),
			'description'           => __( 'Secure video content', 'secure-video-player' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 25,
			'menu_icon'             => 'dashicons-video-alt3',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
			'rest_base'             => 'secure-videos',
		);

		$result = register_post_type( 'video_player', $args );
		
		// Add error handling for CPT registration
		if ( is_wp_error( $result ) ) {
			error_log( 'Secure Video Player: Failed to register post type - ' . $result->get_error_message() );
		}
	}

	/**
	 * Add meta boxes for video URLs
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'svp_video_urls',
			__( 'Video Quality URLs', 'secure-video-player' ),
			array( $this, 'render_meta_box' ),
			'video_player',
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box( $post ): void {
		// Add nonce for security
		wp_nonce_field( 'svp_save_meta_box_data', 'svp_meta_box_nonce' );

		// Get existing values
		$hd_url = get_post_meta( $post->ID, '_svp_hd_url', true );
		$sd_url = get_post_meta( $post->ID, '_svp_sd_url', true );
		$ld_url = get_post_meta( $post->ID, '_svp_ld_url', true );

		?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="svp_hd_url"><?php esc_html_e( 'HD URL (1080p) *', 'secure-video-player' ); ?></label>
					</th>
					<td>
						<input type="url" id="svp_hd_url" name="svp_hd_url" value="<?php echo esc_attr( $hd_url ); ?>" class="regular-text" required />
						<p class="description"><?php esc_html_e( 'Required. URL to the high-definition video file (1080p).', 'secure-video-player' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="svp_sd_url"><?php esc_html_e( 'SD URL (720p)', 'secure-video-player' ); ?></label>
					</th>
					<td>
						<input type="url" id="svp_sd_url" name="svp_sd_url" value="<?php echo esc_attr( $sd_url ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Optional. URL to the standard-definition video file (720p).', 'secure-video-player' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="svp_ld_url"><?php esc_html_e( 'LD URL (480p)', 'secure-video-player' ); ?></label>
					</th>
					<td>
						<input type="url" id="svp_ld_url" name="svp_ld_url" value="<?php echo esc_attr( $ld_url ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Optional. URL to the low-definition video file (480p).', 'secure-video-player' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save meta box data
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_meta_boxes( int $post_id ): void {
		// Check if user intended to change this value
		if ( ! isset( $_POST['svp_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['svp_meta_box_nonce'], 'svp_save_meta_box_data' ) ) {
			return;
		}

		// Check if user has permission to edit the post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Don't save during autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type
		if ( 'video_player' !== get_post_type( $post_id ) ) {
			return;
		}

		// Save HD URL (required)
		if ( isset( $_POST['svp_hd_url'] ) ) {
			$hd_url = esc_url_raw( $_POST['svp_hd_url'] );
			if ( ! empty( $hd_url ) ) {
				update_post_meta( $post_id, '_svp_hd_url', $hd_url );
			} else {
				delete_post_meta( $post_id, '_svp_hd_url' );
			}
		}

		// Save SD URL (optional)
		if ( isset( $_POST['svp_sd_url'] ) ) {
			$sd_url = esc_url_raw( $_POST['svp_sd_url'] );
			if ( ! empty( $sd_url ) ) {
				update_post_meta( $post_id, '_svp_sd_url', $sd_url );
			} else {
				delete_post_meta( $post_id, '_svp_sd_url' );
			}
		}

		// Save LD URL (optional)
		if ( isset( $_POST['svp_ld_url'] ) ) {
			$ld_url = esc_url_raw( $_POST['svp_ld_url'] );
			if ( ! empty( $ld_url ) ) {
				update_post_meta( $post_id, '_svp_ld_url', $ld_url );
			} else {
				delete_post_meta( $post_id, '_svp_ld_url' );
			}
		}
	}

	/**
	 * Add shortcode display after title
	 *
	 * @param WP_Post $post The post object.
	 */
	public function add_shortcode_display( $post ): void {
		if ( 'video_player' !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}

		?>
		<div class="postbox">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Shortcode', 'secure-video-player' ); ?></h2>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Use this shortcode to embed the video:', 'secure-video-player' ); ?></p>
				<div style="display: flex; gap: 10px; align-items: center;">
					<input type="text" readonly value="[secure_video id=&quot;<?php echo esc_attr( $post->ID ); ?>&quot;]" class="regular-text" onclick="this.select();" style="flex: 1;" />
					<button type="button" class="button" onclick="this.copyShortcode(<?php echo esc_js( $post->ID ); ?>);"><?php esc_html_e( 'Copy', 'secure-video-player' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'You can also add optional parameters like: [secure_video id="' . esc_attr( $post->ID ) . '" autoplay="true" class="my-class"]', 'secure-video-player' ); ?></p>
			</div>
		</div>
		
		<script type="text/javascript">
		(function() {
			window.copyShortcode = function(videoId) {
				const shortcode = `[secure_video id="${videoId}"]`;
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(shortcode).then(() => {
						alert('<?php echo esc_js( __( 'Shortcode copied to clipboard!', 'secure-video-player' ) ); ?>');
					}).catch(() => {
						// Fallback for clipboard API failure
						fallbackCopy(shortcode);
					});
				} else {
					// Fallback for older browsers or non-HTTPS
					fallbackCopy(shortcode);
				}
			};
			
			function fallbackCopy(text) {
				const textArea = document.createElement('textarea');
				textArea.value = text;
				textArea.style.position = 'fixed';
				textArea.style.opacity = '0';
				document.body.appendChild(textArea);
				textArea.focus();
				textArea.select();
				try {
					document.execCommand('copy');
					alert('<?php echo esc_js( __( 'Shortcode copied to clipboard!', 'secure-video-player' ) ); ?>');
				} catch (err) {
					alert('<?php echo esc_js( __( 'Failed to copy shortcode. Please copy manually.', 'secure-video-player' ) ); ?>');
				}
				document.body.removeChild(textArea);
			}
		})();
		</script>
		<?php
	}

	/**
	 * Add shortcode column to posts list
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_shortcode_column( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['shortcode'] = __( 'Shortcode', 'secure-video-player' );
			}
		}
		return $new_columns;
	}

	/**
	 * Show shortcode column content
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function show_shortcode_column( string $column, int $post_id ): void {
		if ( 'shortcode' === $column ) {
			$shortcode = '[secure_video id="' . $post_id . '"]';
			echo '<code>' . esc_html( $shortcode ) . '</code>';
			echo '<br><button type="button" class="button button-small" onclick="copyToClipboard(\'' . esc_js( $shortcode ) . '\', this)" style="margin-top: 5px;">' . esc_html__( 'Copy', 'secure-video-player' ) . '</button>';
		}
	}
}