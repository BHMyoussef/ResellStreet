<?php
/**
 * Redirect after order: optional redirect from the order-received page to another URL.
 *
 * Settings: WooCommerce → Settings → Advanced → Redirect after order (options rs_redirect_enabled / _url / _delay).
 * The redirect is client side (setTimeout) on purpose: the thank-you page must load fully first so the Meta Pixel
 * Purchase event (inc/meta-tracking.php, woocommerce_thankyou priority 10) fires before the customer leaves.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the "Redirect after order" section to the Advanced tab.
 *
 * @param array $sections Section id => label.
 * @return array
 */
function ci_order_redirect_section( $sections ) {
	$sections['rs_redirect'] = __( 'Redirect after order', 'customimpulse' );
	return $sections;
}
add_filter( 'woocommerce_get_sections_advanced', 'ci_order_redirect_section' );

/**
 * Fields of the section.
 *
 * @param array  $settings        Settings of the current Advanced section.
 * @param string $current_section Current section id.
 * @return array
 */
function ci_order_redirect_settings( $settings, $current_section ) {
	if ( 'rs_redirect' !== $current_section ) {
		return $settings;
	}

	return array(
		array(
			'id'    => 'rs_redirect_options',
			'type'  => 'title',
			'title' => __( 'Redirect after order', 'customimpulse' ),
			'desc'  => __( 'Send customers to another page after the order confirmation has loaded (the Meta Pixel Purchase event fires first). Failed orders are never redirected.', 'customimpulse' ),
		),
		array(
			'id'      => 'rs_redirect_enabled',
			'type'    => 'checkbox',
			'title'   => __( 'Enable', 'customimpulse' ),
			'desc'    => __( 'Redirect after the order-received page', 'customimpulse' ),
			'default' => 'no',
		),
		array(
			'id'          => 'rs_redirect_url',
			'type'        => 'url',
			'title'       => __( 'Redirect URL', 'customimpulse' ),
			'desc_tip'    => __( 'Full URL, starting with https://', 'customimpulse' ),
			'placeholder' => 'https://',
			'default'     => '',
			'css'         => 'min-width: 400px;',
		),
		array(
			'id'                => 'rs_redirect_delay',
			'type'              => 'number',
			'title'             => __( 'Delay (seconds)', 'customimpulse' ),
			'desc_tip'          => __( '0–30 seconds. Keep at least 2 so tracking pixels can fire.', 'customimpulse' ),
			'default'           => '2',
			'custom_attributes' => array(
				'min'  => 0,
				'max'  => 30,
				'step' => 1,
			),
		),
		array(
			'id'   => 'rs_redirect_options',
			'type' => 'sectionend',
		),
	);
}
add_filter( 'woocommerce_get_settings_advanced', 'ci_order_redirect_settings', 10, 2 );

/**
 * Sanitize the URL on save (http/https only).
 *
 * @param string $value Raw value.
 * @return string
 */
function ci_order_redirect_sanitize_url( $value ) {
	return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
}
add_filter( 'woocommerce_admin_settings_sanitize_option_rs_redirect_url', 'ci_order_redirect_sanitize_url' );

/**
 * Clamp the delay to 0–30 seconds.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function ci_order_redirect_clamp_delay( $value ) {
	return max( 0, min( 30, absint( $value ) ) );
}
add_filter( 'woocommerce_admin_settings_sanitize_option_rs_redirect_delay', 'ci_order_redirect_clamp_delay' );

/**
 * The configured redirect URL, or '' when the feature is off or the URL isn't a valid http(s) URL.
 *
 * @return string
 */
function ci_order_redirect_url() {
	if ( 'yes' !== get_option( 'rs_redirect_enabled', 'no' ) ) {
		return '';
	}
	$url = ci_order_redirect_sanitize_url( get_option( 'rs_redirect_url', '' ) );
	return $url && filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}

/**
 * Order-received page: notice + delayed client-side redirect.
 *
 * @param int $order_id Order ID.
 */
function ci_order_redirect_thankyou( $order_id ) {
	$url = ci_order_redirect_url();
	if ( ! $url ) {
		return;
	}
	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order || $order->has_status( 'failed' ) ) {
		return;
	}
	$delay = ci_order_redirect_clamp_delay( get_option( 'rs_redirect_delay', 2 ) );
	?>
	<p class="ci-order-redirect" role="status">
		<?php esc_html_e( 'Redirecting you…', 'customimpulse' ); ?>
		<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Click here if nothing happens', 'customimpulse' ); ?></a>
	</p>
	<script>
		setTimeout( function () { window.location.href = <?php echo wp_json_encode( $url ); ?>; }, <?php echo (int) $delay * 1000; ?> );
	</script>
	<?php
}
add_action( 'woocommerce_thankyou', 'ci_order_redirect_thankyou', 99 );
