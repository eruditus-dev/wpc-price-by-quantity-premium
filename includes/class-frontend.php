<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcpq_Frontend' ) ) {
	class Wpcpq_Frontend {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Product price class
			add_filter( 'woocommerce_product_price_class', [ $this, 'product_price_class' ] );

			// Shortcode
			add_shortcode( 'wpcpq', [ $this, 'shortcode' ] );

			add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_to_cart_button' ] );

			switch ( Wpcpq_Helper()::get_setting( 'position', 'above' ) ) {
				case 'above':
					add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'display_table' ] );
					break;
				case 'under':
					add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'display_table' ] );
					break;
				case 'under_title':
					add_action( 'woocommerce_single_product_summary', [ $this, 'display_table' ], 6 );
					break;
				case 'under_price':
					add_action( 'woocommerce_single_product_summary', [ $this, 'display_table' ], 11 );
					break;
				case 'under_excerpt':
					add_action( 'woocommerce_single_product_summary', [ $this, 'display_table' ], 21 );
					break;
				case 'under_meta':
					add_action( 'woocommerce_single_product_summary', [ $this, 'display_table' ], 41 );
					break;
				case 'under_summary':
					add_action( 'woocommerce_after_single_product_summary', [ $this, 'display_table' ], 9 );
					break;
			}

			// Variation
			add_filter( 'woocommerce_available_variation', [ $this, 'available_variation' ], 99, 3 );
			add_action( 'woocommerce_before_variations_form', [ $this, 'before_variations_form' ] );

			// WPC Smart Messages
			add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );
		}

		public function product_price_class( $class ) {
			global $product;

			if ( $product ) {
				$class .= ' wpcpq-price-' . $product->get_id();
			}

			return $class;
		}

		public function enqueue_scripts() {
			$color_default   = apply_filters( 'wpcpq_active_color_default', '#ffffff' );
			$bgcolor_default = apply_filters( 'wpcpq_active_bgcolor_default', '#cc99c2' );
			$color           = Wpcpq_Helper()::get_setting( 'active_color', $color_default );
			$bgcolor         = Wpcpq_Helper()::get_setting( 'active_bgcolor', $bgcolor_default );
			$inline_css      = '.wpcpq-table .wpcpq-item-active {color: ' . $color . '; background-color: ' . $bgcolor . '}';

			// frontend css
			wp_enqueue_style( 'wpcpq-frontend', WPCPQ_URI . 'assets/css/frontend.css' );
			wp_add_inline_style( 'wpcpq-frontend', $inline_css );

			// frontend js
			wp_enqueue_script( 'wpcpq-frontend', WPCPQ_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCPQ_VERSION, true );
			wp_localize_script( 'wpcpq-frontend', 'wpcpq_vars', [
				'main_price'               => Wpcpq_Helper()::get_setting( 'main_price', 'no' ),
				'price_decimals'           => wc_get_price_decimals(),
				'currency_symbol'          => get_woocommerce_currency_symbol(),
				'price_format'             => get_woocommerce_price_format(),
				'price_decimal_separator'  => wc_get_price_decimal_separator(),
				'price_thousand_separator' => wc_get_price_thousand_separator(),
				'trim_zeros'               => apply_filters( 'woocommerce_price_trim_zeros', false ),
			] );
		}

		function available_variation( $available, $variable, $variation ) {
			$enable = get_post_meta( $variation->get_id(), 'wpcpq_enable', true ) ?: 'parent';

			if ( $enable === 'parent' ) {
				$enable = get_post_meta( $variation->get_parent_id(), 'wpcpq_enable', true ) ?: 'global';
			}

			$available['wpcpq_enable'] = $enable;

			if ( $enable === 'override' || $enable === 'global' ) {
				$available['wpcpq_table'] = htmlentities( self::get_table( $variation ) );
			}

			return $available;
		}

		function before_variations_form() {
			global $product;

			echo '<span class="wpcpq-variable wpcpq-variable-' . esc_attr( $product->get_id() ) . '" data-wpcpq="' . esc_attr( htmlentities( self::get_table( $product ) ) ) . '" style="display: none"></span>';
		}

		public function get_table( $product = null ) {
			$product_id = 0;

			if ( is_numeric( $product ) ) {
				$product_id = $product;
				$product    = wc_get_product( $product_id );
			} elseif ( is_a( $product, 'WC_Product' ) ) {
				$product_id = $product->get_id();
			}

			if ( ! $product_id ) {
				return '';
			}

			if ( $product->is_type( 'variable' ) ) {
				$product_price = $product->get_variation_price( 'min', true );
			} else {
				$product_price = wc_get_price_to_display( $product );
			}

			if ( is_a( $product, 'WC_Product_Variation' ) ) {
				$wrap_id = $product->get_parent_id();
			} else {
				$wrap_id = $product_id;
			}

			ob_start();
			$wrap_attrs = apply_filters( 'wpcpq_wrap_data_attributes', [ 'id' => $wrap_id ], $product );
			echo '<div class="' . esc_attr( 'wpcpq-wrap wpcpq-wrap-' . $wrap_id ) . '" ' . Wpcpq_Helper()::data_attributes( $wrap_attrs ) . '>';
			// always render the wrapper to use it for variable product

			$roles   = Wpcpq_Helper()::get_current_roles();
			$pricing = Wpcpq_Helper()::get_pricing( $product_id );
			$method  = $pricing['method'] ?? 'volume';
			$layout  = $pricing['layout'] ?? 'default';
			$type    = $pricing['type'] ?? '';
			$role    = $pricing['role'] ?? 'all';
			$tiers   = $pricing['tiers'] ?? [];

			if ( is_array( $tiers ) && ! empty( $tiers ) ) {
				do_action( 'wpcpq_table_above', $product );

				$table_class = 'wpcpq-table wpcpq-table-' . $product_id . ' wpcpq-items shop-table wpcpq-table-' . $method . ' wpcpq-' . $method . ' wpcpq-layout-' . $layout . ' wpcpq-click-' . Wpcpq_Helper()::get_setting( 'click_to_set', 'yes' ) . ' wpcpq-hide-first-row-' . Wpcpq_Helper()::get_setting( 'hide_first_row', 'no' );
				$table_attrs = apply_filters( 'wpcpq_table_data_attributes', [
					'id'      => $product_id,
					'roles'   => implode( ',', $roles ),
					'type'    => $type,
					'role'    => $role,
					'method'  => $method,
					'step'    => apply_filters( 'wpcpq_product_step', '1', $product ),
					'price'   => $product_price,
					'o_price' => $product_price
				], $product );
				echo '<div class="' . esc_attr( apply_filters( 'wpcpq_table_class', $table_class ) ) . '" ' . Wpcpq_Helper()::data_attributes( $table_attrs ) . '>';

				do_action( 'wpcpq_table_before', $product );

				if ( $layout === 'compact' ) {
					?>
                    <div class="wpcpq-row wpcpq-item wpcpq-item-0 wpcpq-item-min wpcpq-item-default" data-price="<?php echo esc_attr( Wpcpq_Helper()::format_data_price( $product_price ) ); ?>" data-qty="<?php echo esc_attr( $tiers[0]['quantity'] ); ?>">
						<?php do_action( 'wpcpq_item_before', 0, $tiers, 'first' ); ?>
                        <div class="wpcpq-item-qty">
							<?php
							$min = Wpcpq_Helper()::localization( 'min' );

							if ( empty( $min ) ) {
								$min = esc_html__( '<{min}', 'wpc-price-by-quantity' );
							}

							$min = str_replace( '{min}', $tiers[0]['quantity'], $min );

							echo wp_kses_post( apply_filters( 'wpcpq_item_qty_default', $min, $tiers, $product ) );
							?>
                        </div>
                        <div class="wpcpq-item-price">
							<?php echo wp_kses_post( apply_filters( 'wpcpq_item_price_default', '<span class="wpcpq-item-price-val">' . wc_price( wc_get_price_to_display( $product, [ 'price' => $product_price, ] ) ) . '</span>', $tiers, $product ) ); ?>
                        </div>
						<?php
						if ( $method === 'tiered' ) {
							echo '<div class="wpcpq-item-total"> &nbsp; </div>';
						}

						do_action( 'wpcpq_item_after', 0, $tiers, 'first' ); ?>
                    </div>
					<?php foreach ( $tiers as $key => $tier ) {
						$tier = array_merge( [ 'quantity' => 0, 'price' => '100%', 'text' => '' ], $tier );

						if ( empty( $tier['price'] ) ) {
							$tier['price'] = '100%';
						}

						$item_class   = 'wpcpq-row wpcpq-item wpcpq-item-' . ( $key + 1 );
						$item_context = 'item';

						if ( ( $key + 1 ) === count( $tiers ) ) {
							$item_class   .= ' wpcpq-item-max';
							$item_context = 'last';
						}
						?>
                        <div class="<?php echo esc_attr( apply_filters( 'wpcpq_item_class', $item_class ) ); ?>" data-price="<?php echo esc_attr( Wpcpq_Helper()::format_data_price( $tier['price'] ) ); ?>" data-qty="<?php echo esc_attr( $tier['quantity'] ); ?>" data-prev-qty="<?php echo esc_attr( isset( $tiers[ $key - 1 ] ) ? $tiers[ $key - 1 ]['quantity'] : '0' ); ?>" data-next-qty="<?php echo esc_attr( isset( $tiers[ $key + 1 ] ) ? $tiers[ $key + 1 ]['quantity'] : '-1' ); ?>">
							<?php do_action( 'wpcpq_item_before', $key, $tiers, $item_context ); ?>
                            <div class="wpcpq-item-qty">
								<?php
								if ( ( $key + 1 ) === count( $tiers ) ) {
									// max
									$qty_text = Wpcpq_Helper()::localization( 'max' );

									if ( empty( $qty_text ) ) {
										$qty_text = esc_html__( '{max}+', 'wpc-price-by-quantity' );
									}

									$qty_text = str_replace( '{max}', $tier['quantity'], $qty_text );
								} else {
									$from = floatval( $tier['quantity'] );
									$to   = Wpcpq_Helper()::format_quantity( $tiers[ $key + 1 ]['quantity'] );

									if ( $from < $to ) {
										$qty_text = Wpcpq_Helper()::localization( 'from_to' );

										if ( empty( $qty_text ) ) {
											$qty_text = esc_html__( '{from} - {to}', 'wpc-price-by-quantity' );
										}

										$qty_text = str_replace( '{from}', $from, $qty_text );
										$qty_text = str_replace( '{to}', $to, $qty_text );
									} else {
										$qty_text = Wpcpq_Helper()::localization( 'single' );

										if ( empty( $qty_text ) ) {
											$qty_text = esc_html__( '{single}', 'wpc-price-by-quantity' );
										}

										$qty_text = str_replace( '{single}', $from, $qty_text );
									}
								}

								echo wp_kses_post( apply_filters( 'wpcpq_item_qty', $qty_text, $tier, $product ) );
								?>
                            </div>
							<?php
							// item price
							$tier_price = wc_get_price_to_display( $product, [ 'price' => Wpcpq_Helper()::format_price( $tier['price'], $product_price ), ] );
							echo '<div class="wpcpq-item-price">' . wp_kses_post( apply_filters( 'wpcpq_item_price', '<span class="wpcpq-item-price-val">' . wc_price( $tier_price ) . '</span>', $tier, $product ) );

							// item text
							if ( Wpcpq_Helper()::get_setting( 'after_text', 'yes' ) === 'yes' ) {
								$tier_text = ! empty( $tier['text'] ) ? $tier['text'] : Wpcpq_Helper()::get_setting( 'after_text_default' );
								$tier_text = str_replace( '[p]', Wpcpq_Helper()::get_discount( $product_price, $tier_price ) . '%', $tier_text );
								$tier_text = str_replace( '[a]', wc_price( Wpcpq_Helper()::get_discount( $product_price, $tier_price, 'amount' ) ), $tier_text );

								echo ' <span class="wpcpq-item-text">' . wp_kses_post( apply_filters( 'wpcpq_item_text', $tier_text, $product_price, $tier_price ) ) . '</span>';
							}

							echo '</div>';

							// item total
							if ( $method === 'tiered' ) {
								echo '<div class="wpcpq-item-total"> &nbsp; </div>';
							}

							do_action( 'wpcpq_item_after', $key, $tiers, $item_context );
							?>
                        </div>
					<?php }
					if ( apply_filters( 'wpcpq_layout_compact_summary', false ) ) {
						?>
                        <div class="wpcpq-row wpcpq-summary">
                            <div class="wpcpq-summary-info"><span class="wpcpq-summary-qty"></span> &times;
                                <span class="wpcpq-summary-name"><?php echo esc_html( $product->get_name() ); ?></span>
                            </div>
                            <div class="wpcpq-summary-total"></div>
                        </div>
						<?php
					}
				} elseif ( $layout === 'quick_buy' ) {
					?>
                    <div class="wpcpq-item wpcpq-item-0 wpcpq-item-min wpcpq-item-default" data-price="<?php echo esc_attr( Wpcpq_Helper()::format_data_price( $product_price ) ); ?>" data-qty="<?php echo esc_attr( $tiers[0]['quantity'] ); ?>">
						<?php do_action( 'wpcpq_item_before', 0, $tiers, 'first' ); ?>
                        &nbsp;
						<?php do_action( 'wpcpq_item_after', 0, $tiers, 'first' ); ?>
                    </div>
					<?php foreach ( $tiers as $key => $tier ) {
						$tier = array_merge( [ 'quantity' => 0, 'price' => '100%', 'text' => '' ], $tier );

						if ( empty( $tier['price'] ) ) {
							$tier['price'] = '100%';
						}

						$item_class   = 'wpcpq-item wpcpq-item-' . ( $key + 1 );
						$item_context = 'item';

						if ( ( $key + 1 ) === count( $tiers ) ) {
							$item_class   .= ' wpcpq-item-max';
							$item_context = 'last';
						}
						?>
                        <div class="<?php echo esc_attr( apply_filters( 'wpcpq_item_class', $item_class ) ); ?>" data-price="<?php echo esc_attr( Wpcpq_Helper()::format_data_price( $tier['price'] ) ); ?>" data-qty="<?php echo esc_attr( $tier['quantity'] ); ?>" data-prev-qty="<?php echo esc_attr( isset( $tiers[ $key - 1 ] ) ? $tiers[ $key - 1 ]['quantity'] : '0' ); ?>" data-next-qty="<?php echo esc_attr( isset( $tiers[ $key + 1 ] ) ? $tiers[ $key + 1 ]['quantity'] : '-1' ); ?>">
							<?php do_action( 'wpcpq_item_before', $key, $tiers, $item_context ); ?>
                            <div class="wpcpq-item-info">
                                <div class="wpcpq-item-qty">
									<?php
									$item_qty = 1 === (int) $tier['quantity'] ? Wpcpq_Helper()::localization( 'qb_item_qty_singular', /* translators: qty */ esc_html__( '%s item', 'wpc-price-by-quantity' ) ) : Wpcpq_Helper()::localization( 'qb_item_qty_plural', /* translators: qty */ esc_html__( '%s items', 'wpc-price-by-quantity' ) );
									echo wp_kses_post( apply_filters( 'wpcpq_item_qty', sprintf( $item_qty, number_format_i18n( $tier['quantity'] ) ), $tier, $product ) );
									?>
                                </div>
								<?php
								// item price
								$tier_price = wc_get_price_to_display( $product, [ 'price' => Wpcpq_Helper()::format_price( $tier['price'], $product_price ), ] );
								echo '<div class="wpcpq-item-price">' . wp_kses_post( apply_filters( 'wpcpq_item_price', sprintf( Wpcpq_Helper()::localization( 'qb_item_price', /* translators: price */ esc_html__( '%s for each product', 'wpc-price-by-quantity' ) ), '<span class="wpcpq-item-price-val">' . wc_price( $tier_price ) . '</span>' ), $tier, $product ) ) . '</div>';

								// item text
								if ( Wpcpq_Helper()::get_setting( 'after_text', 'yes' ) === 'yes' ) {
									$tier_text = ! empty( $tier['text'] ) ? $tier['text'] : Wpcpq_Helper()::get_setting( 'after_text_default' );
									$tier_text = str_replace( '[p]', Wpcpq_Helper()::get_discount( $product_price, $tier_price ) . '%', $tier_text );
									$tier_text = str_replace( '[a]', wc_price( Wpcpq_Helper()::get_discount( $product_price, $tier_price, 'amount' ) ), $tier_text );

									echo ' <div class="wpcpq-item-text">' . wp_kses_post( apply_filters( 'wpcpq_item_text', $tier_text, $product_price, $tier_price ) ) . '</div>';
								} ?>
                            </div>
                            <div class="wpcpq-item-atc">
								<?php echo wp_kses_post( apply_filters( 'wpcpq_item_atc', '<button type="button" class="wpcpq-item-atc-btn single_add_to_cart_button button alt">' . Wpcpq_Helper()::localization( 'qb_item_atc', esc_html__( 'Add to cart', 'wpc-price-by-quantity' ) ) . '</button>', $tier, $product ) ); ?>
                            </div>
							<?php do_action( 'wpcpq_item_after', $key, $tiers, $item_context ); ?>
                        </div>
					<?php }
				} else { ?>
                    <div class="wpcpq-row wpcpq-head">
                        <div class="wpcpq-row-qty"><?php echo Wpcpq_Helper()::localization( 'quantity', esc_html__( 'Quantity', 'wpc-price-by-quantity' ) ); ?></div>
                        <div class="wpcpq-row-price"><?php echo Wpcpq_Helper()::localization( 'price', esc_html__( 'Price', 'wpc-price-by-quantity' ) ); ?></div>
						<?php if ( $method === 'tiered' ) {
							echo '<div class="wpcpq-row-total">' . Wpcpq_Helper()::localization( 'row_total', esc_html__( 'Tier Total', 'wpc-price-by-quantity' ) ) . '</div>';
						} ?>
                    </div>
                    <div class="wpcpq-row wpcpq-item wpcpq-item-0 wpcpq-item-min wpcpq-item-default" data-price="<?php echo esc_attr( Wpcpq_Helper()::format_data_price( $product_price ) ); ?>" data-qty="<?php echo esc_attr( $tiers[0]['quantity'] ); ?>">
						<?php do_action( 'wpcpq_item_before', 0, $tiers, 'first' ); ?>
                        <div class="wpcpq-item-qty">
							<?php
							$min = Wpcpq_Helper()::localization( 'min' );

							if ( empty( $min ) ) {
								$min = esc_html__( '<{min}', 'wpc-price-by-quantity' );
							}

							$min = str_replace( '{min}', $tiers[0]['quantity'], $min );

							echo wp_kses_post( apply_filters( 'wpcpq_item_qty_default', $min, $tiers, $product ) );
							?>
                        </div>
                        <div class="wpcpq-item-price">
							<?php echo wp_kses_post( apply_filters( 'wpcpq_item_price_default', '<span class="wpcpq-item-price-val">' . wc_price( wc_get_price_to_display( $product, [ 'price' => $product_price, ] ) ) . '</span>', $tiers, $product ) ); ?>
                        </div>
						<?php
						if ( $method === 'tiered' ) {
							echo '<div class="wpcpq-item-total"> &nbsp; </div>';
						}

						do_action( 'wpcpq_item_after', 0, $tiers, 'first' );
						?>
                    </div>
					<?php foreach ( $tiers as $key => $tier ) {
						$tier = array_merge( [ 'quantity' => 0, 'price' => '100%', 'text' => '' ], $tier );

						if ( empty( $tier['price'] ) ) {
							$tier['price'] = '100%';
						}

						$item_class   = 'wpcpq-row wpcpq-item wpcpq-item-' . ( $key + 1 );
						$item_context = 'item';

						if ( ( $key + 1 ) === count( $tiers ) ) {
							$item_class   .= ' wpcpq-item-max';
							$item_context = 'last';
						}
						?>
                        <div class="<?php echo esc_attr( apply_filters( 'wpcpq_item_class', $item_class ) ); ?>" data-price="<?php echo esc_attr( Wpcpq_Helper()::format_data_price( $tier['price'] ) ); ?>" data-qty="<?php echo esc_attr( $tier['quantity'] ); ?>" data-prev-qty="<?php echo esc_attr( isset( $tiers[ $key - 1 ] ) ? $tiers[ $key - 1 ]['quantity'] : '0' ); ?>" data-next-qty="<?php echo esc_attr( isset( $tiers[ $key + 1 ] ) ? $tiers[ $key + 1 ]['quantity'] : '-1' ); ?>">
							<?php do_action( 'wpcpq_item_before', $key, $tiers, $item_context ); ?>
                            <div class="wpcpq-item-qty">
								<?php
								if ( ( $key + 1 ) === count( $tiers ) ) {
									// max
									$qty_text = Wpcpq_Helper()::localization( 'max' );

									if ( empty( $qty_text ) ) {
										$qty_text = esc_html__( '{max}+', 'wpc-price-by-quantity' );
									}

									$qty_text = str_replace( '{max}', $tier['quantity'], $qty_text );
								} else {
									$from = floatval( $tier['quantity'] );
									$to   = Wpcpq_Helper()::format_quantity( $tiers[ $key + 1 ]['quantity'] );

									if ( $from < $to ) {
										$qty_text = Wpcpq_Helper()::localization( 'from_to' );

										if ( empty( $qty_text ) ) {
											$qty_text = esc_html__( '{from} - {to}', 'wpc-price-by-quantity' );
										}

										$qty_text = str_replace( '{from}', $from, $qty_text );
										$qty_text = str_replace( '{to}', $to, $qty_text );
									} else {
										$qty_text = Wpcpq_Helper()::localization( 'single' );

										if ( empty( $qty_text ) ) {
											$qty_text = esc_html__( '{single}', 'wpc-price-by-quantity' );
										}

										$qty_text = str_replace( '{single}', $from, $qty_text );
									}
								}

								echo wp_kses_post( apply_filters( 'wpcpq_item_qty', $qty_text, $tier, $product ) );
								?>
                            </div>
							<?php
							// item price
							$tier_price = wc_get_price_to_display( $product, [ 'price' => Wpcpq_Helper()::format_price( $tier['price'], $product_price ), ] );
							echo '<div class="wpcpq-item-price">' . wp_kses_post( apply_filters( 'wpcpq_item_price', '<span class="wpcpq-item-price-val">' . wc_price( $tier_price ) . '</span>', $tier, $product ) );

							// item text
							if ( Wpcpq_Helper()::get_setting( 'after_text', 'yes' ) === 'yes' ) {
								$tier_text = ! empty( $tier['text'] ) ? $tier['text'] : Wpcpq_Helper()::get_setting( 'after_text_default' );
								$tier_text = str_replace( '[p]', Wpcpq_Helper()::get_discount( $product_price, $tier_price ) . '%', $tier_text );
								$tier_text = str_replace( '[a]', wc_price( Wpcpq_Helper()::get_discount( $product_price, $tier_price, 'amount' ) ), $tier_text );

								echo ' <span class="wpcpq-item-text">' . wp_kses_post( apply_filters( 'wpcpq_item_text', $tier_text, $product_price, $tier_price ) ) . '</span>';
							}

							echo '</div>';

							// item total
							if ( $method === 'tiered' ) {
								echo '<div class="wpcpq-item-total"> &nbsp; </div>';
							}

							do_action( 'wpcpq_item_after', $key, $tiers, $item_context );
							?>
                        </div>
					<?php } ?>
                    <div class="wpcpq-row wpcpq-foot wpcpq-summary">
                        <div class="wpcpq-summary-info"><span class="wpcpq-summary-qty"></span> &times;
                            <span class="wpcpq-summary-name"><?php echo esc_html( $product->get_name() ); ?></span>
                        </div>
                        <div class="wpcpq-summary-total"></div>
                    </div>
				<?php }

				do_action( 'wpcpq_table_after', $product );

				echo '</div><!-- /wpcpq-table -->';

				do_action( 'wpcpq_table_under', $product );
			}

			echo '</div>';

			return apply_filters( 'wpcpq_get_table', ob_get_clean(), $product );
		}

		function shortcode() {
			global $product;

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return null;
			}

			return self::get_table( $product );
		}

		function add_to_cart_button() {
			global $product;

			echo '<span class="' . esc_attr( 'wpcpq-id wpcpq-id-' . $product->get_id() ) . '" data-id="' . esc_attr( $product->get_id() ) . '"></span>';
		}

		public function display_table() {
			global $product;

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			echo self::get_table( $product );
		}

		function wpcsm_locations( $locations ) {
			$locations['WPC Price by Quantity'] = [
				'wpcpq_table_above' => esc_html__( 'Before price table', 'wpc-price-by-quantity' ),
				'wpcpq_table_under' => esc_html__( 'After price table', 'wpc-price-by-quantity' ),
			];

			return $locations;
		}
	}

	function Wpcpq_Frontend() {
		return Wpcpq_Frontend::instance();
	}

	Wpcpq_Frontend();
}