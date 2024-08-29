<?php
/*
Plugin Name: WPC Price by Quantity for WooCommerce (Premium)
Plugin URI: https://wpclever.net/
Description: Offering quantity-based prices would be one of the most effective and powerful methods to urge buyers with very few convincing actions needed.
Version: 5.1.5
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-price-by-quantity
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.5
WC requires at least: 3.0
WC tested up to: 9.0
*/

! defined( 'WPCPQ_VERSION' ) && define( 'WPCPQ_VERSION', '5.1.2' );
! defined( 'WPCPQ_PREMIUM' ) && define( 'WPCPQ_PREMIUM', __FILE__ );
! defined( 'WPCPQ_FILE' ) && define( 'WPCPQ_FILE', __FILE__ );
! defined( 'WPCPQ_DIR' ) && define( 'WPCPQ_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCPQ_URI' ) && define( 'WPCPQ_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCPQ_SUPPORT' ) && define( 'WPCPQ_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcpq&utm_campaign=wporg' );
! defined( 'WPCPQ_REVIEWS' ) && define( 'WPCPQ_REVIEWS', 'https://wordpress.org/support/plugin/wpc-price-by-quantity/reviews/?filter=5' );
! defined( 'WPCPQ_CHANGELOG' ) && define( 'WPCPQ_CHANGELOG', 'https://wordpress.org/plugins/wpc-price-by-quantity/#developers' );
! defined( 'WPCPQ_DISCUSSION' ) && define( 'WPCPQ_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-price-by-quantity' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCPQ_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';
include 'includes/premium/wpc-premium.php';

if ( ! function_exists( 'wpcpq_init' ) ) {
	add_action( 'plugins_loaded', 'wpcpq_init', 11 );

	function wpcpq_init() {
		load_plugin_textdomain( 'wpc-price-by-quantity', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcpq_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcpq' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWpcpq {
				public function __construct() {
					require_once trailingslashit( WPCPQ_DIR ) . 'includes/class-helper.php';
					require_once trailingslashit( WPCPQ_DIR ) . 'includes/class-backend.php';
					require_once trailingslashit( WPCPQ_DIR ) . 'includes/class-frontend.php';
					require_once trailingslashit( WPCPQ_DIR ) . 'includes/class-cart.php';
				}
			}

			new WPCleverWpcpq();
		}
	}
}

if ( ! function_exists( 'wpcpq_notice_wc' ) ) {
	function wpcpq_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Price by Quantity</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
