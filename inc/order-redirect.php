<?php
/**
 * Redirect after order: optional redirect from the order-received page to another URL.
 *
 * Settings: WooCommerce → Settings → Advanced → Redirect after order (options rs_redirect_enabled / _url / _delay).
 * The URL is a template with {tags} (rs_redirect_placeholder_list(), filter `rs_redirect_placeholders`), filled per
 * order with rawurlencoded values. The redirect is client side (setTimeout) on purpose: the thank-you page must load
 * fully first so the Meta Pixel Purchase event (inc/meta-tracking.php, woocommerce_thankyou priority 10) fires before
 * the customer leaves.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Available URL tags: tag => callable( WC_Order ) returning the raw value.
 *
 * @return array
 */
function rs_redirect_placeholder_list() {
	$tags = array(
		'{order_id}'       => function ( $order ) {
			return $order->get_id();
		},
		'{order_number}'   => function ( $order ) {
			return $order->get_order_number();
		},
		'{order_key}'      => function ( $order ) {
			return $order->get_order_key();
		},
		'{status}'         => function ( $order ) {
			return $order->get_status();
		},
		'{total}'          => function ( $order ) {
			return $order->get_total();
		},
		'{currency}'       => function ( $order ) {
			return $order->get_currency();
		},
		'{payment_method}' => function ( $order ) {
			return $order->get_payment_method();
		},
		'{customer_id}'    => function ( $order ) {
			return $order->get_customer_id();
		},
		'{email}'          => function ( $order ) {
			return $order->get_billing_email();
		},
		'{first_name}'     => function ( $order ) {
			return $order->get_billing_first_name();
		},
		'{last_name}'      => function ( $order ) {
			return $order->get_billing_last_name();
		},
		'{phone}'          => function ( $order ) {
			return $order->get_billing_phone();
		},
		'{address}'        => function ( $order ) {
			return trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );
		},
		'{city}'           => function ( $order ) {
			return $order->get_billing_city();
		},
		'{postcode}'       => function ( $order ) {
			return $order->get_billing_postcode();
		},
		'{country}'        => function ( $order ) {
			return $order->get_billing_country();
		},
		'{product_ids}'    => function ( $order ) {
			$ids = array();
			foreach ( $order->get_items() as $item ) {
				$ids[] = $item->get_product_id();
			}
			return implode( ',', array_unique( array_filter( $ids ) ) );
		},
		'{item_count}'     => function ( $order ) {
			return $order->get_item_count();
		},
	);

	return apply_filters( 'rs_redirect_placeholders', $tags );
}

/**
 * Fill the tags present in the template with the order's values (rawurlencoded).
 *
 * @param string   $template URL template.
 * @param WC_Order $order    Order.
 * @return string
 */
function rs_redirect_build_url( $template, WC_Order $order ) {
	$pairs = array();
	foreach ( rs_redirect_placeholder_list() as $tag => $callback ) {
		if ( false !== strpos( $template, $tag ) && is_callable( $callback ) ) {
			$pairs[ $tag ] = rawurlencode( (string) call_user_func( $callback, $order ) );
		}
	}
	return $pairs ? strtr( $template, $pairs ) : $template;
}

/**
 * Add the "Redirect after order" section to the Advanced tab.
 *
 * @param array $sections Section id => label.
 * @return array
 */
function rs_redirect_section( $sections ) {
	$sections['rs_redirect'] = __( 'Redirect after order', 'customimpulse' );
	return $sections;
}
add_filter( 'woocommerce_get_sections_advanced', 'rs_redirect_section' );

/**
 * Fields of the section.
 *
 * @param array  $settings        Settings of the current Advanced section.
 * @param string $current_section Current section id.
 * @return array
 */
function rs_redirect_settings( $settings, $current_section ) {
	if ( 'rs_redirect' !== $current_section ) {
		return $settings;
	}

	$chips = array();
	foreach ( array_keys( rs_redirect_placeholder_list() ) as $tag ) {
		$chips[] = '<code>' . esc_html( $tag ) . '</code>';
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
			'type'        => 'text', // Not "url": it must keep the {tags}.
			'title'       => __( 'Redirect URL', 'customimpulse' ),
			'desc'        => __( 'Available tags:', 'customimpulse' ) . ' ' . implode( ' ', $chips ),
			'placeholder' => 'https://example.com/welcome?order={order_id}&email={email}',
			'default'     => '',
			'css'         => 'min-width: 500px;',
		),
		array(
			'id'                => 'rs_redirect_delay',
			'type'              => 'number',
			'title'             => __( 'Delay (seconds)', 'customimpulse' ),
			'desc_tip'          => __( 'Keep 2+ seconds so the Meta Pixel Purchase event fires.', 'customimpulse' ),
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
add_filter( 'woocommerce_get_settings_advanced', 'rs_redirect_settings', 10, 2 );

/**
 * Sanitize the URL template on save: plain text, http(s) only, else empty. No esc_url_raw (it breaks the {braces}).
 *
 * @param string $value Raw value.
 * @return string
 */
function rs_redirect_sanitize_url( $value ) {
	$value = sanitize_text_field( (string) $value );
	return preg_match( '#^https?://#i', $value ) ? $value : '';
}
add_filter( 'woocommerce_admin_settings_sanitize_option_rs_redirect_url', 'rs_redirect_sanitize_url' );

/**
 * Clamp the delay to 0–30 seconds.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function rs_redirect_sanitize_delay( $value ) {
	return max( 0, min( 30, absint( $value ) ) );
}
add_filter( 'woocommerce_admin_settings_sanitize_option_rs_redirect_delay', 'rs_redirect_sanitize_delay' );

/**
 * Order-received page: notice + delayed client-side redirect.
 *
 * @param int $order_id Order ID.
 */
function rs_redirect_thankyou( $order_id ) {
	if ( 'yes' !== get_option( 'rs_redirect_enabled', 'no' ) ) {
		return;
	}
	$template = (string) get_option( 'rs_redirect_url', '' );
	if ( '' === $template ) {
		return;
	}
	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order || $order->has_status( 'failed' ) ) {
		return;
	}
	$url = esc_url_raw( rs_redirect_build_url( $template, $order ) );
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		return;
	}
	$delay = rs_redirect_sanitize_delay( get_option( 'rs_redirect_delay', 2 ) );
	?>
	<p class="rs-order-redirect" role="status">
		<?php esc_html_e( 'Redirecting you…', 'customimpulse' ); ?>
		<?php // target="_self": Rank Math's "open external links in a new tab" skips links that already have a target. ?>
		<a href="<?php echo esc_url( $url ); ?>" target="_self"><?php esc_html_e( 'Click here if nothing happens.', 'customimpulse' ); ?></a>
	</p>
	<script>
		setTimeout( function () { window.location.href = <?php echo wp_json_encode( $url ); ?>; }, <?php echo (int) $delay * 1000; ?> );
	</script>
	<?php
}
add_action( 'woocommerce_thankyou', 'rs_redirect_thankyou', 99 );
