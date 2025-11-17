/**
 * Featured Image Helper - Admin JavaScript
 *
 * @package Featured_Image_Helper
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Featured Image Helper Admin object.
	 */
	const FIHAdmin = {
		/**
		 * Initialize admin functionality.
		 */
		init: function () {
			this.bindEvents();
			this.setupMediaUploader();
		},

		/**
		 * Bind event listeners.
		 */
		bindEvents: function () {
			// Select all checkbox.
			$('#fih-select-all').on('change', this.handleSelectAll);

			// Generate single image.
			$('.fih-generate-single').on('click', this.generateSingleImage);

			// Refresh dashboard stats.
			$('.fih-refresh-stats').on('click', this.refreshDashboardStats);

			// Queue controls.
			$('.fih-pause-queue').on('click', this.pauseQueue);
			$('.fih-resume-queue').on('click', this.resumeQueue);

			// Clear logs confirmation.
			$('input[name="clear_logs"]').on('click', this.confirmClearLogs);

			// Auto-refresh queue stats (every 30 seconds).
			if ($('.fih-queue-stats').length) {
				setInterval(this.refreshQueueStats, 30000);
			}
		},

		/**
		 * Setup media uploader for default image selection.
		 */
		setupMediaUploader: function () {
			let mediaUploader;

			$('.fih-upload-image').on('click', function (e) {
				e.preventDefault();

				// If the uploader object has already been created, reopen it.
				if (mediaUploader) {
					mediaUploader.open();
					return;
				}

				// Create new media uploader.
				mediaUploader = wp.media({
					title: fihAdmin.strings.selectImage,
					button: {
						text: fihAdmin.strings.useThisImage,
					},
					multiple: false,
				});

				// When an image is selected, update the input and preview.
				mediaUploader.on('select', function () {
					const attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#fih_default_image_id').val(attachment.id);

					// Update preview.
					const preview = attachment.sizes.thumbnail
						? attachment.sizes.thumbnail.url
						: attachment.url;
					$('.fih-image-preview').html('<img src="' + preview + '" />');
					$('.fih-remove-image').show();
				});

				// Open the uploader.
				mediaUploader.open();
			});

			// Remove image.
			$('.fih-remove-image').on('click', function (e) {
				e.preventDefault();
				$('#fih_default_image_id').val('');
				$('.fih-image-preview').html('');
				$(this).hide();
			});
		},

		/**
		 * Handle select all checkbox.
		 */
		handleSelectAll: function () {
			const isChecked = $(this).prop('checked');
			$('input[name="post_ids[]"]').prop('checked', isChecked);
		},

		/**
		 * Generate single featured image.
		 */
		generateSingleImage: function (e) {
			e.preventDefault();

			const $button = $(this);
			const postId = $button.data('post-id');
			const nonce = $button.data('nonce');

			// Disable button and show loading state.
			$button.prop('disabled', true).addClass('fih-generating');
			$button.html(fihAdmin.strings.generating + ' <span class="fih-spinner"></span>');

			// Make AJAX request.
			$.ajax({
				url: fihAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fih_generate_single_image',
					post_id: postId,
					nonce: fihAdmin.nonce,
				},
				success: function (response) {
					if (response.success) {
						// Show success message.
						FIHAdmin.showMessage(response.data.message, 'success');

						// Remove the row from the table.
						$button.closest('tr').fadeOut(400, function () {
							$(this).remove();
						});
					} else {
						// Show error message.
						FIHAdmin.showMessage(response.data.message, 'error');

						// Re-enable button.
						$button.prop('disabled', false).removeClass('fih-generating');
						$button.html(fihAdmin.strings.generate);
					}
				},
				error: function () {
					// Show error message.
					FIHAdmin.showMessage(fihAdmin.strings.error, 'error');

					// Re-enable button.
					$button.prop('disabled', false).removeClass('fih-generating');
					$button.html(fihAdmin.strings.generate);
				},
			});
		},

		/**
		 * Refresh dashboard statistics.
		 */
		refreshDashboardStats: function (e) {
			e.preventDefault();

			const $button = $(this);
			$button.prop('disabled', true);

			$.ajax({
				url: fihAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fih_dashboard_stats',
					nonce: fihAdmin.nonce,
				},
				success: function (response) {
					if (response.success) {
						// Update stats display.
						location.reload();
					}
					$button.prop('disabled', false);
				},
				error: function () {
					$button.prop('disabled', false);
				},
			});
		},

		/**
		 * Refresh queue statistics.
		 */
		refreshQueueStats: function () {
			const $container = $('.fih-queue-stats');

			if (!$container.length) {
				return;
			}

			$.ajax({
				url: fihAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fih_get_queue_stats',
					nonce: fihAdmin.nonce,
				},
				success: function (response) {
					if (response.success) {
						// Update stats display.
						const stats = response.data;
						$container.find('td:contains("Pending:") + td strong').text(stats.pending);
						$container.find('td:contains("Processing:") + td strong').text(stats.processing);
						$container.find('td:contains("Completed:") + td strong').text(stats.completed);
						$container.find('td:contains("Failed:") + td strong').text(stats.failed);
					}
				},
			});
		},

		/**
		 * Pause queue processing.
		 */
		pauseQueue: function (e) {
			e.preventDefault();

			const $button = $(this);
			$button.prop('disabled', true);

			$.ajax({
				url: fihAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fih_pause_queue',
					nonce: fihAdmin.nonce,
				},
				success: function (response) {
					if (response.success) {
						FIHAdmin.showMessage(response.data.message, 'success');
						location.reload();
					}
					$button.prop('disabled', false);
				},
				error: function () {
					$button.prop('disabled', false);
				},
			});
		},

		/**
		 * Resume queue processing.
		 */
		resumeQueue: function (e) {
			e.preventDefault();

			const $button = $(this);
			$button.prop('disabled', true);

			$.ajax({
				url: fihAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fih_resume_queue',
					nonce: fihAdmin.nonce,
				},
				success: function (response) {
					if (response.success) {
						FIHAdmin.showMessage(response.data.message, 'success');
						location.reload();
					}
					$button.prop('disabled', false);
				},
				error: function () {
					$button.prop('disabled', false);
				},
			});
		},

		/**
		 * Confirm clear logs action.
		 */
		confirmClearLogs: function (e) {
			if (!confirm(fihAdmin.strings.confirmClear)) {
				e.preventDefault();
				return false;
			}
		},

		/**
		 * Show admin message.
		 */
		showMessage: function (message, type) {
			const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
			const $notice = $('<div>', {
				class: 'notice fih-notice ' + messageClass + ' is-dismissible',
				html: '<p>' + message + '</p>',
			});

			// Add to page.
			$('.wrap h1').after($notice);

			// Auto-dismiss after 5 seconds.
			setTimeout(function () {
				$notice.fadeOut(400, function () {
					$(this).remove();
				});
			}, 5000);

			// Scroll to message.
			$('html, body').animate(
				{
					scrollTop: $notice.offset().top - 100,
				},
				500
			);
		},
	};

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function () {
		FIHAdmin.init();
	});
})(jQuery);
