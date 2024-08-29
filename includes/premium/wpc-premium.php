<?php
defined( 'ABSPATH' ) || exit;

include_once 'wpc-checker.php';

if ( ! function_exists( 'wpcpq_update_checker' ) ) {
	add_action( 'init', 'wpcpq_update_checker', 99 );
	add_action( 'admin_notices', 'wpcpq_update_notice', 99 );
	add_action( 'in_plugin_update_message-' . plugin_basename( WPCPQ_FILE ), 'wpcpq_update_license', 99 );

	function wpcpq_update_checker() {
		if ( $key = wpc_get_update_key( 59141 ) ) {
			PucFactory::buildUpdateChecker( 'https://api.wpclever.net/update/' . $key . '.json', WPCPQ_FILE, plugin_basename( WPCPQ_DIR ) );
		} else {
			PucFactory::buildUpdateChecker( 'https://api.wpclever.net/update/59141.json', WPCPQ_FILE, plugin_basename( WPCPQ_DIR ) );
		}
	}

	function wpcpq_update_license( $plugin_data ) {
		if ( empty( $plugin_data['package'] ) ) {
			echo ' <em>Please verify your license key in <a href="' . esc_url( admin_url( 'admin.php?page=wpclever-keys' ) ) . '" target="_blank">WPClever > License Keys</a> to update.</em>';
		}
	}

	function wpcpq_update_notice() {
		if ( apply_filters( 'wpc_dismiss_notices', false ) ) {
			return;
		}

		if ( get_option( 'wpc_dismiss_notice_wpcpq_update' ) ) {
			return;
		}

		if ( ! wpc_get_update_key( 59141 ) ) {
			?>
            <div data-dismissible="wpcpq_update" class="wpc-notice notice notice-warning is-dismissible">
                <p>Please verify
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-keys' ) ); ?>">License Key</a> of
                    <strong>WPC Price by Quantity</strong> to enjoy unlimited update release and get the latest plugin update directly on the website backend.
                </p>
            </div>
			<?php
		}
	}
}

if ( ! function_exists( 'wpc_get_update_key' ) ) {
	function wpc_get_update_key( $id = '' ) {
		if ( ! empty( $id ) ) {
			$keys = (array) get_option( 'wpc_update_keys', [] );

			if ( empty( $keys ) ) {
				return false;
			}

			foreach ( array_reverse( $keys ) as $key ) {
				if ( isset( $key['plugins'] ) && is_array( $key['plugins'] ) && ! empty( $key['plugins'] ) ) {
					foreach ( $key['plugins'] as $plugin ) {
						if ( $plugin->id == $id ) {
							return $plugin->key;
						}
					}
				}
			}
		}

		return false;
	}
}

if ( ! class_exists( 'WPCleverPremium' ) ) {
	class WPCleverPremium {
		function __construct() {
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
			add_action( 'wp_ajax_wpc_check_update_key', [ $this, 'check_update_key' ] );
			add_action( 'wp_ajax_wpc_remove_key', [ $this, 'remove_key' ] );
			add_action( 'wp_ajax_wpc_dismiss_notice', [ $this, 'dismiss_notice' ] );
		}

		function admin_enqueue_scripts( $hook ) {
			if ( strpos( $hook, 'wpclever-keys' ) ) {
				wp_enqueue_style( 'wpc-premium', WPCPQ_URI . 'includes/premium/css/premium.css' );
				wp_enqueue_script( 'wpc-premium', WPCPQ_URI . 'includes/premium/js/premium.js', [ 'jquery', ] );
			}
		}

		function admin_menu() {
			add_submenu_page( 'wpclever', 'WPC License Keys', 'License Keys', 'manage_options', 'wpclever-keys', [
				$this,
				'admin_menu_content'
			], 1 );
		}

		function admin_menu_content() {
			?>
            <div class="wpclever_page wpclever_update_keys_page wrap">
                <h1>WPClever | License Keys</h1>
                <div class="card">
                    <h2 class="title">Enter Your License Keys</h2>
                    <p>
                        <strong>Enter your License Key to verify the license you’re using and turn on the update notification. Verified licenses can enjoy unlimited update release and get the latest plugin update directly on our website.</strong>
                    </p>
                    <p>
                        Please check the purchase receipt to find your Receipt ID (old-type invoice) or License Key (new-type invoice) to verify your license(s). You can also access the
                        <a href="https://wpclever.net/my-account/" target="_blank">Membership page</a> to get the license key and enter it below for the verification of each purchase attached to your account.
                    </p>
                    <div class="wpclever_update_keys_form">
                        <input type="hidden" name="wpc_update_site" id="wpc_update_site" value="<?php echo esc_attr( get_bloginfo( 'url' ) ); ?>"/>
                        <input type="hidden" name="wpc_update_email" id="wpc_update_email" value="<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>"/>
                        <input type="text" name="wpc_update_key" id="wpc_update_key" class="regular-text" placeholder="Receipt ID or License Key"/>
                        <input type="button" value="Verify" id="wpc_add_update_key"/>
                    </div>
                </div>
                <div class="card wpclever_plugins">
                    <h2 class="title">Verified Keys</h2>
					<?php
					$keys = (array) get_option( 'wpc_update_keys', [] );

					if ( ! empty( $keys ) ) {
						?>
                        <table class="wpc_update_keys">
                            <thead>
                            <tr>
                                <th>Key</th>
                                <th>Allowed plugins</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
							<?php
							foreach ( array_reverse( $keys ) as $key => $val ) {
								echo '<tr>';
								echo '<td>' . esc_html( substr( $key, 0, 10 ) . '...' . substr( $key, strlen( $key ) - 4, 4 ) ) . '</td>';
								echo '<td>';
								echo '<ul>';

								foreach ( $val['plugins'] as $plugin ) {
									echo '<li>' . esc_html( $plugin->name ) . '</li>';
								}

								echo '</ul>';
								echo '</td>';
								echo '<td><div class="wpc-premium-validated">Validated on: ' . esc_html( isset( $val['date'] ) ? $val['date'] : '' ) . '</div><div class="wpc-premium-support-expires">Support expires: ' . ( isset( $val['date'] ) ? esc_html( wp_date( 'Y-m-d H:i:s', strtotime( '+1 year', strtotime( $val['date'] ) ) ) ) : '' ) . ' ' . ( time() > strtotime( '+1 year', strtotime( $val['date'] ) ) ? '(expired)' : '' ) . '</div></td>';
								echo '<td><a href="#" class="wpc_remove_key" data-key="' . esc_attr( $key ) . '">remove</a></td>';
								echo '</tr>';
							}
							?>
                            </tbody>
                        </table>
					<?php } else {
						echo '<p>Have no keys was verified. Please add your first one!</p>';
					} ?>
                </div>
            </div>
			<?php
		}

		function check_update_key() {
			if ( isset( $_POST['key'] ) && ! empty( $_POST['key'] ) ) {
				$key      = sanitize_key( $_POST['key'] );
				$site     = sanitize_url( isset( $_POST['site'] ) ? $_POST['site'] : '' );
				$email    = sanitize_email( isset( $_POST['email'] ) ? $_POST['email'] : '' );
				$response = wp_remote_post( 'https://wpclever.net/wp-json/update/v2/verify/', [
					'headers' => [ 'Accept' => 'application/json' ],
					'body'    => [
						'key'   => $key,
						'site'  => $site,
						'email' => $email
					],
				] );
				$data     = wp_remote_retrieve_body( $response );

				if ( ! empty( $data ) ) {
					$result = json_decode( $data );

					if ( property_exists( $result, 'id' ) && $result->id && property_exists( $result, 'plugins' ) ) {
						// add keys
						$keys                = (array) get_option( 'wpc_update_keys', [] );
						$secret_key          = substr( $key, 0, 10 ) . substr( $key, strlen( $key ) - 4, 4 );
						$keys[ $secret_key ] = [
							'id'      => $result->id,
							'plugins' => $result->plugins,
							'date'    => property_exists( $result, 'date' ) ? $result->date : ''
						];

						update_option( 'wpc_update_keys', $keys );
					}
				}
			}

			wp_die();
		}

		function remove_key() {
			if ( isset( $_POST['key'] ) && ! empty( $_POST['key'] ) ) {
				$key  = sanitize_key( $_POST['key'] );
				$keys = (array) get_option( 'wpc_update_keys', [] );
				unset( $keys[ $key ] );

				update_option( 'wpc_update_keys', $keys );
			}

			wp_die();
		}

		function dismiss_notice() {
			if ( isset( $_POST['key'] ) && ! empty( $_POST['key'] ) ) {
				$key = sanitize_key( $_POST['key'] );

				update_option( 'wpc_dismiss_notice_' . $key, time() );
			}

			wp_die();
		}

		public static function get_update_key( $id = '' ) {
			if ( ! empty( $id ) ) {
				$keys = (array) get_option( 'wpc_update_keys', [] );

				if ( empty( $keys ) ) {
					return false;
				}

				foreach ( array_reverse( $keys ) as $key ) {
					if ( isset( $key['plugins'] ) && is_array( $key['plugins'] ) && ! empty( $key['plugins'] ) ) {
						foreach ( $key['plugins'] as $plugin ) {
							if ( $plugin->id == $id ) {
								return $plugin->key;
							}
						}
					}
				}
			}

			return false;
		}
	}

	new WPCleverPremium();
}
