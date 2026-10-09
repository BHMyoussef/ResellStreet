<?php
/**
 * Payment icons chosen in the Customizer.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'payment_icons_enable' ) ) {
	return;
}

$ci_icons = array_filter(
	array_keys( ci_payment_icon_choices() ),
	fn( $key ) => (bool) ci_mod( "payment_{$key}" )
);
if ( ! $ci_icons ) {
	return;
}
?>
<ul class="payment-icons" aria-label="<?php esc_attr_e( 'Payment methods', 'customimpulse' ); ?>">
	<?php foreach ( $ci_icons as $ci_key ) : ?>
		<li><?php echo ci_payment_icon( $ci_key ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG, label escaped. ?></li>
	<?php endforeach; ?>
</ul>
