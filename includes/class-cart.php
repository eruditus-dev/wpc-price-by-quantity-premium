<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcpq_Cart' ) ) {
	class Wpcpq_Cart {
		protected static $instance = null;
		protected static $calculated = false;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_action( 'woocommerce_before_calculate_totals', [ $this, 'before_calculate_totals' ], 9999 );
			add_action( 'woocommerce_before_mini_cart_contents', [ $this, 'mini_cart_contents' ], 9999 );
			add_filter( 'woocommerce_cart_item_price', [ $this, 'cart_item_price' ], 10, 3 );
		}

		public function before_calculate_totals( $cart ) {
			if ( ! self::$calculated ) {
				if ( ! defined( 'DOING_AJAX' ) && is_admin() ) {
					// This is necessary for WC 3.0+
					return;
				}

				if ( ! empty( $cart->cart_contents ) ) {
					foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
						$product_id = $cart_item['data']->get_id();

						if ( Wpcpq_Helper()::is_enable( $product_id ) && apply_filters( 'wpcpq_enable_cart_item', true, $cart_item ) && ! apply_filters( 'wpcpq_ignore', false, $cart_item ) ) {
							$ori_product = apply_filters( 'wpcpq_get_ori_product', wc_get_product( $product_id ), $cart_item, $cart_item_key );
							$ori_price   = apply_filters( 'wpcpq_get_ori_product_price', $ori_product->get_price(), $cart_item, $cart_item_key );
							$quantity    = apply_filters( 'wpcpq_cart_item_quantity', $cart_item['quantity'], $cart_item, $cart_item_key );
							$pricing     = Wpcpq_Helper()::get_pricing( $product_id );
							$new_price   = Wpcpq_Helper()::get_price( $pricing['method'], $pricing['tiers'], $quantity, $ori_price );
							$new_price   = round( $new_price, wc_get_price_decimals() );

							$cart_item['data']->set_price( $new_price );

							if ( empty( $cart_item['wpcpq_qty'] ) || ( $cart_item['quantity'] != $cart_item['wpcpq_qty'] ) ) {
								// store price at current quantity
								WC()->cart->cart_contents[ $cart_item_key ]['wpcpq_qty']       = $cart_item['quantity'];
								WC()->cart->cart_contents[ $cart_item_key ]['wpcpq_ori_price'] = $ori_price;
								WC()->cart->cart_contents[ $cart_item_key ]['wpcpq_price']     = $new_price;
							}
						}
					}
				}

				self::$calculated = true;
			}
		}

		public function cart_item_price( $price, $cart_item, $cart_item_key ) {
			$ori_product = apply_filters( 'wpcpq_get_ori_product', wc_get_product( $cart_item['data']->get_id() ), $cart_item, $cart_item_key );
			$ori_price   = apply_filters( 'wpcpq_get_ori_product_price', $ori_product->get_price(), $cart_item, $cart_item_key );
			$new_price   = $cart_item['data']->get_price();

			if ( (float) $ori_price !== (float) $new_price ) {
				return wc_format_sale_price( $ori_price, $new_price );
			}

			return $price;
		}

		public function mini_cart_contents() {
			WC()->cart->calculate_totals();
		}


	}

	function Wpcpq_Cart() {
		return Wpcpq_Cart::instance();
	}

	Wpcpq_Cart();
}