/* global jQuery */
(function ($) {
	'use strict';

	const visibleSelector = '#alg_crowdfunding_open_price';
	const expressName = 'wc_crowdfunding_open_price';
	const stripeSelectedProductEndpoint = 'wc_stripe_get_selected_product_data';
	let refreshTimer = null;
	let deferredValueCheck = null;
	let lastSyncedAmount = null;

	function getForm($input) {
		const $cartForm = $input.closest('form.cart');
		return $cartForm.length ? $cartForm : $('form.cart').first();
	}

	function normalizeForNumberInput(value) {
		return String(value || '').trim().replace(',', '.');
	}

	function getCurrentAmount() {
		return normalizeForNumberInput($(visibleSelector).val());
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

	function injectAmountIntoData(data) {
		const amount = getCurrentAmount();
		if (!amount) {
			return data;
		}

		if (typeof data === 'string') {
			const encodedName = encodeURIComponent(expressName);
			const parts = data ? data.split('&').filter(Boolean) : [];
			const filtered = parts.filter(function (part) {
				return part.split('=')[0] !== encodedName;
			});
			filtered.push(encodedName + '=' + encodeURIComponent(amount));
			return filtered.join('&');
		}

		if (!data || typeof data !== 'object') {
			data = {};
		}
		data[expressName] = amount;
		return data;
	}

	function registerStripeSelectedProductBridge() {
		const originalPost = $.post;
		if (originalPost && !originalPost.algCrowdfundingRuntimePricing) {
			const wrappedPost = function (url, data, success, dataType) {
				if (
					typeof url === 'string' &&
					url.indexOf(stripeSelectedProductEndpoint) !== -1
				) {
					data = injectAmountIntoData(data);
				}
				return originalPost.call(this, url, data, success, dataType);
			};
			wrappedPost.algCrowdfundingRuntimePricing = true;
			$.post = wrappedPost;
		}

		// Keep the prefilter as a fallback for integrations that call $.ajax directly.
		$.ajaxPrefilter(function (options) {
			if (
				typeof options.url === 'string' &&
				options.url.indexOf(stripeSelectedProductEndpoint) !== -1
			) {
				options.data = injectAmountIntoData(options.data);
			}
		});
	}

	function syncAmount($input, refreshStripe) {
		const normalized = normalizeForNumberInput($input.val());
		const $alias = ensureExpressAlias($input);
		if ($alias.length) {
			$alias.val(normalized);
		}

		if (!refreshStripe) {
			lastSyncedAmount = normalized;
			return;
		}

		// Do not rebuild Stripe's wallet element when an interaction did not
		// actually change the contribution amount.
		if (normalized === lastSyncedAmount) {
			return;
		}
		lastSyncedAmount = normalized;

		window.clearTimeout(refreshTimer);
		refreshTimer = window.setTimeout(function () {
			$(document.body).trigger('woocommerce_variation_has_changed');
		}, 150);
	}

	function scheduleValueCheck($input) {
		if (deferredValueCheck) {
			window.cancelAnimationFrame(deferredValueCheck);
		}

		// Native number-input spinner controls are browser UI rather than DOM
		// children. Some browsers do not emit jQuery input/change reliably for
		// spinner clicks, so read the post-interaction value on the next frame.
		deferredValueCheck = window.requestAnimationFrame(function () {
			deferredValueCheck = null;
			syncAmount($input, true);
		});
	}

	registerStripeSelectedProductBridge();

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

		// Explicitly cover native spinner arrows, keyboard stepping and wheel
		// stepping. If input/change already handled the value, lastSyncedAmount
		// suppresses a duplicate Stripe refresh.
		$input.on('click mouseup pointerup keyup wheel', function () {
			scheduleValueCheck($(this));
		});
	});
})(jQuery);
