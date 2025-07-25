/**
 * Gutenberg Block JavaScript
 * 
 * @package SecureVideoPlayer
 */

(function() {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, Placeholder, Button } = wp.components;
	const { createElement: e, Fragment } = wp.element;
	const { __ } = wp.i18n;

	// Register the block
	registerBlockType('secure-video-player/video', {
		title: svpBlockData.strings.title,
		description: svpBlockData.strings.description,
		icon: 'video-alt3',
		category: 'media',
		attributes: {
			videoId: {
				type: 'number',
				default: 0
			}
		},
		supports: {
			align: ['wide', 'full']
		},

		edit: function(props) {
			const { attributes, setAttributes } = props;
			const { videoId } = attributes;

			// Handle video selection
			const onSelectVideo = (newVideoId) => {
				setAttributes({ videoId: parseInt(newVideoId) });
			};

			// Get selected video data
			const selectedVideo = svpBlockData.videos.find(video => video.value === videoId);

			// If no video is selected, show selector
			if (!videoId || !selectedVideo) {
				return e(
					Placeholder,
					{
						icon: 'video-alt3',
						label: svpBlockData.strings.title,
						instructions: svpBlockData.strings.description
					},
					svpBlockData.videos.length > 0 
						? e(
							SelectControl,
							{
								label: svpBlockData.strings.selectVideo,
								value: videoId,
								options: [
									{ value: 0, label: svpBlockData.strings.selectVideo },
									...svpBlockData.videos
								],
								onChange: onSelectVideo
							}
						)
						: e(
							'p',
							{ style: { textAlign: 'center', color: '#757575' } },
							svpBlockData.strings.noVideos
						)
				);
			}

			// Show selected video with preview
			return e(
				Fragment,
				null,
				e(
					InspectorControls,
					null,
					e(
						PanelBody,
						{ title: svpBlockData.strings.title },
						e(
							SelectControl,
							{
								label: svpBlockData.strings.selectVideo,
								value: videoId,
								options: [
									{ value: 0, label: svpBlockData.strings.selectVideo },
									...svpBlockData.videos
								],
								onChange: onSelectVideo
							}
						)
					)
				),
				e(
					'div',
					{
						className: 'secure-video-player-block-preview',
						style: {
							border: '2px dashed #ddd',
							borderRadius: '8px',
							padding: '20px',
							textAlign: 'center',
							backgroundColor: '#f9f9f9'
						}
					},
					e(
						'div',
						{
							style: {
								fontSize: '48px',
								color: '#555',
								marginBottom: '10px'
							}
						},
						'📹'
					),
					e(
						'h3',
						{
							style: {
								margin: '0 0 10px 0',
								fontSize: '18px',
								color: '#333'
							}
						},
						selectedVideo.label
					),
					e(
						'p',
						{
							style: {
								margin: '0 0 15px 0',
								color: '#666',
								fontSize: '14px'
							}
						},
						svpBlockData.strings.preview
					),
					e(
						Button,
						{
							isPrimary: true,
							onClick: () => setAttributes({ videoId: 0 })
						},
						svpBlockData.strings.changeVideo
					)
				)
			);
		},

		save: function() {
			// Return null to use PHP render callback
			return null;
		}
	});

})();