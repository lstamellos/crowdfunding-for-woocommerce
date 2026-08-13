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

	function normalizeForNumberInput(value) {
		return String(value || '').trim().replace(',', '.');
	}

	function upgradeVisibleInput($input) {
		const step = $input.attr('data-step');
		const min = $input.attr('data-min');
		const max = $input.attr('data-max');
		const value = normalizeForNumberInput($input.val());

		$input.attr('type', 'number');
		$input.attr('inputmode', 'decimal');
		if (step) {
			$input.attr('step', step);
		}
		if (min) {
			$input.attr('min', min);
		}
		if (max) {
			$input.attr('max', max);
		}
		$input.val(value);
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
				value: normalizeForNumberInput($input.val())
			}).appendTo($form);
		}

		return $alias;
	}

	function syncAmount($input, refreshStripe) {
		const normalized = normalizeForNumberInput($input.val());
		const $alias = ensureExpressAlias($input);
		if ($alias.length) {
			$alias.val(normalized);
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

		upgradeVisibleInput($input);
		syncAmount($input, false);

		$input.on('input change', function () {
			syncAmount($(this), true);
		});
	});
})(jQuery);
