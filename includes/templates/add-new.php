<?php
/**
 * @var $is_variation
 * @var $product_id
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wpcpq-items-new">
    <select class="wpcpq-item-new-role">
		<?php
		echo '<option value="all">' . esc_html__( 'All users', 'wpc-price-by-quantity' ) . '</option>';

		foreach ( $wp_roles->roles as $role => $details ) {
			echo '<option value="' . esc_attr( $role ) . '">' . esc_html( $details['name'] ) . '</option>';
		}
		?>
    </select>
    <input type="button" class="button wpcpq-item-new" value="<?php esc_attr_e( '+ Setup for role', 'wpc-price-by-quantity' ); ?>" data-id="<?php echo esc_attr( $is_variation ? $product_id : 0 ); ?>">
</div>
