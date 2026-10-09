<?php
/**
 * Customizer settings. All store content lives here (or in WP menus), never in templates.
 * Defaults are deliberately content-free; real content is seeded with tools/seed.sh.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_ANNOUNCEMENT_SLOTS = 3;

/**
 * Payment icons available in the footer, in display order.
 *
 * @return array<string,string> key => label
 */
function ci_payment_icon_choices() {
	return array(
		'amex'       => 'American Express',
		'apple_pay'  => 'Apple Pay',
		'diners'     => 'Diners Club',
		'discover'   => 'Discover',
		'google_pay' => 'Google Pay',
		'jcb'        => 'JCB',
		'mastercard' => 'Mastercard',
		'visa'       => 'Visa',
		'paypal'     => 'PayPal',
		'klarna'     => 'Klarna',
	);
}

/**
 * Default values for every theme mod.
 *
 * @return array<string,mixed>
 */
function ci_defaults() {
	static $defaults = null;
	if ( null !== $defaults ) {
		return $defaults;
	}

	$defaults = array(
		// Header.
		'logo_width_desktop'    => 150,
		'logo_width_mobile'     => 110,
		// Announcement bar.
		'announcement_enable'   => true,
		'announcement_interval' => 5,
		// Footer.
		'newsletter_title'      => '',
		'newsletter_text'       => '',
		'newsletter_shortcode'  => '',
		'newsletter_action'     => '',
		'newsletter_field'      => 'email',
		'contact_title'         => '',
		'contact_text'          => '',
		'footer_logo_height'    => 40,
		'payment_icons_enable'  => true,
		'copyright_text'        => '',
	);
	for ( $i = 1; $i <= CI_ANNOUNCEMENT_SLOTS; $i++ ) {
		$defaults[ "announcement_{$i}_text" ] = '';
		$defaults[ "announcement_{$i}_link" ] = '';
	}
	foreach ( array_keys( ci_payment_icon_choices() ) as $key ) {
		$defaults[ "payment_{$key}" ] = ! in_array( $key, array( 'paypal', 'klarna' ), true );
	}
	return $defaults;
}

/**
 * Theme mod with the theme default applied.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function ci_mod( $key ) {
	$defaults = ci_defaults();
	return get_theme_mod( $key, $defaults[ $key ] ?? '' );
}

function ci_sanitize_checkbox( $value ) {
	return (bool) $value;
}

add_action(
	'customize_register',
	function ( WP_Customize_Manager $wp_customize ) {
		$d = ci_defaults();

		$add = function ( $id, $section, $label, $type = 'text', $sanitize = 'sanitize_text_field', $args = array() ) use ( $wp_customize, $d ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $d[ $id ] ?? '',
					'sanitize_callback' => $sanitize,
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				$id,
				array_merge(
					array(
						'label'   => $label,
						'section' => $section,
						'type'    => $type,
					),
					$args
				)
			);
		};

		$wp_customize->add_panel(
			'ci_theme',
			array(
				'title'    => __( 'CustomImpulse theme', 'customimpulse' ),
				'priority' => 30,
			)
		);

		// Logo sizes sit with the core logo control.
		$px = array( 'input_attrs' => array( 'min' => 40, 'max' => 400, 'step' => 1 ) );
		$add( 'logo_width_desktop', 'title_tagline', __( 'Logo width on desktop (px)', 'customimpulse' ), 'number', 'absint', $px );
		$add( 'logo_width_mobile', 'title_tagline', __( 'Logo width on mobile (px)', 'customimpulse' ), 'number', 'absint', $px );

		// Announcement bar.
		$wp_customize->add_section(
			'ci_announcement',
			array(
				'title' => __( 'Announcement bar', 'customimpulse' ),
				'panel' => 'ci_theme',
			)
		);
		$add( 'announcement_enable', 'ci_announcement', __( 'Show announcement bar', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add(
			'announcement_interval',
			'ci_announcement',
			__( 'Seconds per message', 'customimpulse' ),
			'number',
			'absint',
			array( 'input_attrs' => array( 'min' => 2, 'max' => 30 ) )
		);
		for ( $i = 1; $i <= CI_ANNOUNCEMENT_SLOTS; $i++ ) {
			/* translators: %d: message number */
			$add( "announcement_{$i}_text", 'ci_announcement', sprintf( __( 'Message %d', 'customimpulse' ), $i ) );
			/* translators: %d: message number */
			$add( "announcement_{$i}_link", 'ci_announcement', sprintf( __( 'Message %d link (optional)', 'customimpulse' ), $i ), 'url', 'esc_url_raw' );
		}

		// Footer.
		$wp_customize->add_section(
			'ci_footer',
			array(
				'title'       => __( 'Footer', 'customimpulse' ),
				'panel'       => 'ci_theme',
				'description' => __( 'The footer link column uses the "Footer menu" location (Menus).', 'customimpulse' ),
			)
		);
		$add( 'newsletter_title', 'ci_footer', __( 'Newsletter title', 'customimpulse' ) );
		$add( 'newsletter_text', 'ci_footer', __( 'Newsletter text', 'customimpulse' ), 'textarea', 'wp_kses_post' );
		$add(
			'newsletter_shortcode',
			'ci_footer',
			__( 'Newsletter form shortcode', 'customimpulse' ),
			'text',
			'sanitize_text_field',
			array( 'description' => __( 'e.g. a Klaviyo, Mailchimp or MailPoet form shortcode. Takes priority over the fields below.', 'customimpulse' ) )
		);
		$add(
			'newsletter_action',
			'ci_footer',
			__( 'Newsletter form action URL', 'customimpulse' ),
			'url',
			'esc_url_raw',
			array( 'description' => __( 'Used only without a shortcode. Leave both empty to hide the form.', 'customimpulse' ) )
		);
		$add( 'newsletter_field', 'ci_footer', __( 'Email field name', 'customimpulse' ), 'text', 'sanitize_key' );
		$add( 'contact_title', 'ci_footer', __( 'Contact column title', 'customimpulse' ) );
		$add( 'contact_text', 'ci_footer', __( 'Contact column text (HTML allowed)', 'customimpulse' ), 'textarea', 'wp_kses_post' );
		$add(
			'footer_logo_height',
			'ci_footer',
			__( 'Footer logo height (px)', 'customimpulse' ),
			'number',
			'absint',
			array( 'input_attrs' => array( 'min' => 20, 'max' => 200 ) )
		);
		$add( 'payment_icons_enable', 'ci_footer', __( 'Show payment icons', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		foreach ( ci_payment_icon_choices() as $key => $label ) {
			$add( "payment_{$key}", 'ci_footer', $label, 'checkbox', 'ci_sanitize_checkbox' );
		}
		$add(
			'copyright_text',
			'ci_footer',
			__( 'Copyright text', 'customimpulse' ),
			'text',
			'sanitize_text_field',
			array( 'description' => __( 'Leave empty for "© {year} {site name}".', 'customimpulse' ) )
		);
	}
);

/**
 * Customizer-driven CSS variables.
 */
function ci_customizer_css() {
	return sprintf(
		':root{--logo-w-mobile:%dpx;--logo-w-desktop:%dpx;--footer-logo-h:%dpx}',
		absint( ci_mod( 'logo_width_mobile' ) ),
		absint( ci_mod( 'logo_width_desktop' ) ),
		absint( ci_mod( 'footer_logo_height' ) )
	);
}
