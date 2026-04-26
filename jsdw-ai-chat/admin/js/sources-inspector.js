(function ($) {
	'use strict';

	function openModal() {
		var $m = $('#jsdw-source-inspector-modal');
		$m.attr('aria-hidden', 'false').css('display', 'flex');
		$('body').addClass('jsdw-source-inspector-open');
	}

	function closeModal() {
		var $m = $('#jsdw-source-inspector-modal');
		$m.attr('aria-hidden', 'true').css('display', 'none');
		$('body').removeClass('jsdw-source-inspector-open');
	}

	$(document).on('click', '.jsdw-source-inspector-open', function (e) {
		e.preventDefault();
		var sid = $(this).data('source-id');
		if (!sid || typeof jsdwSourcesInspector === 'undefined') {
			return;
		}
		var $body = $('#jsdw-source-inspector-modal .jsdw-source-inspector-body');
		$body.html('<p class="jsdw-source-inspector-loading">' + (jsdwSourcesInspector.loadingText || '…') + '</p>');
		openModal();
		$.post(jsdwSourcesInspector.ajax_url, {
			action: 'jsdw_ai_chat_source_inspector',
			nonce: jsdwSourcesInspector.nonce,
			source_id: sid
		})
			.done(function (resp) {
				if (resp && resp.success && resp.data && resp.data.html) {
					$body.html(resp.data.html);
				} else {
					$body.html('<p class="jsdw-source-inspector-error">' + (jsdwSourcesInspector.errorText || 'Error') + '</p>');
				}
			})
			.fail(function () {
				$body.html('<p class="jsdw-source-inspector-error">' + (jsdwSourcesInspector.errorText || 'Error') + '</p>');
			});
	});

	$(document).on('click', '.jsdw-source-inspector-close, .jsdw-source-inspector-backdrop', function (e) {
		e.preventDefault();
		closeModal();
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $('#jsdw-source-inspector-modal').is(':visible')) {
			closeModal();
		}
	});
})(jQuery);
