<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcpq_Helper' ) ) {
	class Wpcpq_Helper {
		protected static $settings = [];
		protected static $prices = [];
		protected static $localization = [];
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		function __construct() {
			self::$prices       = (array) get_option( 'wpcpq_prices', [] );
			self::$settings     = (array) get_option( 'wpcpq_settings', [] );
			self::$localization = (array) get_option( 'wpcpq_localization', [] );
		}

		public static function get_prices() {
			return apply_filters( 'wpcpq_get_prices', self::$prices );
		}

		public static function get_settings() {
			return apply_filters( 'wpcpq_get_settings', self::$settings );
		}

		public static function get_setting( $name, $default = false ) {
			if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
				$setting = self::$settings[ $name ];
			} else {
				$setting = get_option( 'wpcpq_' . $name, $default );
			}

			return apply_filters( 'wpcpq_get_setting', $setting, $name, $default );
		}

		public static function localization( $key = '', $default = '' ) {
			$str = '';

			if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
				$str = self::$localization[ $key ];
			} elseif ( ! empty( $default ) ) {
				$str = $default;
			}

			return apply_filters( 'wpcpq_localization_' . $key, $str );
		}

		public static function sanitize_array( $arr ) {
			foreach ( (array) $arr as $k => $v ) {
				if ( is_array( $v ) ) {
					$arr[ $k ] = self::sanitize_array( $v );
				} else {
					$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'display' );
				}
			}

			return $arr;
		}

		public static function generate_key() {
			$key         = '';
			$key_str     = apply_filters( 'wpcpq_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
			$key_str_len = strlen( $key_str );

			for ( $i = 0; $i < apply_filters( 'wpcpq_key_length', 4 ); $i ++ ) {
				$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
			}

			if ( is_numeric( $key ) ) {
				$key = self::generate_key();
			}

			return apply_filters( 'wpcpq_generate_key', $key );
		}

		public static function sort_tiers( $tiers ) {
			$price = [];

			if ( ! empty( $tiers ) && is_array( $tiers ) ) {
				foreach ( $tiers as $key => $row ) {
					$row['quantity'] = floatval( $row['quantity'] );

					if ( $row['quantity'] == 0 ) {
						unset( $tiers[ $key ] );
						continue;
					}

					$price[ $key ] = $row['quantity'];
				}

				array_multisort( $price, SORT_ASC, $tiers );
			}

			return apply_filters( 'wpcpq_sort_tiers', $tiers );
		}

		public static function get_current_roles() {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();

				return $user->roles;
			} else {
				return [ 'all' ];
			}
		}

		public static function get_pricing( $product_id ) {
			$product   = wc_get_product( $product_id );
			$pricing   = [];
			$prices    = [];
			$enable_v  = 'global';
			$parent_id = 0;

			if ( ! is_a( $product, 'WC_Product' ) ) {
				return [];
			}

			if ( $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				$enable    = $enable_v = get_post_meta( $product_id, 'wpcpq_enable', true ) ?: 'parent';

				if ( $enable_v === 'parent' ) {
					$enable = get_post_meta( $parent_id, 'wpcpq_enable', true ) ?: 'global';
				}
			} else {
				$enable = get_post_meta( $product_id, 'wpcpq_enable', true ) ?: 'global';
			}

			if ( $enable === 'global' ) {
				$pricing['type'] = 'global';
				$prices          = self::get_prices();
			}

			if ( $enable === 'override' ) {
				$pricing['type'] = 'override';
				$prices          = (array) get_post_meta( $product_id, 'wpcpq_prices', true );

				if ( $enable_v === 'parent' ) {
					// is variation
					$prices = (array) get_post_meta( $parent_id, 'wpcpq_prices', true );
				}

				$prices = ! empty( $prices ) ? $prices : [
					'all' => [
						'tiers' => []
					]
				];
			}

			if ( empty( $prices ) ) {
				return [];
			}

			$roles      = self::get_current_roles();
			$price_role = [];

			if ( isset( $prices['all'] ) ) {
				// move old 'all' to the last for old version < 3.0
				$all = $prices['all'];
				unset( $prices['all'] );
				$prices['all'] = $all;
			}

			foreach ( $prices as $key => $price ) {
				$apply     = ! empty( $price['apply'] ) ? $price['apply'] : 'all';
				$apply_val = ! empty( $price['apply_val'] ) ? explode( ',', $price['apply_val'] ) : [];

				if ( $apply !== 'all' ) {
					if ( $product->is_type( 'variation' ) ) {
						// all attributes
						$all_attrs = [];
						$attrs     = $product->get_attributes();

						if ( $taxonomies = get_object_taxonomies( 'product', 'objects' ) ) {
							foreach ( $taxonomies as $taxonomy ) {
								if ( str_starts_with( $taxonomy->name, 'pa_' ) ) {
									$all_attrs[] = $taxonomy->name;
								}
							}
						}

						if ( in_array( $apply, $all_attrs ) ) {
							if ( empty( $attrs[ $apply ] ) || ! in_array( $attrs[ $apply ], $apply_val ) ) {
								// doesn't apply for current product
								continue;
							}
						} else {
							if ( ! has_term( $apply_val, $apply, $product_id ) && ! has_term( $apply_val, $apply, $parent_id ) ) {
								// doesn't apply for current product
								continue;
							}
						}
					} else {
						if ( ! has_term( $apply_val, $apply, $product_id ) ) {
							// doesn't apply for current product
							continue;
						}
					}
				}

				if ( ! empty( $price['role'] ) ) {
					$role = $price['role'];
				} else {
					if ( ! str_contains( $key, '**' ) ) {
						$role = $key;
					} else {
						$key_arr = explode( '**', $key );
						$role    = ! empty( $key_arr[1] ) ? $key_arr[1] : 'all';
					}
				}

				if ( ( in_array( $role, $roles ) || $role === 'all' ) && ! empty( $price ) ) {
					$pricing['role'] = $role;
					$price_role      = $price;
					break;
				}
			}

			if ( ! empty( $price_role ) ) {
				$pricing['role']      = ! empty( $price_role['role'] ) ? $price_role['role'] : 'all';
				$pricing['layout']    = ! empty( $price_role['layout'] ) ? $price_role['layout'] : 'default';
				$pricing['method']    = ! empty( $price_role['method'] ) ? $price_role['method'] : 'volume';
				$pricing['apply']     = ! empty( $price_role['apply'] ) ? $price_role['apply'] : 'all';
				$pricing['apply_val'] = ! empty( $price_role['apply_val'] ) ? $price_role['apply_val'] : '';
				$pricing['tiers']     = ! empty( $price_role['tiers'] ) && is_array( $price_role['tiers'] ) ? self::sort_tiers( $price_role['tiers'] ) : [];
			} else {
				$pricing = array_merge( [
					'role'      => 'all',
					'layout'    => 'default',
					'method'    => 'volume',
					'apply'     => 'all',
					'apply_val' => '',
					'tiers'     => []
				], $pricing );
			}

			return apply_filters( 'wpcpq_get_pricing', $pricing, $product_id );
		}

		public static function format_prices( $prices ) {
			if ( ! is_array( $prices ) || empty( $prices ) ) {
				$prices = [
					'all' => [
						'tiers' => [
							[
								'quantity' => '',
								'price'    => '',
							]
						]
					]
				];
			}

			foreach ( $prices as $key => $price ) {
				if ( empty( $price ) || ! is_array( $price ) ) {
					$price = [];
				}

				if ( empty( $price['tiers'] ) || ! is_array( $price['tiers'] ) ) {
					$price['tiers'] = [];
				}

				if ( ! is_array( $prices[ $key ] ) ) {
					$prices[ $key ] = [];
				}

				$prices[ $key ]['tiers'] = self::sort_tiers( $price['tiers'] );
			}

			return $prices;
		}

		public static function format_price( $price, $current_price ) {
			if ( ( $price === '' ) || ( $price === '100%' ) ) {
				$price = $current_price;
			} else {
				preg_match( '/[\d+\.{0,1}%{0,1}]+/', $price, $matches, PREG_OFFSET_CAPTURE, 0 );

				if ( count( $matches ) > 0 ) {
					$price = $matches[0][0];
				}

				if ( preg_match( '/%$/', $price ) ) {
					$price = $current_price * floatval( preg_replace( '/%$/', '', $price ) ) / 100;
				}
			}

			return apply_filters( 'wpcpq_format_price', (float) $price );
		}

		public static function format_data_price( $price ) {
			return apply_filters( 'wpcpq_format_data_price', trim( $price ) );
		}

		public static function format_quantity( $quantity ) {
			$arr = explode( '.', $quantity );

			if ( count( $arr ) >= 2 ) {
				$quantity = floatval( $arr[0] . '.' . ( floatval( $arr[1] ) - 1 ) );
			} else {
				$quantity = floatval( $quantity ) - 1;
			}

			return apply_filters( 'wpcpq_format_quantity', $quantity );
		}

		public static function get_discount( $old_price, $new_price, $type = 'percentage' ) {
			$discount = 0;

			if ( $old_price && ( $new_price < $old_price ) ) {
				if ( $type === 'percentage' ) {
					$discount = apply_filters( 'wpcpq_discount_percentage', 100 - round( ( $new_price / $old_price ) * 100 ), $old_price, $new_price );
				} elseif ( $type === 'amount' ) {
					$discount = apply_filters( 'wpcpq_discount_amount', $old_price - $new_price, $old_price, $new_price );
				}
			}

			return apply_filters( 'wpcpq_discount', $discount, $old_price, $new_price, $type );
		}

		public static function get_price( $method, $tiers, $quantity, $old_price ) {
			$quantity = (float) $quantity;
			$step     = 1;

			if ( $method === 'tiered' ) {
				$total = 0;

				foreach ( $tiers as $key => $tier ) {
					$tier = array_merge( [ 'quantity' => 0, 'price' => '100%', 'text' => '' ], $tier );

					if ( empty( $tier['price'] ) ) {
						$tier['price']          = '100%';
						$tiers[ $key ]['price'] = '100%';
					}

					$tier_qty   = (float) $tier['quantity'];
					$prev_price = isset( $tiers[ $key - 1 ] ) ? $tiers[ $key - 1 ]['price'] : $old_price;
					$prev_qty   = isset( $tiers[ $key - 1 ] ) ? (float) $tiers[ $key - 1 ]['quantity'] : 1;

					if ( $quantity >= $tier_qty ) {
						$total += ( $tier_qty - $prev_qty ) * self::format_price( $prev_price, $old_price );

						if ( ! isset( $tiers[ $key + 1 ] ) ) {
							$total += ( $quantity - $tier_qty + $step ) * self::format_price( $tier['price'], $old_price );
							break;
						}
					} else {
						$total += ( $quantity - $prev_qty + $step ) * self::format_price( $prev_price, $old_price );
						break;
					}
				}

				return $total / $quantity;
			} else {
				$tiers = array_reverse( $tiers );

				foreach ( $tiers as $tier ) {
					$tier = array_merge( [ 'quantity' => 0, 'price' => '100%', 'text' => '' ], $tier );

					if ( empty( $tier['price'] ) ) {
						$tier['price'] = '100%';
					}

					if ( $quantity >= (float) $tier['quantity'] ) {
						return self::format_price( $tier['price'], $old_price );
					}
				}
			}

			return $old_price;
		}

		public static function is_enable( $product_id ) {
			$is_enable = true;

			if ( ! apply_filters( 'wpcpq_enable_product_id', true, $product_id ) ) {
				$is_enable = false;
			}

			$enable = get_post_meta( $product_id, 'wpcpq_enable', true ) ?: 'global';

			if ( $enable === 'disable' ) {
				$is_enable = false;
			} else {
				$pricing = self::get_pricing( $product_id );

				if ( empty( $pricing['tiers'] ) ) {
					$is_enable = false;
				}
			}

			return apply_filters( 'wpcpq_is_enable', $is_enable, $product_id );
		}

		public static function data_attributes( $attrs ) {
			$attrs_arr = [];

			foreach ( $attrs as $key => $attr ) {
				$attrs_arr[] = esc_attr( 'data-' . sanitize_title( $key ) ) . '="' . esc_attr( $attr ) . '"';
			}

			return implode( ' ', $attrs_arr );
		}
	}

	function Wpcpq_Helper() {
		return Wpcpq_Helper::instance();
	}

	Wpcpq_Helper();
}