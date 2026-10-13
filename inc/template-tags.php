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
		'zoom'         => '<circle cx="27" cy="27" r="17"/><path d="M39 39l17 17M19 27h16M27 19v16"/>',
		'ruler'        => '<path d="M6 42L42 6l16 16-36 36z"/><path d="M16 32l6 6M23 25l4 4M30 18l6 6M37 11l4 4"/>',
		// Product page selling points.
		'truck'        => '<path d="M4 16h34v28H4z"/><path d="M38 26h12l10 10v8H38"/><circle cx="15" cy="48" r="5"/><circle cx="49" cy="48" r="5"/>',
		'lock'         => '<path d="M14 28h36v28H14z"/><path d="M22 28v-8a10 10 0 0120 0v8M32 38v8"/>',
		'return'       => '<path d="M14 24h30a12 12 0 010 24H22"/><path d="M23 13L12 24l11 11"/>',
		'gift'         => '<path d="M10 24h44v10H10zM14 34h36v22H14zM32 24v32"/><path d="M32 24c-3-9-15-11-15-4 0 4 8 4 15 4zm0 0c3-9 15-11 15-4 0 4-8 4-15 4z"/>',
		'check'        => '<path d="M13 33l12 12 26-26"/>',
		'shield'       => '<path d="M32 6l22 8v16c0 14-9 24-22 28C19 54 10 44 10 30V14z"/><path d="M22 32l7 7 13-13"/>',
		'chat'         => '<path d="M8 12h48v32H28L16 54V44H8z"/>',
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
 * Logo: the uploaded logo (Customizer → Site Identity; footer: Footer → Footer logo, a light version for the
 * dark footer), else the site title as text.
 *
 * @param string $context 'header' | 'footer'.
 */
function ci_logo( $context = 'header' ) {
	$name     = get_bloginfo( 'name', 'display' );
	$footer   = 'footer' === $context;
	$sizes    = $footer
		? sprintf( '%dpx', absint( ci_mod( 'footer_logo_height' ) ) * 10 )
		: sprintf( '(min-width: 769px) %dpx, %dpx', absint( ci_mod( 'logo_width_desktop' ) ), absint( ci_mod( 'logo_width_mobile' ) ) );
	$attrs    = array(
		'class'    => "custom-logo custom-logo--{$context}",
		'alt'      => $name,
		'loading'  => $footer ? 'lazy' : 'eager',
		'decoding' => 'async',
		'sizes'    => $sizes,
	);
	$logo_id  = $footer ? (int) ci_mod( 'footer_logo' ) : (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$img = wp_get_attachment_image( $logo_id, 'full', false, $attrs );
		if ( $img ) {
			return $img;
		}
	}
	return sprintf( '<span class="%s">%s</span>', $footer ? 'footer__logo-text' : 'site-header__logo-text', esc_html( $name ) );
}

/**
 * Social networks with a URL in Customizer → Footer, in display order.
 *
 * @return array<string,array{label:string,url:string}>
 */
function ci_social_links() {
	$links = array();
	foreach ( ci_social_networks() as $key => $label ) {
		$url = (string) ci_mod( "social_{$key}" );
		if ( '' !== $url ) {
			$links[ $key ] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
	}
	return $links;
}

function ci_social_networks() {
	return array(
		'instagram' => 'Instagram',
		'tiktok'    => 'TikTok',
		'facebook'  => 'Facebook',
		'youtube'   => 'YouTube',
		'x'         => 'X',
		'pinterest' => 'Pinterest',
	);
}

/**
 * Social network icon (24-unit grid, filled / outlined in currentColor).
 *
 * @param string $name Network key.
 */
function ci_social_icon( $name ) {
	$paths = array(
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.3" cy="6.7" r="1.2"/>',
		'tiktok'    => '<path d="M16.5 3c.3 2.4 1.9 4 4.5 4.2v3.2c-1.7 0-3.2-.5-4.5-1.4v6.3A6.2 6.2 0 1 1 11 9.2v3.3a3 3 0 1 0 2.3 2.9V3z"/>',
		'facebook'  => '<path d="M13.5 22v-8.2h2.8l.4-3.3h-3.2V8.4c0-.9.3-1.6 1.6-1.6h1.7V3.9a23 23 0 0 0-2.5-.1c-2.5 0-4.2 1.5-4.2 4.3v2.4H7.3v3.3h2.8V22z"/>',
		'youtube'   => '<path fill-rule="evenodd" d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3z"/>',
		'x'         => '<path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z"/>',
		'pinterest' => '<path d="M12 2a10 10 0 0 0-3.6 19.3c-.1-.8-.2-2 0-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.4 1.8-2.4.9 0 1.3.6 1.3 1.4 0 .9-.5 2.1-.8 3.3-.2 1 .5 1.8 1.5 1.8 1.8 0 3.1-1.9 3.1-4.6 0-2.4-1.7-4.1-4.2-4.1-2.9 0-4.5 2.1-4.5 4.4 0 .9.3 1.8.8 2.3l.1.4-.3 1.1c0 .2-.2.3-.4.2-1.3-.6-2.1-2.5-2.1-4 0-3.3 2.4-6.3 6.9-6.3 3.6 0 6.4 2.6 6.4 6 0 3.6-2.3 6.5-5.4 6.5-1 0-2-.5-2.4-1.2l-.6 2.5c-.2.9-.9 2.1-1.3 2.8A10 10 0 1 0 12 2z"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="icon icon--fill icon--social" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
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

/**
 * A srcset string without the candidates wider than $max px (the narrowest one is kept if none fit).
 *
 * @param string $srcset Srcset attribute value.
 * @param int    $max    Widest candidate to keep.
 */
function ci_srcset_upto( $srcset, $max ) {
	$keep = array();
	foreach ( explode( ',', (string) $srcset ) as $candidate ) {
		$parts = preg_split( '/\s+/', trim( $candidate ) );
		if ( 2 === count( $parts ) ) {
			$keep[ (int) $parts[1] ] = $parts[0];
		}
	}
	ksort( $keep );
	$small = array_filter( $keep, fn( $w ) => $w <= $max, ARRAY_FILTER_USE_KEY );
	$small = $small ? $small : array_slice( $keep, 0, 1, true );
	$out   = array();
	foreach ( $small as $w => $url ) {
		$out[] = "{$url} {$w}w";
	}
	return implode( ', ', $out );
}

/**
 * Full-width image on phones: srcset limited to files up to $max px wide.
 * A 3x phone asks for ~1200px on a 100vw image and would otherwise pick the 1600px+
 * original (≈800 KB); past ~2x the difference isn't visible on photos.
 *
 * @param int $id  Attachment ID.
 * @param int $max Widest candidate to keep.
 */
function ci_mobile_srcset( $id, $max = 1024 ) {
	return ci_srcset_upto( wp_get_attachment_image_srcset( $id, 'full' ), $max );
}
