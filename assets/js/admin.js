/**
 * Secure Embed Manager - Admin JavaScript
 *
 * Handles admin interface interactions including copy embed code functionality.
 *
 * @package    Secure_Embed
 * @since      1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function() {

		/**
		 * Copy embed code to clipboard
		 */
		$('.secure-embed-copy-btn').on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const uniqueId = $btn.data('id');
			const embedName = $btn.data('name');

			// Build embed code snippet
			const embedCode = `<div class="video-container">
    <a href="#" class="video-toggler" data-id="${uniqueId}">${embedName}</a>
    <div class="video-content" style="display:none;">
        <iframe src="about:blank" frameborder="0" width="640" height="360" allowfullscreen></iframe>
    </div>
</div>`;

			// Use the hidden textarea for compatibility
			const $textarea = $('#secure-embed-copy-textarea');
			if ($textarea.length === 0) {
				// Create textarea if it doesn't exist
				$('body').append('<textarea id="secure-embed-copy-textarea" style="position:absolute;left:-9999px;"></textarea>');
			}

			// Copy to clipboard
			const textarea = document.getElementById('secure-embed-copy-textarea');
			textarea.value = embedCode;
			textarea.select();
			textarea.setSelectionRange(0, 99999); // For mobile devices

			try {
				document.execCommand('copy');
				showNotice(secureEmbedAdmin.strings.copiedToClipboard, 'success');
			} catch (err) {
				console.error('Failed to copy:', err);
				showNotice(secureEmbedAdmin.strings.copyFailed, 'error');
			}

			// Deselect
			textarea.blur();
		});

		/**
		 * Show admin notice
		 */
		function showNotice(message, type) {
			type = type || 'info';

			const $notice = $('<div>')
				.addClass('notice notice-' + type + ' is-dismissible')
				.css({
					'position': 'fixed',
					'top': '32px',
					'right': '20px',
					'z-index': '9999',
					'max-width': '400px'
				})
				.html('<p>' + message + '</p>');

			$('body').append($notice);

			// Auto-dismiss after 3 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 3000);
		}

	});

})(jQuery);
