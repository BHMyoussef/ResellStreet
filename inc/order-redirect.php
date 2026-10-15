<?php
/**
 * Redirect after order + "Order confirmation (from link)" page.
 *
 * Settings: WooCommerce → Settings → Advanced → Redirect after order (options rs_redirect_enabled / _url / _delay and
 * one rs_redirect_send_{field} checkbox per order field). The order-received page redirects (JS setTimeout, so the
 * Meta Pixel Purchase event fires first) to the URL with the selected order data in one parameter:
 * ?o=<base64url JSON>.<signature>. The signature (HMAC with the site's auth salt) makes the page reject edited links.
 * The page template template-order-confirmation.php renders that data with the order-received markup
 * (template-parts/order/confirmation.php); the real order-received page is untouched.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const RS_ORDER_COPY_TEMPLATE = 'template-order-confirmation.php';

/**
 * Order fields that can be sent: key => array( label, default ).
 *
 * @return array
 */
function rs_redirect_fields() {
	return array(
		'order'    => array( __( 'Order number and date', 'customimpulse' ), 'yes' ),
		'items'    => array( __( 'Products (name, options, quantity, price, image)', 'customimpulse' ), 'yes' ),
		'totals'   => array( __( 'Totals (subtotal, discount, shipping, total)', 'customimpulse' ), 'yes' ),
		'payment'  => array( __( 'Payment method', 'customimpulse' ), 'yes' ),
		'email'    => array( __( 'Email', 'customimpulse' ), 'no' ),
		'phone'    => array( __( 'Phone', 'customimpulse' ), 'no' ),
		'billing'  => array( __( 'Billing address (with name)', 'customimpulse' ), 'no' ),
		'shipping' => array( __( 'Shipping address (with name)', 'customimpulse' ), 'no' ),
	);
}

/**
 * Whether a field is selected in the settings.
 *
 * @param string $key Field key.
 * @return bool
 */
function rs_redirect_sends( $key ) {
	$fields = rs_redirect_fields();
	return isset( $fields[ $key ] ) && 'yes' === get_option( 'rs_redirect_send_' . $key, $fields[ $key ][1] );
}

/* ---------------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------------ */

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

	$settings = array(
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
			'desc'        => __( 'A page using the "Order confirmation (from link)" template shows the order like the order-received page. The selected data is added to the URL as ?o=… (base64).', 'customimpulse' ),
			'placeholder' => home_url( '/order-confirmation/' ),
			'default'     => '',
			'css'         => 'min-width: 500px;',
		),
	);

	$fields = rs_redirect_fields();
	$last   = array_key_last( $fields );
	foreach ( $fields as $key => $field ) {
		$settings[] = array(
			'id'            => 'rs_redirect_send_' . $key,
			'type'          => 'checkbox',
			'title'         => array_key_first( $fields ) === $key ? __( 'Data to send', 'customimpulse' ) : '',
			'desc'          => $field[0],
			'default'       => $field[1],
			'checkboxgroup' => array_key_first( $fields ) === $key ? 'start' : ( $last === $key ? 'end' : '' ),
		);
	}

	$settings[] = array(
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
	);
	$settings[] = array(
		'id'   => 'rs_redirect_options',
		'type' => 'sectionend',
	);

	return $settings;
}
add_filter( 'woocommerce_get_settings_advanced', 'rs_redirect_settings', 10, 2 );

/**
 * Sanitize the URL on save (http/https only).
 *
 * @param string $value Raw value.
 * @return string
 */
function rs_redirect_sanitize_url( $value ) {
	return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
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

/* ---------------------------------------------------------------------------
 * Token: base64url(JSON) + "." + signature
 * ------------------------------------------------------------------------ */

/**
 * Plain text of a WooCommerce HTML value ("£4.99 via Standard delivery").
 *
 * @param string $html HTML.
 * @return string
 */
function rs_redirect_text( $html ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $text ) ) );
}

/**
 * The selected order data (short keys keep the URL short).
 *
 * @param WC_Order $order Order.
 * @return array
 */
function rs_redirect_payload( WC_Order $order ) {
	$data = array();

	if ( rs_redirect_sends( 'order' ) ) {
		$data['n'] = (string) $order->get_order_number();
		$data['d'] = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time();
	}

	if ( rs_redirect_sends( 'items' ) ) {
		$data['i'] = array();
		foreach ( $order->get_items() as $item ) {
			$row = array(
				'n' => $item->get_name(),
				'q' => (int) $item->get_quantity(),
				't' => rs_redirect_text( $order->get_formatted_line_subtotal( $item ) ),
			);
			if ( $item instanceof WC_Order_Item_Product ) {
				$row['p'] = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			}
			// Same meta as the order-received page (hides attributes already in the variation name).
			foreach ( $item->get_formatted_meta_data() as $meta ) {
				$row['m'][] = array( rs_redirect_text( $meta->display_key ), rs_redirect_text( $meta->display_value ) );
			}
			$data['i'][] = $row;
		}
	}

	if ( rs_redirect_sends( 'totals' ) ) {
		$data['t'] = array();
		foreach ( $order->get_order_item_totals() as $key => $total ) {
			if ( 'payment_method' === $key ) {
				continue;
			}
			// Row: label, value, is order total, "via …" (shipping method, shown small like WooCommerce).
			$value = (string) $total['value'];
			$via   = '';
			if ( preg_match( '#<small class="shipped_via">(.*?)</small>#s', $value, $match ) ) {
				$via   = rs_redirect_text( $match[1] );
				$value = str_replace( $match[0], '', $value );
			}
			$row = array( rs_redirect_text( $total['label'] ), rs_redirect_text( $value ), 'order_total' === $key ? 1 : 0 );
			if ( $via ) {
				$row[] = $via;
			}
			$data['t'][] = $row;
		}
	}

	if ( rs_redirect_sends( 'payment' ) && $order->get_payment_method_title() ) {
		$data['pm'] = $order->get_payment_method_title();
	}
	if ( rs_redirect_sends( 'email' ) && $order->get_billing_email() ) {
		$data['e'] = $order->get_billing_email();
	}
	if ( rs_redirect_sends( 'phone' ) && $order->get_billing_phone() ) {
		$data['ph'] = $order->get_billing_phone();
	}
	if ( rs_redirect_sends( 'phone' ) && rs_redirect_sends( 'shipping' ) && $order->get_shipping_phone() ) {
		$data['sp'] = $order->get_shipping_phone();
	}
	foreach ( array( 'billing' => 'b', 'shipping' => 's' ) as $type => $short ) {
		if ( rs_redirect_sends( $type ) ) {
			$address = array_filter( array_intersect_key( $order->get_address( $type ), array_flip( rs_redirect_address_keys() ) ) );
			if ( $address ) {
				$data[ $short ] = $address;
			}
		}
	}

	return $data;
}

/**
 * Address parts that are sent.
 *
 * @return array
 */
function rs_redirect_address_keys() {
	return array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );
}

/**
 * Base64url without padding.
 *
 * @param string $raw Bytes.
 * @return string
 */
function rs_redirect_b64( $raw ) {
	return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Signature of the encoded data (16 bytes of HMAC-SHA256 with the site's auth salt).
 *
 * @param string $encoded Base64url data.
 * @return string
 */
function rs_redirect_sign( $encoded ) {
	return rs_redirect_b64( substr( hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ), true ), 0, 16 ) );
}

/**
 * Data → token.
 *
 * @param array $data Payload.
 * @return string
 */
function rs_redirect_encode( array $data ) {
	$encoded = rs_redirect_b64( wp_json_encode( $data ) );
	return $encoded . '.' . rs_redirect_sign( $encoded );
}

/**
 * Token → data, or null when it's missing, edited or broken.
 *
 * @param string $token Token from the URL.
 * @return array|null
 */
function rs_redirect_decode( $token ) {
	$token = (string) $token;
	if ( '' === $token || strlen( $token ) > 16000 || 1 !== substr_count( $token, '.' ) ) {
		return null;
	}
	list( $encoded, $signature ) = explode( '.', $token );
	if ( ! hash_equals( rs_redirect_sign( $encoded ), $signature ) ) {
		return null;
	}
	$json = base64_decode( strtr( $encoded, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	$data = false === $json ? null : json_decode( $json, true );
	return is_array( $data ) ? $data : null;
}

/* ---------------------------------------------------------------------------
 * Order-received page: notice + delayed client-side redirect
 * ------------------------------------------------------------------------ */

/**
 * The redirect URL for an order: setting URL + ?o=token (when any data is selected).
 *
 * @param WC_Order $order Order.
 * @return string
 */
function rs_redirect_build_url( WC_Order $order ) {
	$url  = (string) get_option( 'rs_redirect_url', '' );
	$data = rs_redirect_payload( $order );
	return $data ? add_query_arg( 'o', rs_redirect_encode( $data ), $url ) : $url;
}

/**
 * Notice + redirect.
 *
 * @param int $order_id Order ID.
 */
function rs_redirect_thankyou( $order_id ) {
	if ( 'yes' !== get_option( 'rs_redirect_enabled', 'no' ) || '' === (string) get_option( 'rs_redirect_url', '' ) ) {
		return;
	}
	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order || $order->has_status( 'failed' ) ) {
		return;
	}
	$url = esc_url_raw( rs_redirect_build_url( $order ) );
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

/* ---------------------------------------------------------------------------
 * "Order confirmation (from link)" page
 * ------------------------------------------------------------------------ */

/**
 * Whether the current page uses the order confirmation template.
 *
 * @return bool
 */
function rs_is_order_copy_page() {
	return is_page_template( RS_ORDER_COPY_TEMPLATE );
}

/* Personal data in the page: never cached (also LiteSpeed on Hostinger), never indexed. */
add_action(
	'template_redirect',
	function () {
		if ( ! rs_is_order_copy_page() ) {
			return;
		}
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'order confirmation from link' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}
);
add_filter(
	'wp_robots',
	fn( $robots ) => rs_is_order_copy_page() ? wp_robots_no_robots( $robots ) : $robots
);
add_filter(
	'rank_math/frontend/robots',
	fn( $robots ) => rs_is_order_copy_page() ? array( 'index' => 'noindex', 'follow' => 'nofollow' ) : $robots
);

/**
 * Formatted address (HTML with <br>) from token data.
 *
 * @param mixed $address Address array from the token.
 * @return string
 */
function rs_order_copy_address( $address ) {
	if ( ! is_array( $address ) ) {
		return '';
	}
	$clean = array();
	foreach ( rs_redirect_address_keys() as $key ) {
		if ( isset( $address[ $key ] ) && is_scalar( $address[ $key ] ) ) {
			$clean[ $key ] = sanitize_text_field( (string) $address[ $key ] );
		}
	}
	return $clean ? WC()->countries->get_formatted_address( $clean ) : '';
}

/**
 * String from token data ('' for anything that isn't a scalar).
 *
 * @param array  $data Token data.
 * @param string $key  Key.
 * @return string
 */
function rs_order_copy_str( $data, $key ) {
	return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? (string) $data[ $key ] : '';
}

/**
 * Escaped text with prices ("£4.99") wrapped in WooCommerce's price markup, so they render like wc_price().
 *
 * @param string $text Plain text from the token.
 * @return string HTML.
 */
function rs_order_copy_prices( $text ) {
	$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
	return preg_replace(
		'/' . preg_quote( esc_html( $symbol ), '/' ) . '(\d[\d.,]*)/u',
		'<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . esc_html( $symbol ) . '</span>$1</bdi></span>',
		esc_html( (string) $text )
	);
}
