/* global jQuery */
(function ($) {
	'use strict';

	const visibleSelector = '#alg_crowdfunding_open_price';
	const expressName = 'wc_crowdfunding_open_price';
	let refreshTimer = null;

	function getForm($input) {
		const $cartForm = $input.closest('form.cart');
		return $cartForm.length ? $cartForm : $('form.cart').first();
	}

	function ensureExpressAlias($input) {
		const $form = getForm($input);
		if (!$form.length) {
			return $();
		}

		let $alias = $form.find('input[name="' + expressName + '"]');
		if (!$alias.length) {
			$alias = $('<input>', {
				type: 'hidden',
				name: expressName,
				value: $input.val()
			}).appendTo($form);
		}

		return $alias;
	}

	function syncAmount($input, refreshStripe) {
		const $alias = ensureExpressAlias($input);
		if ($alias.length) {
			$alias.val($input.val());
		}

		if (!refreshStripe) {
			return;
		}

		window.clearTimeout(refreshTimer);
		refreshTimer = window.setTimeout(function () {
			$(document.body).trigger('woocommerce_variation_has_changed');
		}, 150);
	}

	$(function () {
		const $input = $(visibleSelector);
		if (!$input.length) {
			return;
		}

		syncAmount($input, false);

		$input.on('input change', function () {
			syncAmount($(this), true);
		});
	});
})(jQuery);
