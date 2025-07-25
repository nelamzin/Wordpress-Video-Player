/**
 * Secure Video Player JavaScript
 * 
 * @package SecureVideoPlayer
 */

(function() {
	'use strict';

	// Player class
	class SecureVideoPlayer {
		constructor(container) {
			this.container = container;
			this.video = container.querySelector('.svp-video');
			this.controls = container.querySelector('.svp-controls');
			this.loadingContainer = container.querySelector('.svp-loading-container');
			
			// Control elements
			this.playPauseBtn = container.querySelector('.svp-play-pause');
			this.currentTimeEl = container.querySelector('.svp-current-time');
			this.durationEl = container.querySelector('.svp-duration');
			this.progressBar = container.querySelector('.svp-progress-bar');
			this.progressPlayed = container.querySelector('.svp-progress-played');
			this.progressBuffer = container.querySelector('.svp-progress-buffer');
			this.volumeBtn = container.querySelector('.svp-mute');
			this.volumeSlider = container.querySelector('.svp-volume-slider');
			this.volumeFill = container.querySelector('.svp-volume-fill');
			this.qualityContainer = container.querySelector('.svp-quality-container');
			this.qualityBtn = container.querySelector('.svp-quality-btn');
			this.qualityMenu = container.querySelector('.svp-quality-menu');
			this.speedContainer = container.querySelector('.svp-speed-container');
			this.speedBtn = container.querySelector('.svp-speed-btn');
			this.speedMenu = container.querySelector('.svp-speed-menu');
			this.fullscreenBtn = container.querySelector('.svp-fullscreen');
			this.errorMessage = container.querySelector('.svp-error-message');
			
			// State
			this.videoId = parseInt(container.dataset.videoId);
			this.currentQuality = 'hd';
			this.qualities = {};
			this.isLoading = false;
			this.isDragging = false;
			this.controlsTimeout = null;
			
			this.init();
		}
		
		init() {
			this.setupVideoSources();
			this.bindEvents();
			this.loadUserPreferences();
			this.setupAccessibility();
		}
		
		setupVideoSources() {
			// Collect available quality sources
			if (this.container.dataset.srcHd) {
				this.qualities.hd = { 
					label: svpAjax.strings.qualityHD, 
					url: this.container.dataset.srcHd 
				};
			}
			if (this.container.dataset.srcSd) {
				this.qualities.sd = { 
					label: svpAjax.strings.qualitySD, 
					url: this.container.dataset.srcSd 
				};
			}
			if (this.container.dataset.srcLd) {
				this.qualities.ld = { 
					label: svpAjax.strings.qualityLD, 
					url: this.container.dataset.srcLd 
				};
			}
			
			// Determine default quality
			const savedQuality = localStorage.getItem('svpQuality');
			if (savedQuality && this.qualities[savedQuality]) {
				this.currentQuality = savedQuality;
			} else if (!this.qualities.hd) {
				this.currentQuality = this.qualities.sd ? 'sd' : 'ld';
			}
			
			this.buildQualityMenu();
			this.loadVideo();
		}
		
		buildQualityMenu() {
			this.qualityMenu.innerHTML = '';
			
			Object.keys(this.qualities).forEach(quality => {
				const option = document.createElement('div');
				option.className = 'svp-quality-option';
				option.dataset.quality = quality;
				option.textContent = this.qualities[quality].label;
				option.setAttribute('role', 'menuitem');
				option.setAttribute('tabindex', '0');
				
				if (quality === this.currentQuality) {
					option.classList.add('svp-quality-active');
				}
				
				option.addEventListener('click', () => this.changeQuality(quality));
				option.addEventListener('keydown', (e) => {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						this.changeQuality(quality);
					}
				});
				
				this.qualityMenu.appendChild(option);
			});
			
			// Update button text
			this.qualityBtn.querySelector('.svp-quality-text').textContent = 
				this.qualities[this.currentQuality].label.split(' ')[0];
		}
		
		async loadVideo(retryCount = 0) {
			if (this.isLoading) return;
			
			this.isLoading = true;
			this.showLoading();
			
			try {
				// Validate required data
				if (!svpAjax) {
					throw new Error('svpAjax not loaded');
				}
				
				if (!svpAjax.apiUrl || !svpAjax.nonce) {
					throw new Error('Missing API configuration');
				}
				
				// Construct URL with proper query parameters
				// Handle potential HTML entity encoding in the API URL
				const baseUrl = svpAjax.apiUrl.replace(/&amp;/g, '&');
				const url = new URL(baseUrl);
				url.searchParams.set('post', this.videoId);
				url.searchParams.set('quality', this.currentQuality);
				url.searchParams.set('nonce', svpAjax.nonce);
				
				console.log('SVP: Loading video with URL:', url.toString());
				
				const response = await fetch(url.toString(), {
					method: 'GET',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
					},
					credentials: 'same-origin'
				});
				
				console.log('SVP: Response status:', response.status);
				console.log('SVP: Response headers:', response.headers);
				
				if (!response.ok) {
					let errorText = `HTTP ${response.status}: ${response.statusText}`;
					let errorData = null;
					
					try {
						errorData = await response.json();
						if (errorData.message) {
							errorText += ` - ${errorData.message}`;
						}
					} catch (e) {
						// If we can't parse the error as JSON, just use the status text
					}
					
					// Handle nonce errors with retry mechanism
					if (response.status === 403 && errorData && errorData.code === 'invalid_nonce' && retryCount === 0) {
						console.log('SVP: Nonce error detected, attempting to refresh and retry...');
						
						// Try to refresh the nonce
						const nonceRefreshed = await this.refreshNonce();
						if (nonceRefreshed) {
							// Retry the request with the new nonce
							return this.loadVideo(1);
						}
					}
					
					throw new Error(errorText);
				}
				
				const data = await response.json();
				console.log('SVP: API response:', data);
				
				if (data.success) {
					this.video.src = data.url;
					await this.video.load();
					console.log('SVP: Video loaded successfully');
				} else {
					throw new Error(data.message || 'Failed to load video');
				}
			} catch (error) {
				console.error('SVP: Error loading video:', error);
				this.showError(error.message);
			} finally {
				this.isLoading = false;
				this.hideLoading();
			}
		}
		
		/**
		 * Attempt to refresh the nonce by making a request to WordPress
		 */
		async refreshNonce() {
			try {
				console.log('SVP: Attempting to refresh nonce...');
				
				// Create a simple nonce refresh endpoint request
				const refreshUrl = svpAjax.homeUrl + '/wp-admin/admin-ajax.php';
				const formData = new FormData();
				formData.append('action', 'svp_refresh_nonce');
				
				const response = await fetch(refreshUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				});
				
				if (response.ok) {
					const data = await response.json();
					if (data.success && data.data && data.data.nonce) {
						svpAjax.nonce = data.data.nonce;
						console.log('SVP: Nonce refreshed successfully');
						return true;
					}
				}
				
				console.log('SVP: Failed to refresh nonce');
				return false;
			} catch (error) {
				console.error('SVP: Error refreshing nonce:', error);
				return false;
			}
		}
		
		bindEvents() {
			// Video events
			this.video.addEventListener('loadedmetadata', () => this.onLoadedMetadata());
			this.video.addEventListener('timeupdate', () => this.onTimeUpdate());
			this.video.addEventListener('progress', () => this.onProgress());
			this.video.addEventListener('play', () => this.onPlay());
			this.video.addEventListener('pause', () => this.onPause());
			this.video.addEventListener('ended', () => this.onEnded());
			this.video.addEventListener('error', () => this.onError());
			this.video.addEventListener('waiting', () => this.showLoading());
			this.video.addEventListener('canplay', () => this.hideLoading());
			
			// Control events
			this.playPauseBtn.addEventListener('click', () => this.togglePlayPause());
			this.progressBar.addEventListener('click', (e) => this.onProgressClick(e));
			this.progressBar.addEventListener('mousedown', (e) => this.onProgressMouseDown(e));
			this.volumeBtn.addEventListener('click', () => this.toggleMute());
			this.volumeSlider.addEventListener('click', (e) => this.onVolumeClick(e));
			this.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
			
			// Quality menu
			this.qualityBtn.addEventListener('click', () => this.toggleQualityMenu());
			
			// Speed menu
			this.speedBtn.addEventListener('click', () => this.toggleSpeedMenu());
			this.speedMenu.addEventListener('click', (e) => this.onSpeedSelect(e));
			
			// Keyboard events
			this.container.addEventListener('keydown', (e) => this.onKeyDown(e));
			
			// Mouse events for controls visibility
			this.container.addEventListener('mousemove', () => this.showControls());
			this.container.addEventListener('mouseleave', () => this.hideControlsDelayed());
			
			// Touch events for mobile
			this.container.addEventListener('touchstart', () => this.showControls());
			
			// Close menus when clicking outside
			document.addEventListener('click', (e) => this.onDocumentClick(e));
			
			// Fullscreen events
			document.addEventListener('fullscreenchange', () => this.onFullscreenChange());
			document.addEventListener('webkitfullscreenchange', () => this.onFullscreenChange());
			document.addEventListener('mozfullscreenchange', () => this.onFullscreenChange());
		}
		
		onLoadedMetadata() {
			this.updateDuration();
			this.updateTimeDisplay();
		}
		
		onTimeUpdate() {
			this.updateProgress();
			this.updateTimeDisplay();
		}
		
		onProgress() {
			this.updateBuffer();
		}
		
		onPlay() {
			this.playPauseBtn.classList.add('svp-playing');
			this.playPauseBtn.querySelector('.svp-sr-only').textContent = svpAjax.strings.pause;
			this.playPauseBtn.setAttribute('aria-label', svpAjax.strings.pause);
		}
		
		onPause() {
			this.playPauseBtn.classList.remove('svp-playing');
			this.playPauseBtn.querySelector('.svp-sr-only').textContent = svpAjax.strings.play;
			this.playPauseBtn.setAttribute('aria-label', svpAjax.strings.play);
		}
		
		onEnded() {
			this.playPauseBtn.classList.remove('svp-playing');
			this.showControls();
		}
		
		onError() {
			this.showError();
		}
		
		togglePlayPause() {
			if (this.video.paused) {
				this.video.play();
			} else {
				this.video.pause();
			}
		}
		
		onProgressClick(e) {
			const rect = this.progressBar.getBoundingClientRect();
			const percent = (e.clientX - rect.left) / rect.width;
			this.video.currentTime = percent * this.video.duration;
		}
		
		onProgressMouseDown(e) {
			this.isDragging = true;
			this.onProgressClick(e);
			
			const onMouseMove = (e) => {
				if (this.isDragging) {
					this.onProgressClick(e);
				}
			};
			
			const onMouseUp = () => {
				this.isDragging = false;
				document.removeEventListener('mousemove', onMouseMove);
				document.removeEventListener('mouseup', onMouseUp);
			};
			
			document.addEventListener('mousemove', onMouseMove);
			document.addEventListener('mouseup', onMouseUp);
		}
		
		toggleMute() {
			if (this.video.muted) {
				this.video.muted = false;
				this.volumeBtn.setAttribute('aria-label', svpAjax.strings.mute);
			} else {
				this.video.muted = true;
				this.volumeBtn.setAttribute('aria-label', svpAjax.strings.unmute);
			}
			this.updateVolumeDisplay();
		}
		
		onVolumeClick(e) {
			const rect = this.volumeSlider.getBoundingClientRect();
			const percent = (e.clientX - rect.left) / rect.width;
			this.video.volume = Math.max(0, Math.min(1, percent));
			this.video.muted = false;
			this.updateVolumeDisplay();
		}
		
		updateVolumeDisplay() {
			const volume = this.video.muted ? 0 : this.video.volume;
			this.volumeFill.style.width = (volume * 100) + '%';
			this.volumeSlider.setAttribute('aria-valuenow', Math.round(volume * 100));
		}
		
		toggleQualityMenu() {
			this.qualityContainer.classList.toggle('svp-open');
			this.speedContainer.classList.remove('svp-open');
		}
		
		toggleSpeedMenu() {
			this.speedContainer.classList.toggle('svp-open');
			this.qualityContainer.classList.remove('svp-open');
		}
		
		onSpeedSelect(e) {
			if (e.target.classList.contains('svp-speed-option')) {
				const speed = parseFloat(e.target.dataset.speed);
				this.video.playbackRate = speed;
				
				// Update active state
				this.speedMenu.querySelectorAll('.svp-speed-option').forEach(option => {
					option.classList.remove('svp-speed-active');
				});
				e.target.classList.add('svp-speed-active');
				
				// Update button text
				this.speedBtn.querySelector('.svp-speed-text').textContent = speed + '×';
				
				// Save preference
				localStorage.setItem('svpSpeed', speed);
				
				this.speedContainer.classList.remove('svp-open');
			}
		}
		
		changeQuality(quality) {
			if (quality === this.currentQuality) return;
			
			const currentTime = this.video.currentTime;
			const wasPlaying = !this.video.paused;
			
			this.currentQuality = quality;
			localStorage.setItem('svpQuality', quality);
			
			// Update menu
			this.qualityMenu.querySelectorAll('.svp-quality-option').forEach(option => {
				option.classList.remove('svp-quality-active');
			});
			this.qualityMenu.querySelector(`[data-quality="${quality}"]`).classList.add('svp-quality-active');
			
			// Update button text
			this.qualityBtn.querySelector('.svp-quality-text').textContent = 
				this.qualities[quality].label.split(' ')[0];
			
			this.qualityContainer.classList.remove('svp-open');
			
			// Load new quality
			this.loadVideo().then(() => {
				this.video.currentTime = currentTime;
				if (wasPlaying) {
					this.video.play();
				}
			});
		}
		
		toggleFullscreen() {
			if (this.isFullscreen()) {
				this.exitFullscreen();
			} else {
				this.enterFullscreen();
			}
		}
		
		isFullscreen() {
			return !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement);
		}
		
		enterFullscreen() {
			const element = this.container;
			if (element.requestFullscreen) {
				element.requestFullscreen();
			} else if (element.webkitRequestFullscreen) {
				element.webkitRequestFullscreen();
			} else if (element.mozRequestFullScreen) {
				element.mozRequestFullScreen();
			}
		}
		
		exitFullscreen() {
			if (document.exitFullscreen) {
				document.exitFullscreen();
			} else if (document.webkitExitFullscreen) {
				document.webkitExitFullscreen();
			} else if (document.mozCancelFullScreen) {
				document.mozCancelFullScreen();
			}
		}
		
		onFullscreenChange() {
			if (this.isFullscreen()) {
				this.fullscreenBtn.setAttribute('aria-label', svpAjax.strings.exitFullscreen);
			} else {
				this.fullscreenBtn.setAttribute('aria-label', svpAjax.strings.enterFullscreen);
			}
		}
		
		onKeyDown(e) {
			switch (e.key) {
				case ' ':
					e.preventDefault();
					this.togglePlayPause();
					break;
				case 'ArrowLeft':
					e.preventDefault();
					this.video.currentTime = Math.max(0, this.video.currentTime - 5);
					break;
				case 'ArrowRight':
					e.preventDefault();
					this.video.currentTime = Math.min(this.video.duration, this.video.currentTime + 5);
					break;
				case 'ArrowUp':
					e.preventDefault();
					this.video.volume = Math.min(1, this.video.volume + 0.1);
					this.updateVolumeDisplay();
					break;
				case 'ArrowDown':
					e.preventDefault();
					this.video.volume = Math.max(0, this.video.volume - 0.1);
					this.updateVolumeDisplay();
					break;
				case 'f':
				case 'F':
					e.preventDefault();
					this.toggleFullscreen();
					break;
				case 'm':
				case 'M':
					e.preventDefault();
					this.toggleMute();
					break;
				case 'Escape':
					this.qualityContainer.classList.remove('svp-open');
					this.speedContainer.classList.remove('svp-open');
					break;
			}
		}
		
		onDocumentClick(e) {
			if (!this.qualityContainer.contains(e.target)) {
				this.qualityContainer.classList.remove('svp-open');
			}
			if (!this.speedContainer.contains(e.target)) {
				this.speedContainer.classList.remove('svp-open');
			}
		}
		
		showControls() {
			this.container.classList.add('svp-controls-visible');
			this.clearControlsTimeout();
		}
		
		hideControlsDelayed() {
			this.clearControlsTimeout();
			this.controlsTimeout = setTimeout(() => {
				if (!this.video.paused) {
					this.container.classList.remove('svp-controls-visible');
				}
			}, 3000);
		}
		
		clearControlsTimeout() {
			if (this.controlsTimeout) {
				clearTimeout(this.controlsTimeout);
				this.controlsTimeout = null;
			}
		}
		
		updateProgress() {
			if (this.video.duration && !this.isDragging) {
				const percent = (this.video.currentTime / this.video.duration) * 100;
				this.progressPlayed.style.width = percent + '%';
				this.progressBar.setAttribute('aria-valuenow', Math.round(percent));
			}
		}
		
		updateBuffer() {
			if (this.video.buffered.length > 0) {
				const percent = (this.video.buffered.end(this.video.buffered.length - 1) / this.video.duration) * 100;
				this.progressBuffer.style.width = percent + '%';
			}
		}
		
		updateTimeDisplay() {
			this.currentTimeEl.textContent = this.formatTime(this.video.currentTime);
		}
		
		updateDuration() {
			this.durationEl.textContent = this.formatTime(this.video.duration);
		}
		
		formatTime(seconds) {
			if (isNaN(seconds)) return '0:00';
			
			const mins = Math.floor(seconds / 60);
			const secs = Math.floor(seconds % 60);
			return mins + ':' + (secs < 10 ? '0' : '') + secs;
		}
		
		loadUserPreferences() {
			// Load saved speed
			const savedSpeed = localStorage.getItem('svpSpeed');
			if (savedSpeed) {
				const speed = parseFloat(savedSpeed);
				this.video.playbackRate = speed;
				
				// Update speed menu
				this.speedMenu.querySelectorAll('.svp-speed-option').forEach(option => {
					option.classList.remove('svp-speed-active');
					if (parseFloat(option.dataset.speed) === speed) {
						option.classList.add('svp-speed-active');
					}
				});
				
				this.speedBtn.querySelector('.svp-speed-text').textContent = speed + '×';
			}
			
			// Load saved volume
			const savedVolume = localStorage.getItem('svpVolume');
			if (savedVolume) {
				this.video.volume = parseFloat(savedVolume);
			}
			
			this.updateVolumeDisplay();
		}
		
		setupAccessibility() {
			// Set up ARIA attributes
			this.progressBar.setAttribute('aria-valuemin', '0');
			this.progressBar.setAttribute('aria-valuemax', '100');
			this.volumeSlider.setAttribute('aria-valuemin', '0');
			this.volumeSlider.setAttribute('aria-valuemax', '100');
			
			// Disable context menu on video (basic protection)
			this.video.addEventListener('contextmenu', (e) => e.preventDefault());
		}
		
		showLoading() {
			this.loadingContainer.classList.remove('svp-hidden');
		}
		
		hideLoading() {
			this.loadingContainer.classList.add('svp-hidden');
		}
		
		showError(message) {
			this.hideLoading();
			this.errorMessage.style.display = 'block';
			
			// Update error message if custom message provided
			if (message) {
				const errorText = this.errorMessage.querySelector('p');
				if (errorText) {
					errorText.textContent = `Error loading video: ${message}`;
				}
			}
		}
	}
	
	// Initialize all players when DOM is ready
	function initPlayers() {
		const players = document.querySelectorAll('.secure-video-player');
		players.forEach(container => {
			new SecureVideoPlayer(container);
		});
	}
	
	// Initialize
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPlayers);
	} else {
		initPlayers();
	}
	
	// Save volume preference on change
	document.addEventListener('volumechange', (e) => {
		if (e.target.tagName === 'VIDEO') {
			localStorage.setItem('svpVolume', e.target.volume);
		}
	});
	
})();