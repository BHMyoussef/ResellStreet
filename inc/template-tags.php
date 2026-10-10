<?php
/**
 * Template helpers: icons, logo, announcement data, payment icons.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG icon (64-unit grid, stroke weight from --icon-weight).
 *
 * @param string $name  Icon name.
 * @param string $class Extra classes.
 */
function ci_icon( $name, $class = '' ) {
	$paths = array(
		'menu'         => '<path d="M7 15h50M7 32h50M7 49h50"/>',
		'close'        => '<path d="M17 17l30 30M47 17L17 47"/>',
		'user'         => '<circle cx="32" cy="21" r="12"/><path d="M10 57c0-12.2 9.8-20 22-20s22 7.8 22 20"/>',
		'bag'          => '<path d="M12 21h40l-3 35H15z"/><path d="M24 21v-5a8 8 0 0116 0v5"/>',
		'search'       => '<circle cx="27" cy="27" r="17"/><path d="M39 39l17 17"/>',
		'chevron-down' => '<path d="M12 24l20 20 20-20"/>',
		'chevron-left' => '<path d="M40 12L20 32l20 20"/>',
		'chevron-right' => '<path d="M24 12l20 20-20 20"/>',
		'email'        => '<path d="M7 15h50v34H7z"/><path d="M7 15l25 20 25-20"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="icon icon--%1$s %2$s" viewBox="0 0 64 64" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		esc_attr( $class ),
		$paths[ $name ] // Static markup above.
	);
}

/**
 * Logo image (custom logo) or the site name as text.
 *
 * @param string $context 'header' | 'footer'.
 */
function ci_logo( $context = 'header' ) {
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$name    = get_bloginfo( 'name', 'display' );

	if ( $logo_id ) {
		$sizes = 'header' === $context
			? sprintf( '(min-width: 769px) %dpx, %dpx', absint( ci_mod( 'logo_width_desktop' ) ), absint( ci_mod( 'logo_width_mobile' ) ) )
			: '300px';
		$img = wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class'    => "custom-logo custom-logo--{$context}",
				'alt'      => $name,
				'loading'  => 'header' === $context ? 'eager' : 'lazy',
				'decoding' => 'async',
				'sizes'    => $sizes,
			)
		);
		if ( $img ) {
			return $img;
		}
	}
	return sprintf( '<span class="%s">%s</span>', 'header' === $context ? 'site-header__logo-text' : 'footer__logo-text', esc_html( $name ) );
}

/**
 * Non-empty announcement messages from the Customizer.
 *
 * @return array<int,array{text:string,link:string}>
 */
function ci_announcement_messages() {
	if ( ! ci_mod( 'announcement_enable' ) ) {
		return array();
	}
	$messages = array();
	for ( $i = 1; $i <= CI_ANNOUNCEMENT_SLOTS; $i++ ) {
		$text = trim( (string) ci_mod( "announcement_{$i}_text" ) );
		if ( '' !== $text ) {
			$messages[] = array(
				'text' => $text,
				'link' => (string) ci_mod( "announcement_{$i}_link" ),
			);
		}
	}
	return $messages;
}

/**
 * Current cart item count (0 when WooCommerce isn't loaded).
 */
function ci_cart_count() {
	if ( function_exists( 'WC' ) && WC()->cart ) {
		return (int) WC()->cart->get_cart_contents_count();
	}
	return 0;
}

/**
 * Payment brand mark (38×24). Simplified, theme-drawn marks.
 *
 * @param string $key Icon key from ci_payment_icon_choices().
 */
function ci_payment_icon( $key ) {
	$labels = ci_payment_icon_choices();
	if ( ! isset( $labels[ $key ] ) ) {
		return '';
	}
	$frame = '<rect x=".5" y=".5" width="37" height="23" rx="3" fill="#fff" stroke="#d9d9d9"/>';
	$text  = function ( $str, $fill, $size = 8, $extra = '' ) {
		return sprintf(
			'<text x="19" y="15.5" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="%s" fill="%s" %s>%s</text>',
			$size,
			$fill,
			$extra,
			$str
		);
	};

	switch ( $key ) {
		case 'amex':
			$body = '<rect width="38" height="24" rx="3" fill="#016fd0"/>' . $text( 'AMEX', '#fff', 9 );
			break;
		case 'apple_pay':
			$body = '<rect x=".5" y=".5" width="37" height="23" rx="3" fill="#fff" stroke="#000"/>'
				. '<path d="M10.6 8.6c.4-.5.7-1.1.6-1.8-.6 0-1.3.4-1.7.9-.4.4-.7 1.1-.6 1.7.6.1 1.3-.3 1.7-.8zm.6.9c-.9-.1-1.7.5-2.2.5-.4 0-1.1-.5-1.9-.5-1 0-1.8.6-2.3 1.4-1 1.7-.3 4.3.7 5.7.5.7 1 1.5 1.8 1.4.7 0 1-.5 1.8-.5.9 0 1.1.5 1.9.5s1.3-.7 1.8-1.4c.6-.8.8-1.6.8-1.6s-1.6-.6-1.6-2.4c0-1.5 1.2-2.2 1.3-2.3-.7-1-1.8-1.1-2.1-1.1z"/>'
				. '<text x="24" y="16" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="8.5" fill="#000">Pay</text>';
			break;
		case 'diners':
			$body = $frame . '<circle cx="19" cy="12" r="7" fill="none" stroke="#0079be" stroke-width="1.6"/><path d="M17 7.6v8.8M21 7.6v8.8" stroke="#0079be" stroke-width="1.4"/>';
			break;
		case 'discover':
			$body = $frame . '<text x="16" y="14" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="5" fill="#231f20">DISC</text><circle cx="26.2" cy="12.3" r="2.4" fill="#f48120"/><text x="32" y="14" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="5" fill="#231f20">VER</text>';
			break;
		case 'google_pay':
			$body = $frame . '<text x="12" y="15.5" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="9" fill="#4285f4">G</text><text x="24" y="15.5" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="8.5" fill="#5f6368">Pay</text>';
			break;
		case 'jcb':
			$body = $frame
				. '<rect x="8" y="5" width="7" height="14" rx="2" fill="#0e4c96"/><rect x="15.5" y="5" width="7" height="14" rx="2" fill="#e21836"/><rect x="23" y="5" width="7" height="14" rx="2" fill="#00a14b"/>'
				. '<text x="19" y="14.5" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="6" fill="#fff" letter-spacing="2.3">JCB</text>';
			break;
		case 'mastercard':
			$body = '<rect width="38" height="24" rx="3" fill="#252525"/><circle cx="15" cy="12" r="7" fill="#eb001b"/><circle cx="23" cy="12" r="7" fill="#f79e1b"/><path d="M19 6.5a7 7 0 010 11 7 7 0 010-11z" fill="#ff5f00"/>';
			break;
		case 'visa':
			$body = '<rect width="38" height="24" rx="3" fill="#1434cb"/>' . $text( 'VISA', '#fff', 9.5, 'font-style="italic"' );
			break;
		case 'paypal':
			$body = $frame . $text( 'PayPal', '#003087', 8, 'font-style="italic"' );
			break;
		case 'klarna':
			$body = '<rect width="38" height="24" rx="3" fill="#ffb3c7"/>' . $text( 'Klarna.', '#0a0b09', 7.5 );
			break;
		default:
			$body = '';
	}

	return sprintf(
		'<svg viewBox="0 0 38 24" role="img" aria-label="%s" xmlns="http://www.w3.org/2000/svg">%s</svg>',
		esc_attr( $labels[ $key ] ),
		$body
	);
}
