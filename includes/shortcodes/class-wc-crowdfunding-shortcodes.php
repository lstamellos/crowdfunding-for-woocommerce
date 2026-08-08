<?php
/**
 * Crowdfunding for WooCommerce - Shortcodes
 *
 * @version 2.7.0
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Crowdfunding_Shortcodes' ) ) :

class Alg_WC_Crowdfunding_Shortcodes {

	/**
	 * Constructor.
	 *
	 * @version 2.3.6
	 */
	function __construct() {
		return true;
	}

	/**
	 * Resolve and normalize a product ID supplied to a shortcode.
	 */
	protected function get_shortcode_product_id( $atts ) {
		return isset( $atts['product_id'] ) ? absint( $atts['product_id'] ) : absint( get_the_ID() );
	}

	/**
	 * output_shortcode.
	 *
	 * @version 2.7.0
	 * @since   1.0.0
	 */
	function output_shortcode( $value, $atts ) {
		if ( '' != $value || ( isset( $atts['show_if_zero'] ) && 'yes' === $atts['show_if_zero'] ) ) {
			if ( empty( $atts ) ) {
				$atts = array();
			}
			if ( ! isset( $atts['before'] ) ) {
				$atts['before'] = '';
			}
			if ( ! isset( $atts['after'] ) ) {
				$atts['after'] = '';
			}
			if ( isset( $atts['type'] ) ) {
				switch ( $atts['type'] ) {
					case 'price':
						$value = apply_filters( 'alg_crowdfunding_output_shortcode_price', wc_price( $value ), $value );
						break;
					case 'percent':
						$total_value     = isset( $atts['total_value'] ) && is_numeric( $atts['total_value'] ) ? (float) $atts['total_value'] : 0.0;
						$round_precision = isset( $atts['round_precision'] ) ? min( 6, absint( $atts['round_precision'] ) ) : 0;
						$numeric_value   = is_numeric( $value ) ? (float) $value : 0.0;
						$value           = ( 0.0 !== $total_value ) ? round( $numeric_value / $total_value * 100, $round_precision ) : 100;
						break;
				}
			}
			$before = wp_kses_post( (string) $atts['before'] );
			$after  = wp_kses_post( (string) $atts['after'] );

			return $before . $value . $after;
		}
		return '';
	}

}

endif;
