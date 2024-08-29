<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Wpcpq_Backend' ) ) {
	class Wpcpq_Backend {
		protected static $instance = null;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Settings
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );

			// Links
			add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
			add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

			// AJAX
			add_action( 'wp_ajax_wpcpq_add_role_price', [ $this, 'ajax_add_role_price' ] );

			// Search Term
			add_action( 'wp_ajax_wpcpq_search_term', [ $this, 'ajax_search_term' ] );

			// Single Product
			add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
			add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
			add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

			// Variation
			add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'variation_settings' ], 99, 3 );
			add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings' ], 99, 2 );

			// WPC Variation Duplicator
			add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

			// WPC Variation Bulk Editor
			add_action( 'wpcvb_bulk_update_variation', [ $this, 'bulk_update_variation' ], 99, 2 );

			// Product columns
			add_filter( 'manage_edit-product_columns', [ $this, 'product_columns' ] );
			add_action( 'manage_product_posts_custom_column', [ $this, 'custom_column' ], 10, 2 );

			// Overview
			add_action( 'wp_ajax_wpcpq_overview', [ $this, 'ajax_overview' ] );
		}

		public function product_data_tabs( $tabs ) {
			$tabs['wpcpq'] = [
				'label'  => esc_html__( 'Price by Quantity', 'wpc-price-by-quantity' ),
				'target' => 'wpcpq_settings'
			];

			return $tabs;
		}

		public function product_data_panels() {
			global $post, $thepostid, $product_object;

			if ( $product_object instanceof WC_Product ) {
				$product_id = $product_object->get_id();
			} elseif ( is_numeric( $thepostid ) ) {
				$product_id = $thepostid;
			} elseif ( $post instanceof WP_Post ) {
				$product_id = $post->ID;
			} else {
				$product_id = 0;
			}

			if ( ! $product_id ) {
				?>
                <div id='wpcpq_settings' class='panel woocommerce_options_panel wpcpq_settings'>
                    <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-price-by-quantity' ); ?></p>
                </div>
				<?php
				return;
			}

			self::product_settings( $product_id );
		}

		function variation_settings( $loop, $variation_data, $variation ) {
			$variation_id = absint( $variation->ID );
			?>
            <div class="form-row form-row-full wpcpq-variation-settings">
                <label><?php esc_html_e( 'WPC Price by Quantity', 'wpc-price-by-quantity' ); ?></label>
                <div class="wpcpq-variation-wrap wpcpq-variation-wrap-<?php echo esc_attr( $variation_id ); ?>">
					<?php self::product_settings( $variation_id, true ); ?>
                </div>
            </div>
			<?php
		}

		function product_settings( $product_id, $is_variation = false ) {
			$enable = get_post_meta( $product_id, 'wpcpq_enable', true ) ?: ( $is_variation ? 'parent' : 'global' );
			$prices = (array) get_post_meta( $product_id, 'wpcpq_prices', true );
			$prices = ! empty( $prices ) ? Wpcpq_Helper()::format_prices( $prices ) : [
				'all' => [
					'tiers' => [
						[
							'quantity' => '',
							'price'    => '',
						]
					]
				]
			];

			$name  = '';
			$id    = 'wpcpq_settings';
			$class = 'panel woocommerce_options_panel wpcpq_settings';

			if ( $is_variation ) {
				$name  = '_v[' . $product_id . ']';
				$id    = '';
				$class = 'wpcpq_settings';
			}
			?>
            <div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>">
				<?php do_action( 'wpcpq_product_settings_before', $product_id, $is_variation ); ?>
                <div class="wpcpq_settings_enable">
                    <div class="wpcpq_settings_enable_inner">
                        <span><?php esc_html_e( 'Price by Quantity', 'wpc-price-by-quantity' ); ?></span> <label>
                            <select name="<?php echo esc_attr( 'wpcpq_enable' . $name ); ?>" class="wpcpq_enable">
                                <option value="global" <?php selected( $enable, 'global' ); ?>><?php esc_html_e( 'Global', 'wpc-price-by-quantity' ); ?></option>
                                <option value="disable" <?php selected( $enable, 'disable' ); ?>><?php esc_html_e( 'Disable', 'wpc-price-by-quantity' ); ?></option>
								<?php if ( $is_variation ) { ?>
                                    <option value="parent" <?php selected( $enable, 'parent' ); ?>><?php esc_html_e( 'Parent', 'wpc-price-by-quantity' ); ?></option>
								<?php } ?>
                                <option value="override" <?php selected( $enable, 'override' ); ?>><?php esc_html_e( 'Override', 'wpc-price-by-quantity' ); ?></option>
                            </select> </label>
                    </div>
                    <!--
					<div style="color: #c9356e; padding-left: 12px; padding-right: 12px;">
						Settings at a product/variation basis only available on the Premium Version.
						<a href="https://wpclever.net/downloads/wpc-price-by-quantity/?utm_source=pro&utm_medium=wpcpq&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!
					</div>
					-->
                </div>
                <div class="wpcpq_settings_override" style="display: <?php echo esc_attr( $enable === 'override' ? 'block' : 'none' ); ?>;">
					<?php do_action( 'wpcpq_product_settings_override_before', $product_id, $is_variation ); ?>
                    <div class="wpcpq-items-wrapper">
                        <div class="wpcpq-items wpcpq-roles">
							<?php
							$active = true;

							if ( is_array( $prices ) && ! empty( $prices ) ) {
								if ( isset( $prices['all'] ) ) {
									// move old 'all' to the last for old version < 3.0
									$all = $prices['all'];
									unset( $prices['all'] );
									$prices['all'] = $all;
								}

								foreach ( $prices as $key => $price ) {
									include WPCPQ_DIR . 'includes/templates/role-price.php';
									$active = false;
								}
							}
							?>
                        </div>
                    </div>
					<?php include WPCPQ_DIR . 'includes/templates/add-new.php'; ?>
					<?php do_action( 'wpcpq_product_settings_override_after', $product_id, $is_variation ); ?>
                </div>
				<?php do_action( 'wpcpq_product_settings_after', $product_id, $is_variation ); ?>
            </div>
			<?php
		}

		public function process_product_meta( $post_id ) {
			if ( isset( $_POST['wpcpq_enable'] ) ) {
				update_post_meta( $post_id, 'wpcpq_enable', sanitize_text_field( $_POST['wpcpq_enable'] ) );
			}

			if ( isset( $_POST['wpcpq_prices'] ) ) {
				update_post_meta( $post_id, 'wpcpq_prices', Wpcpq_Helper()::sanitize_array( $_POST['wpcpq_prices'] ) );
			}
		}

		function save_variation_settings( $post_id ) {
			if ( isset( $_POST['wpcpq_enable_v'][ $post_id ] ) ) {
				update_post_meta( $post_id, 'wpcpq_enable', sanitize_text_field( $_POST['wpcpq_enable_v'][ $post_id ] ) );
			}

			if ( isset( $_POST['wpcpq_prices_v'][ $post_id ] ) ) {
				update_post_meta( $post_id, 'wpcpq_prices', Wpcpq_Helper()::sanitize_array( $_POST['wpcpq_prices_v'][ $post_id ] ) );
			}
		}

		function duplicate_variation( $old_variation_id, $new_variation_id ) {
			if ( $enable = get_post_meta( $old_variation_id, 'wpcpq_enable', true ) ) {
				update_post_meta( $new_variation_id, 'wpcpq_enable', $enable );
			}

			if ( $rules = get_post_meta( $old_variation_id, 'wpcpq_prices', true ) ) {
				update_post_meta( $new_variation_id, 'wpcpq_prices', $rules );
			}
		}

		function bulk_update_variation( $variation_id, $fields ) {
			if ( ! empty( $fields['wpcpq_enable_v'] ) && ( $fields['wpcpq_enable_v'] !== 'wpcvb_no_change' ) ) {
				update_post_meta( $variation_id, 'wpcpq_enable', sanitize_text_field( $fields['wpcpq_enable_v'] ) );
			}

			if ( ! empty( $fields['wpcpq_enable_v'] ) && ( $fields['wpcpq_enable_v'] === 'override' ) && ! empty( $fields['wpcpq_prices_v'] ) ) {
				update_post_meta( $variation_id, 'wpcpq_prices', Wpcpq_Helper()::sanitize_array( $fields['wpcpq_prices_v'] ) );
			}
		}

		function register_settings() {
			// settings
			register_setting( 'wpcpq_settings', 'wpcpq_settings' );
			register_setting( 'wpcpq_settings', 'wpcpq_prices' );

			// localization
			register_setting( 'wpcpq_localization', 'wpcpq_localization' );
		}

		public function admin_menu() {
			add_submenu_page( 'wpclever', 'WPC Price by Quantity', 'Price by Quantity', 'manage_options', 'wpclever-wpcpq', [
				$this,
				'admin_menu_content'
			] );
		}

		public function admin_menu_content() {
			$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
			?>
            <div class="wpclever_settings_page wrap">
                <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Price by Quantity', 'wpc-price-by-quantity' ) . ' ' . esc_html( WPCPQ_VERSION ) . ' ' . ( defined( 'WPCPQ_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-price-by-quantity' ) . '</span>' : '' ); ?></h1>
                <div class="wpclever_settings_page_desc about-text">
                    <p>
						<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-price-by-quantity' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                        <br/>
                        <a href="<?php echo esc_url( WPCPQ_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-price-by-quantity' ); ?></a> |
                        <a href="<?php echo esc_url( WPCPQ_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-price-by-quantity' ); ?></a> |
                        <a href="<?php echo esc_url( WPCPQ_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-price-by-quantity' ); ?></a>
                    </p>
                </div>
				<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'wpc-price-by-quantity' ); ?></p>
                    </div>
				<?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpq&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
							<?php esc_html_e( 'Settings', 'wpc-price-by-quantity' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpq&tab=localization' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
							<?php esc_html_e( 'Localization', 'wpc-price-by-quantity' ); ?>
                        </a>
                        <!--
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpq&tab=premium' ) ); ?>"
                       class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
                        <?php esc_html_e( 'Premium Version', 'wpc-price-by-quantity' ); ?>
                    </a>
                    -->
                        <a href="<?php echo esc_url( WPCPQ_SUPPORT ); ?>" class="nav-tab" target="_blank">
							<?php esc_html_e( 'Support', 'wpc-price-by-quantity' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
							<?php esc_html_e( 'Essential Kit', 'wpc-price-by-quantity' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
					<?php if ( $active_tab === 'settings' ) {
						$position        = Wpcpq_Helper()::get_setting( 'position', 'above' );
						$main_price      = Wpcpq_Helper()::get_setting( 'main_price', 'no' );
						$after_text      = Wpcpq_Helper()::get_setting( 'after_text', 'yes' );
						$click_to_set    = Wpcpq_Helper()::get_setting( 'click_to_set', 'yes' );
						$hide_first_row  = Wpcpq_Helper()::get_setting( 'hide_first_row', 'no' );
						$color_default   = apply_filters( 'wpcpq_active_color_default', '#ffffff' );
						$bgcolor_default = apply_filters( 'wpcpq_active_bgcolor_default', '#cc99c2' );
						?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e( 'Position', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label> <select name="wpcpq_settings[position]">
                                                <option value="above" <?php selected( $position, 'above' ); ?>><?php esc_html_e( 'Above the add to cart button', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="under" <?php selected( $position, 'under' ); ?>><?php esc_html_e( 'Under the add to cart button', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="under_title" <?php selected( $position, 'under_title' ); ?>><?php esc_html_e( 'Under the title', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="under_price" <?php selected( $position, 'under_price' ); ?>><?php esc_html_e( 'Under the price', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="under_excerpt" <?php selected( $position, 'under_excerpt' ); ?>><?php esc_html_e( 'Under the excerpt', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="under_meta" <?php selected( $position, 'under_meta' ); ?>><?php esc_html_e( 'Under the meta', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="under_summary" <?php selected( $position, 'under_summary' ); ?>><?php esc_html_e( 'Under the summary', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="no" <?php selected( $position, 'no' ); ?>><?php esc_html_e( 'No (hide it)', 'wpc-price-by-quantity' ); ?></option>
                                            </select> </label>
                                        <p class="description"><?php printf( /* translators: shortcode */ esc_html__( 'Choose where to display the pricing table on a single product page; use %s shortcode to place it anywhere.', 'wpc-price-by-quantity' ), '<code>[wpcpq]</code>' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Change price', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label> <select name="wpcpq_settings[main_price]">
                                                <option value="price" <?php selected( $main_price, 'price' ); ?>><?php esc_attr_e( 'Replace by active price', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="total" <?php selected( $main_price, 'total' ); ?>><?php esc_attr_e( 'Replace by total', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="no" <?php selected( $main_price, 'no' ); ?>><?php esc_attr_e( 'No', 'wpc-price-by-quantity' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Change the main price when updating the quantity.', 'wpc-price-by-quantity' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Click to set', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label> <select name="wpcpq_settings[click_to_set]">
                                                <option value="yes" <?php selected( $click_to_set, 'yes' ); ?>><?php esc_attr_e( 'Yes', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="no" <?php selected( $click_to_set, 'no' ); ?>><?php esc_attr_e( 'No', 'wpc-price-by-quantity' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Change the main quantity when clicking on a pricing row.', 'wpc-price-by-quantity' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Active color', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_settings[active_color]" class="wpcpq_color_picker" value="<?php echo esc_attr( Wpcpq_Helper()::get_setting( 'active_color', $color_default ) ); ?>"/>
                                        </label>
                                        <span class="description"><?php printf( /* translators: color */ esc_html__( 'Choose the color for the active price, default %s', 'wpc-price-by-quantity' ), '<code>' . esc_html( $color_default ) . '</code>' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Active background color', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_settings[active_bgcolor]" class="wpcpq_color_picker" value="<?php echo esc_attr( Wpcpq_Helper()::get_setting( 'active_bgcolor', $bgcolor_default ) ); ?>"/>
                                        </label>
                                        <span class="description"><?php printf( /* translators: color */ esc_html__( 'Choose the background color for the active price, default %s', 'wpc-price-by-quantity' ), '<code>' . esc_html( $bgcolor_default ) . '</code>' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Hide first row', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label> <select name="wpcpq_settings[hide_first_row]">
                                                <option value="yes" <?php selected( $hide_first_row, 'yes' ); ?>><?php esc_attr_e( 'Yes', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="no" <?php selected( $hide_first_row, 'no' ); ?>><?php esc_attr_e( 'No', 'wpc-price-by-quantity' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Hide the first row that show the original price.', 'wpc-price-by-quantity' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'After text', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label> <select name="wpcpq_settings[after_text]">
                                                <option value="yes" <?php selected( $after_text, 'yes' ); ?>><?php esc_attr_e( 'Show', 'wpc-price-by-quantity' ); ?></option>
                                                <option value="no" <?php selected( $after_text, 'no' ); ?>><?php esc_attr_e( 'Hide', 'wpc-price-by-quantity' ); ?></option>
                                            </select> </label>
                                        <span class="description"><?php esc_html_e( 'Show/hide the after text on each price row.', 'wpc-price-by-quantity' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Default after text', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_settings[after_text_default]" value="<?php echo esc_attr( Wpcpq_Helper()::get_setting( 'after_text_default' ) ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'You can use [p] for percentage discount, [a] for amount discount. E.g: (saved [p])', 'wpc-price-by-quantity' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Pricing table', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <div class="wpcpq_settings">
                                            <div class="wpcpq-items-wrapper">
                                                <div class="wpcpq-items wpcpq-roles">
													<?php
													$active       = true;
													$name         = '';
													$is_variation = false;
													$product_id   = 0;
													$prices       = Wpcpq_Helper()::get_prices();
													$prices       = ( is_array( $prices ) && ! empty( $prices ) ) ? Wpcpq_Helper()::format_prices( $prices ) : [
														'all' => [
															'tiers' => [
																[
																	'quantity' => '',
																	'price'    => '',
																]
															]
														]
													];

													if ( is_array( $prices ) && ! empty( $prices ) ) {
														if ( isset( $prices['all'] ) ) {
															// move old 'all' to the last for old version < 3.0
															$all = $prices['all'];
															unset( $prices['all'] );
															$prices['all'] = $all;
														}

														foreach ( $prices as $key => $price ) {
															include WPCPQ_DIR . 'includes/templates/role-price.php';
															$active = false;
														}
													}
													?>
                                                </div>
                                            </div>
											<?php include WPCPQ_DIR . 'includes/templates/add-new.php'; ?>
                                        </div><!-- /wpcpq_settings -->
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
										<?php settings_fields( 'wpcpq_settings' ); ?><?php submit_button(); ?>
                                    </th>
                                </tr>
                            </table>
                        </form>
					<?php } elseif ( $active_tab == 'localization' ) { ?>
                        <form method="post" action="options.php">
                            <table class="form-table">
                                <tr class="heading">
                                    <th scope="row"><?php esc_html_e( 'Default Table', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
										<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-price-by-quantity' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Quantity', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[quantity]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'quantity' ) ); ?>" placeholder="<?php esc_attr_e( 'Quantity', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Price', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[price]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'price' ) ); ?>" placeholder="<?php esc_attr_e( 'Price', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Tier Total', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[row_total]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'row_total' ) ); ?>" placeholder="<?php esc_attr_e( 'Tier Total', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Min outliers', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[min]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'min' ) ); ?>" placeholder="<?php esc_attr_e( '<{min}', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'Values smaller than min.', 'wpc-price-by-quantity' ); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Single value', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[single]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'single' ) ); ?>" placeholder="<?php esc_attr_e( '{single}', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Values between min and max', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[from_to]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'from_to' ) ); ?>" placeholder="<?php esc_attr_e( '{from} - {to}', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Max & outliers', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[max]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'max' ) ); ?>" placeholder="<?php esc_attr_e( '{max}+', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'Max quantity and larger values.', 'wpc-price-by-quantity' ); ?></span>
                                    </td>
                                </tr>
                                <tr class="heading">
                                    <th scope="row"><?php esc_html_e( 'Quick Buy Table', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
										<?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-price-by-quantity' ); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Item quantity (singular)', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[qb_item_qty_singular]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'qb_item_qty_singular' ) ); ?>" placeholder="<?php /* translators: qty */
											esc_attr_e( '%s item', 'wpc-price-by-quantity' ); ?>"/> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Item quantity (plural)', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[qb_item_qty_plural]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'qb_item_qty_plural' ) ); ?>" placeholder="<?php /* translators: qty */
											esc_attr_e( '%s items', 'wpc-price-by-quantity' ); ?>"/> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Item price', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[qb_item_price]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'qb_item_price' ) ); ?>" placeholder="<?php /* translators: price */
											esc_attr_e( '%s for each product', 'wpc-price-by-quantity' ); ?>"/> </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Add to cart', 'wpc-price-by-quantity' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="text" name="wpcpq_localization[qb_item_atc]" class="regular-text" value="<?php echo esc_attr( Wpcpq_Helper()::localization( 'qb_item_atc' ) ); ?>" placeholder="<?php esc_attr_e( 'Add to cart', 'wpc-price-by-quantity' ); ?>"/>
                                        </label>
                                    </td>
                                </tr>
                                <tr class="submit">
                                    <th colspan="2">
										<?php settings_fields( 'wpcpq_localization' ); ?><?php submit_button(); ?>
                                    </th>
                                </tr>
                            </table>
                        </form>
					<?php } elseif ( $active_tab == 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/wpc-price-by-quantity?utm_source=pro&utm_medium=wpcpq&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/wpc-price-by-quantity</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Setup pricing on a product/variation basis.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
					<?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}

		function action_links( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WPCPQ_FILE );
			}

			if ( $plugin === $file ) {
				$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpq&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-price-by-quantity' ) . '</a>';
				//$links['wpc-premium']       = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpq&tab=premium' ) ) . '" style="color: #c9356e">' . esc_html__( 'Premium Version', 'wpc-price-by-quantity' ) . '</a>';
				array_unshift( $links, $settings );
			}

			return (array) $links;
		}

		function row_meta( $links, $file ) {
			static $plugin;

			if ( ! isset( $plugin ) ) {
				$plugin = plugin_basename( WPCPQ_FILE );
			}

			if ( $plugin === $file ) {
				$row_meta = [
					'support' => '<a href="' . esc_url( WPCPQ_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-price-by-quantity' ) . '</a>',
				];

				return array_merge( $links, $row_meta );
			}

			return (array) $links;
		}

		public function ajax_add_role_price() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcpq-security' ) ) {
				die( 'Permissions check failed!' );
			}

			$role      = sanitize_text_field( $_POST['role'] ?? 'all' );
			$apply     = sanitize_text_field( $_POST['apply'] ?? 'all' );
			$apply_val = sanitize_text_field( $_POST['apply_val'] ?? '' );
			$method    = sanitize_text_field( $_POST['method'] ?? 'volume' );
			$layout    = sanitize_text_field( $_POST['layout'] ?? 'default' );
			$tiers     = Wpcpq_Helper()::sanitize_array( $_POST['tiers'] ?? [
				[
					'quantity' => '',
					'price'    => '',
					'text'     => '',
				]
			] );

			// variation id > 0
			$product_id   = absint( sanitize_text_field( $_POST['id'] ?? 0 ) );
			$is_variation = $product_id > 0;
			$name         = $is_variation ? '_v[' . $product_id . ']' : '';

			if ( ! empty( $role ) ) {
				$key    = $role;
				$active = true;
				$price  = [
					'apply'     => $apply,
					'apply_val' => $apply_val,
					'method'    => $method,
					'layout'    => $layout,
					'tiers'     => $tiers
				];
				include WPCPQ_DIR . 'includes/templates/role-price.php';
			}

			die;
		}

		function ajax_search_term() {
			if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), 'wpcpq-security' ) ) {
				die( 'Permissions check failed!' );
			}

			$return = [];
			$args   = [
				'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
				'orderby'    => 'id',
				'order'      => 'ASC',
				'hide_empty' => false,
				'fields'     => 'all',
				'name__like' => sanitize_text_field( $_REQUEST['q'] ),
			];

			$terms = get_terms( $args );

			if ( count( $terms ) ) {
				foreach ( $terms as $term ) {
					$return[] = [ $term->slug, $term->name ];
				}
			}

			wp_send_json( $return );
		}

		public function enqueue_scripts() {
			wp_enqueue_style( 'hint', WPCPQ_URI . 'assets/css/hint.css' );
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( 'wpcpq-backend', WPCPQ_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCPQ_VERSION );
			wp_enqueue_script( 'wpcpq-backend', WPCPQ_URI . 'assets/js/backend.js', [
				'jquery',
				'wp-color-picker',
				'jquery-ui-dialog',
				'jquery-ui-sortable',
				'wc-enhanced-select',
				'selectWoo'
			], WPCPQ_VERSION, true );
			wp_localize_script( 'wpcpq-backend', 'wpcpq_vars', [
				'nonce'       => wp_create_nonce( 'wpcpq-security' ),
				'hint_price'  => htmlentities( sprintf( /* translators: price */ esc_html__( 'Set a price using a number (eg. "10") or percentage (e.g. "%1$s" of product price). Use ( %2$s ) as decimal separator.', 'wpc-price-by-quantity' ), '90%', wc_get_price_decimal_separator() ) ),
				'hint_text'   => esc_attr__( 'Leave empty to use the default text.', 'wpc-price-by-quantity' ),
				'hint_remove' => esc_attr__( 'remove', 'wpc-price-by-quantity' ),
			] );
		}

		function product_columns( $columns ) {
			$columns['wpcpq'] = esc_html__( 'Price by Quantity', 'wpc-price-by-quantity' );

			return $columns;
		}

		function custom_column( $column, $postid ) {
			if ( $column === 'wpcpq' ) {
				$enable = get_post_meta( $postid, 'wpcpq_enable', true );

				if ( empty( $enable ) || $enable === 'global' ) {
					echo '<a href="#" class="wpcpq_overview" data-pid="' . esc_attr( $postid ) . '" data-name="' . esc_attr( get_the_title( $postid ) ) . '" data-type="global"><span class="dashicons dashicons-money-alt"></span></a>';
				}

				if ( $enable === 'disable' ) {
					echo '<span class="wpcpq_overview_disable"><span class="dashicons dashicons-money-alt"></span> ' . esc_html__( 'Disable', 'wpc-price-by-quantity' ) . '</span>';
				}

				if ( $enable === 'override' ) {
					echo '<a href="#" class="wpcpq_overview" data-pid="' . esc_attr( $postid ) . '" data-name="' . esc_attr( get_the_title( $postid ) ) . '" data-type="override"><span class="dashicons dashicons-money-alt"></span> ' . esc_html__( 'Override', 'wpc-price-by-quantity' ) . '</a>';
				}
			}
		}

		function ajax_overview() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpcpq-security' ) ) {
				die( 'Permissions check failed!' );
			}

			$pid     = absint( sanitize_text_field( $_POST['pid'] ) );
			$type    = sanitize_text_field( $_POST['type'] );
			$product = wc_get_product( $pid );

			if ( $product ) {
				global $wp_roles;
				$prices = [];

				echo '<div class="wpcpq-overview-content">';
				echo '<div class="wpcpq-overview-line">' . esc_html__( 'Product price:', 'wpc-price-by-quantity' ) . ' ' . $product->get_price_html() . '</div>';

				if ( $type === 'global' ) {
					$prices = Wpcpq_Helper()::get_prices();
				}

				if ( $type === 'override' && $pid ) {
					$prices = get_post_meta( $pid, 'wpcpq_prices', true ) ?: [];
				}

				if ( ! empty( $prices ) ) {
					foreach ( $prices as $price ) {
						$price     = array_merge( [
							'role'      => 'all',
							'apply'     => '',
							'apply_val' => '',
							'method'    => '',
							'layout'    => 'default',
							'tiers'     => [],
						], $price );
						$role      = $price['role'];
						$role_name = isset( $wp_roles->roles[ $role ] ) ? $wp_roles->roles[ $role ]['name'] : esc_html__( 'All users', 'wpc-price-by-quantity' );

						echo '<div class="wpcpq-overview-line">';
						echo esc_html__( 'User role:', 'wpc-price-by-quantity' ) . ' <strong>' . esc_html( $role_name ) . '</strong><br/>';

						if ( $type === 'global' ) {
							if ( $price['apply'] === 'all' ) {
								echo esc_html__( 'Apply for:', 'wpc-price-by-quantity' ) . ' ' . esc_html__( 'all products', 'wpc-price-by-quantity' ) . '<br/>';
							} else {
								echo esc_html__( 'Apply for:', 'wpc-price-by-quantity' ) . ' ' . esc_html( $price['apply'] ) . ' (' . esc_html( $price['apply_val'] ) . ')<br/>';
							}
						}

						echo esc_html__( 'Pricing method:', 'wpc-price-by-quantity' ) . ' <u>' . esc_html( $price['method'] ) . '</u><br/>';
						echo esc_html__( 'Layout:', 'wpc-price-by-quantity' ) . ' ' . esc_html( $price['layout'] ) . '<br/>';

						if ( ! empty( $price['tiers'] ) ) {
							echo esc_html__( 'Quantity-based pricing options:', 'wpc-price-by-quantity' );
							echo '<table><thead><tr><td>' . esc_html__( 'Qty', 'wpc-price-by-quantity' ) . '</td><td>' . esc_html__( 'Price', 'wpc-price-by-quantity' ) . '</td><td>' . esc_html__( 'Text', 'wpc-price-by-quantity' ) . '</td></tr></thead>';
							echo '<tbody>';

							foreach ( $price['tiers'] as $tier ) {
								$tier = array_merge( [
									'quantity' => '',
									'price'    => '',
									'text'     => '',
								], $tier );
								echo '<tr><td>' . esc_html( $tier['quantity'] ) . '</td><td>' . esc_html( $tier['price'] ) . '</td><td>' . wp_kses_post( $tier['text'] ) . '</td></tr>';
							}

							echo '</tbody>';
							echo '</table>';
						} else {
							echo esc_html__( 'Quantity-based pricing options:', 'wpc-price-by-quantity' ) . ' ' . esc_html__( 'Not set', 'wpc-price-by-quantity' );
						}

						echo '</div>';
					}
				}

				echo '</div>';
			}

			wp_die();
		}
	}

	function Wpcpq_Backend() {
		return Wpcpq_Backend::instance();
	}

	Wpcpq_Backend();
}