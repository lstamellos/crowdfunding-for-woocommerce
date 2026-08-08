<?php
/**
 * WooCommerce Crowdfunding Product Open Pricing
 *
 * The WooCommerce Crowdfunding Product Open Pricing class.
 *
 * @version 3.0.2
 * @since   2.2.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Alg_Crowdfunding_Product_Open_Pricing' ) ) :

class Alg_Crowdfunding_Product_Open_Pricing {

	/**
	 * Constructor.
	 *
	 * @version 3.0.2
	 * @since   2.2.0
	 */
	function __construct() {
		if ( 'yes' === get_option( 'alg_crowdfunding_open_price_hide_original_price', 'yes' ) ) {
			add_filter( 'woocommerce_get_price_html',                array( $this, 'hide_original_price' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_get_variation_price_html',      array( $this, 'hide_original_price' ), PHP_INT_MAX, 2 );
		}
		if ( 'yes' === get_option( 'alg_crowdfunding_open_price_hide_qty', 'yes' ) ) {
			add_filter( 'woocommerce_is_sold_individually',      array( $this, 'hide_quantity_input_field' ), PHP_INT_MAX, 2 );
		}
		add_filter( 'woocommerce_is_purchasable',                array( $this, 'is_purchasable' ), PHP_INT_MAX - 100, 2 );
		add_filter( 'woocommerce_product_supports',              array( $this, 'disable_add_to_cart_ajax' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_product_add_to_cart_url',       array( $this, 'add_to_cart_url' ), PHP_INT_MAX, 2 );
		if ( 'yes' === get_option( 'alg_wc_crowdfunding_product_open_price_add_to_cart', 'yes' ) ) {
			add_filter( 'woocommerce_product_add_to_cart_text',  array( $this, 'add_to_cart_text' ), PHP_INT_MAX, 2 );
		}
		add_action( 'woocommerce_before_add_to_cart_button',     array( $this, 'add_open_price_input_field_to_frontend' ), PHP_INT_MAX );
		add_filter( 'woocommerce_add_to_cart_validation',        array( $this, 'validate_open_price_on_add_to_cart' ), PHP_INT_MAX, 6 );
		add_filter( 'woocommerce_add_cart_item_data',            array( $this, 'add_open_price_to_cart_item_data' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_add_cart_item',                 array( $this, 'add_open_price_to_cart_item' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session',    array( $this, 'get_cart_item_open_price_from_session' ), PHP_INT_MAX, 3 );
	}

	/**
	 * is_open_price_product.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 */
	function is_open_price_product( $_product ) {
		$_product_id                = alg_wc_crdfnd_get_product_id_or_variation_parent_id( $_product );
		$is_crowdfudning            = ( 'yes' === get_post_meta( $_product_id, '_' . 'alg_crowdfunding_enabled', true ) );
		$is_crowdfudning_open_price = ( 'yes' === get_post_meta( $_product_id, '_' . 'alg_crowdfunding_product_open_price_enabled', true ) );
		return ( $is_crowdfudning && $is_crowdfudning_open_price );
	}

	/**
	 * is_purchasable.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 */
	function is_purchasable( $purchasable, $_product ) {
		if ( $this->is_open_price_product( $_product ) ) {
			$purchasable = true;

			// Products must exist of course
			if ( ! $_product->exists() ) {
				$purchasable = false;

			// Other products types need a price to be set
			/* } elseif ( $_product->get_price() === '' ) {
				$purchasable = false; */

			// Check the product is published
			} elseif ( 'publish' !== alg_wc_crdfnd_get_product_post_status( $_product ) && ! current_user_can( 'edit_post', alg_wc_crdfnd_get_product_id_or_variation_parent_id( $_product ) ) ) {
				$purchasable = false;
			}
		}
		return $purchasable;
	}

	/**
	 * add_to_cart_text.
	 *
	 * @version 3.0.1
	 * @since   2.2.0
	 */
	function add_to_cart_text( $text, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? get_option( 'alg_wc_crowdfunding_product_open_price_add_to_cart_text', __( 'Read more', 'woocommerce' ) ) : $text;
	}

	/**
	 * disable_add_to_cart_ajax.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 */
	function disable_add_to_cart_ajax( $supports, $feature, $_product ) {
		if ( $this->is_open_price_product( $_product ) && 'ajax_add_to_cart' === $feature ) {
			$supports = false;
		}
		return $supports;
	}

	/**
	 * add_to_cart_url.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 */
	function add_to_cart_url( $url, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? get_permalink( alg_wc_crdfnd_get_product_id_or_variation_parent_id( $_product ) ) : $url;
	}

	/**
	 * hide_quantity_input_field.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 */
	function hide_quantity_input_field( $return, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? true : $return;
	}

	/**
	 * hide_original_price.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 */
	function hide_original_price( $price, $_product ) {
		return ( $this->is_open_price_product( $_product ) ) ? '' : $price;
	}

	/**
	 * validate_open_price_on_add_to_cart.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 */
	function validate_open_price_on_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		if ( ! $passed ) {
			return false;
		}

		$the_product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $the_product || ! $this->is_open_price_product( $the_product ) ) {
			return $passed;
		}

		$open_price = $this->get_submitted_open_price();
		if ( null === $open_price || '' === $open_price ) {
			wc_add_notice(
				get_option( 'alg_crowdfunding_product_open_price_messages_required', __( 'Price is required!', 'crowdfunding-for-woocommerce' ) ),
				'error'
			);
			return false;
		}

		if ( false === $open_price ) {
			wc_add_notice( __( 'Please enter a valid price.', 'crowdfunding-for-woocommerce' ), 'error' );
			return false;
		}

		$_product_id = alg_wc_crdfnd_get_product_id_or_variation_parent_id( $the_product );
		$min_price   = $this->normalize_open_price( get_post_meta( $_product_id, '_alg_crowdfunding_product_open_price_min_price', true ), true );
		$max_price   = $this->normalize_open_price( get_post_meta( $_product_id, '_alg_crowdfunding_product_open_price_max_price', true ), true );

		if ( false !== $min_price && '' !== $min_price && (float) $open_price < (float) $min_price ) {
			wc_add_notice(
				get_option( 'alg_crowdfunding_product_open_price_messages_to_small', __( 'Price is too low!', 'crowdfunding-for-woocommerce' ) ),
				'error'
			);
			return false;
		}

		if ( false !== $max_price && '' !== $max_price && (float) $max_price > 0 && (float) $open_price > (float) $max_price ) {
			wc_add_notice(
				get_option( 'alg_crowdfunding_product_open_price_messages_to_big', __( 'Price is too high!', 'crowdfunding-for-woocommerce' ) ),
				'error'
			);
			return false;
		}

		return true;
	}

	/**
	 * Return a normalized submitted open price.
	 *
	 * @return string|false|null Null means missing, false means invalid.
	 */
	private function get_submitted_open_price() {
		if ( ! isset( $_POST['alg_crowdfunding_open_price'] ) ) {
			return null;
		}

		$raw_value = wp_unslash( $_POST['alg_crowdfunding_open_price'] );
		if ( is_array( $raw_value ) ) {
			return false;
		}

		return $this->normalize_open_price( $raw_value, true );
	}

	/**
	 * Normalize an amount using WooCommerce decimal rules.
	 *
	 * @param mixed $value Value to normalize.
	 * @param bool  $allow_empty Whether an empty value is allowed.
	 * @return string|false
	 */
	private function normalize_open_price( $value, $allow_empty = false ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return false;
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return $allow_empty ? '' : false;
		}

		$value = wc_format_decimal( $value, wc_get_price_decimals() );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return false;
		}

		$numeric_value = (float) $value;
		if ( ! is_finite( $numeric_value ) || $numeric_value < 0 ) {
			return false;
		}

		return $value;
	}

	/**
	 * get_cart_item_open_price_from_session.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 */
	function get_cart_item_open_price_from_session( $item, $values, $key ) {
		if ( array_key_exists( 'alg_crowdfunding_open_price', $values ) && isset( $item['data'] ) && is_a( $item['data'], 'WC_Product' ) ) {
			$open_price = $this->normalize_open_price( $values['alg_crowdfunding_open_price'] );
			if ( false !== $open_price ) {
				$item['alg_crowdfunding_open_price'] = $open_price;
				$item['data']->set_price( (float) $open_price );
			}
		}
		return $item;
	}

	/**
	 * add_open_price_to_cart_item_data.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 */
	function add_open_price_to_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$the_product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( $the_product && $this->is_open_price_product( $the_product ) ) {
			$open_price = $this->get_submitted_open_price();
			if ( is_string( $open_price ) && '' !== $open_price ) {
				$cart_item_data['alg_crowdfunding_open_price'] = $open_price;
			}
		}
		return $cart_item_data;
	}

	/**
	 * add_open_price_to_cart_item.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 */
	function add_open_price_to_cart_item( $cart_item_data, $cart_item_key ) {
		if ( isset( $cart_item_data['alg_crowdfunding_open_price'], $cart_item_data['data'] ) && is_a( $cart_item_data['data'], 'WC_Product' ) ) {
			$open_price = $this->normalize_open_price( $cart_item_data['alg_crowdfunding_open_price'] );
			if ( false !== $open_price ) {
				$cart_item_data['alg_crowdfunding_open_price'] = $open_price;
				$cart_item_data['data']->set_price( (float) $open_price );
			}
		}
		return $cart_item_data;
	}

	/**
	 * add_open_price_input_field_to_frontend.
	 *
	 * @version 3.0.0
	 * @since   2.2.0
	 * @todo    [dev] (maybe) `$placeholder = $the_product->get_price();`
	 */
	function add_open_price_input_field_to_frontend() {
		$the_product = wc_get_product();
		if ( ! $the_product || ! $this->is_open_price_product( $the_product ) ) {
			return;
		}

		$title       = get_option( 'alg_crowdfunding_product_open_price_label_frontend', __( 'Name Your Price', 'crowdfunding-for-woocommerce' ) );
		$_product_id = alg_wc_crdfnd_get_product_id_or_variation_parent_id( $the_product );
		$submitted   = $this->get_submitted_open_price();
		$default     = $this->normalize_open_price( get_post_meta( $_product_id, '_alg_crowdfunding_product_open_price_default_price', true ), true );
		$value       = is_string( $submitted ) && '' !== $submitted ? $submitted : ( false !== $default ? $default : '' );

		$price_decimals = get_post_meta( $_product_id, '_alg_crowdfunding_product_open_price_step', true );
		$price_decimals = '' === $price_decimals ? wc_get_price_decimals() : absint( $price_decimals );
		$price_decimals = min( 6, $price_decimals );
		$step = $price_decimals > 0 ? '0.' . str_repeat( '0', $price_decimals - 1 ) . '1' : '1';

		$min_price = $this->normalize_open_price( get_post_meta( $_product_id, '_alg_crowdfunding_product_open_price_min_price', true ), true );
		$max_price = $this->normalize_open_price( get_post_meta( $_product_id, '_alg_crowdfunding_product_open_price_max_price', true ), true );

		$attributes = array(
			'type'  => 'number',
			'class' => 'text',
			'style' => 'width:75px;text-align:center;',
			'name'  => 'alg_crowdfunding_open_price',
			'id'    => 'alg_crowdfunding_open_price',
			'value' => $value,
			'step'  => $step,
		);
		if ( false !== $min_price && '' !== $min_price ) {
			$attributes['min'] = $min_price;
		}
		if ( false !== $max_price && '' !== $max_price && (float) $max_price > 0 ) {
			$attributes['max'] = $max_price;
		}

		$attribute_html = '';
		foreach ( $attributes as $attribute => $attribute_value ) {
			$attribute_html .= sprintf( ' %s="%s"', esc_attr( $attribute ), esc_attr( $attribute_value ) );
		}
		$input_field = '<input' . $attribute_html . '>';

		$template = get_option(
			'alg_crowdfunding_open_price_template',
			'<label for="alg_crowdfunding_open_price">%title%</label> %input_field% %currency_symbol%'
		);
		$template = wp_kses_post( (string) $template );

		echo str_replace(
			array( '%title%', '%input_field%', '%currency_symbol%' ),
			array( esc_html( $title ), $input_field, esc_html( get_woocommerce_currency_symbol() ) ),
			$template
		);
	}


}

endif;

return new Alg_Crowdfunding_Product_Open_Pricing();
