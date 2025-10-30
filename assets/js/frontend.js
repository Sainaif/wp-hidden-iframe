/**
 * Secure Embed Manager - Frontend JavaScript
 *
 * Handles video toggling and secure URL fetching via AJAX.
 * Real video URLs are never exposed in HTML - they're fetched on-demand.
 *
 * @package    Secure_Embed
 * @since      1.0.0
 */

(function() {
	'use strict';

	/**
	 * Initialize when DOM is ready
	 */
	document.addEventListener('DOMContentLoaded', function() {

		// Get all video togglers
		const togglers = document.querySelectorAll('.video-toggler');

		// Add click handler to each toggler
		togglers.forEach(function(toggler) {
			toggler.addEventListener('click', function(e) {
				e.preventDefault();

				const uniqueID = toggler.getAttribute('data-id');
				const container = toggler.closest('.video-container');

				if (!container) {
					console.error('SecureEmbed: Video container not found for ID:', uniqueID);
					return;
				}

				const iframe = container.querySelector('iframe');
				const contentDiv = container.querySelector('.video-content');

				if (!iframe || !contentDiv) {
					console.error('SecureEmbed: Iframe or content div not found for ID:', uniqueID);
					return;
				}

				// Check if video is currently visible
				const isCurrentlyVisible = contentDiv.style.display !== 'none' && contentDiv.style.display !== '';

				if (!isCurrentlyVisible) {
					// Video is hidden, fetch URL and show it
					fetchAndShowVideo(uniqueID, iframe, contentDiv);
				} else {
					// Video is visible, hide it
					contentDiv.style.display = 'none';
					iframe.src = 'about:blank';
				}
			});
		});

	});

	/**
	 * Fetch video URL from server and display it
	 *
	 * @param {string} uniqueID - The unique video ID
	 * @param {HTMLElement} iframe - The iframe element
	 * @param {HTMLElement} contentDiv - The content container
	 */
	function fetchAndShowVideo(uniqueID, iframe, contentDiv) {
		// Create AJAX request
		const xhr = new XMLHttpRequest();
		xhr.open('POST', secureEmbedData.ajaxUrl, true);
		xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

		// Build parameters with nonce for security
		const params = 'action=secure_embed_fetch_link' +
					   '&video_id=' + encodeURIComponent(uniqueID) +
					   '&nonce=' + encodeURIComponent(secureEmbedData.nonce);

		// Handle successful response
		xhr.onload = function() {
			if (xhr.status === 200) {
				try {
					const response = JSON.parse(xhr.responseText);

					if (response.success && response.data && response.data.link) {
						// Success - load the video
						iframe.src = response.data.link;
						contentDiv.style.display = 'block';
					} else {
						// Error from server
						const errorMsg = response.data && response.data.message
							? response.data.message
							: 'Unknown error';
						console.error('SecureEmbed: Failed to get video URL -', errorMsg, 'ID:', uniqueID);
						showError(contentDiv, 'Could not load video: ' + errorMsg);
					}
				} catch (parseError) {
					// JSON parse error
					console.error('SecureEmbed: Error parsing response -', parseError, xhr.responseText, 'ID:', uniqueID);
					showError(contentDiv, 'Invalid response from server');
				}
			} else {
				// HTTP error
				console.error('SecureEmbed: AJAX request failed with status:', xhr.status, 'ID:', uniqueID);
				showError(contentDiv, 'Server request failed (Status: ' + xhr.status + ')');
			}
		};

		// Handle network errors
		xhr.onerror = function() {
			console.error('SecureEmbed: AJAX request network error. ID:', uniqueID);
			showError(contentDiv, 'Network problem prevented video loading');
		};

		// Send the request
		xhr.send(params);
	}

	/**
	 * Display error message in content div
	 *
	 * @param {HTMLElement} contentDiv - The content container
	 * @param {string} message - Error message to display
	 */
	function showError(contentDiv, message) {
		contentDiv.innerHTML = '<p style="color:red;">Error: ' + message + '</p>';
		contentDiv.style.display = 'block';
	}

})();
