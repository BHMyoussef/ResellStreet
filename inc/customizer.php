<?php
/**
 * Customizer settings. All store content lives here (or in WP menus), never in templates.
 * Defaults are deliberately content-free; real content is seeded with tools/seed.sh.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_ANNOUNCEMENT_SLOTS = 3;
const CI_SALES_POINT_SLOTS = 3;
const CI_PRODUCT_TAB_SLOTS = 3;

/**
 * Icons offered for selling points.
 *
 * @return array<string,string>
 */
function ci_sales_point_icons() {
	return array(
		'truck'  => __( 'Truck', 'customimpulse' ),
		'lock'   => __( 'Lock', 'customimpulse' ),
		'return' => __( 'Return', 'customimpulse' ),
		'gift'   => __( 'Gift', 'customimpulse' ),
		'check'  => __( 'Check mark', 'customimpulse' ),
		'chat'   => __( 'Chat', 'customimpulse' ),
	);
}


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
		// Collection page.
		'collection_title_enable' => false,
		'collection_sort_enable'  => false,
		'card_hover_image'        => true,
		'savings_type'            => 'percent',
		'banner_enable'           => true,
		'banner_heading'          => '',
		'banner_text'             => '',
		'banner_link'             => '',
		'banner_label'            => '',
		'banner_tint'             => '#e95454',
		'banner_tint_opacity'     => 10,
		// Product page.
		'variant_preselect'          => true,
		'product_qty_enable'         => false,
		'product_description_inline' => true,
		'related_enable'             => false,
		'sticky_atc_enable'          => true,
		'stock_status_enable'        => true,
		'stock_low_threshold'        => 10,
		'trust_badges_enable'        => true,
		'trust_badges_title'         => '',
		// Quick shop + cart drawer.
		'quick_shop_enable'          => true,
		'quick_shop_text'            => '',
		'cart_note_text'             => '',
	);
	$point_icons = array( 1 => 'truck', 2 => 'lock', 3 => 'return' );
	for ( $i = 1; $i <= 3; $i++ ) {
		$defaults[ "sales_point_{$i}_icon" ] = $point_icons[ $i ];
		$defaults[ "sales_point_{$i}_text" ] = '';
		$defaults[ "product_tab_{$i}_title" ]   = '';
		$defaults[ "product_tab_{$i}_content" ] = '';
	}
	for ( $i = 1; $i <= CI_ANNOUNCEMENT_SLOTS; $i++ ) {
		$defaults[ "announcement_{$i}_text" ] = '';
		$defaults[ "announcement_{$i}_link" ] = '';
	}
	foreach ( array_keys( ci_payment_icon_choices() ) as $key ) {
		$defaults[ "payment_{$key}" ] = ! in_array( $key, array( 'paypal', 'klarna' ), true );
	}
	// Other modules (homepage…) register their defaults here.
	$defaults = apply_filters( 'ci_defaults', $defaults );
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

function ci_sanitize_sales_point_icon( $value ) {
	return array_key_exists( $value, ci_sales_point_icons() ) ? $value : 'check';
}

function ci_sanitize_savings_type( $value ) {
	return in_array( $value, array( 'percent', 'amount', 'none' ), true ) ? $value : 'percent';
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
		// Collection page.
		$wp_customize->add_section(
			'ci_collection',
			array(
				'title' => __( 'Collection page', 'customimpulse' ),
				'panel' => 'ci_theme',
			)
		);
		$add( 'collection_title_enable', 'ci_collection', __( 'Show collection title', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'collection_sort_enable', 'ci_collection', __( 'Show sorting', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'card_hover_image', 'ci_collection', __( 'Show second image on hover', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add(
			'savings_type',
			'ci_collection',
			__( 'Sale savings', 'customimpulse' ),
			'select',
			'ci_sanitize_savings_type',
			array(
				'choices' => array(
					'percent' => __( 'Percentage (Save 50%)', 'customimpulse' ),
					'amount'  => __( 'Amount (Save £50.00)', 'customimpulse' ),
					'none'    => __( 'Hidden', 'customimpulse' ),
				),
			)
		);
		$add( 'quick_shop_enable', 'ci_collection', __( 'Quick shop button on product cards (desktop)', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add(
			'quick_shop_text',
			'ci_collection',
			__( 'Quick shop button text', 'customimpulse' ),
			'text',
			'sanitize_text_field',
			array( 'description' => __( 'Leave empty for "Shop Now".', 'customimpulse' ) )
		);
		$add( 'banner_enable', 'ci_collection', __( 'Show promo banner', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'banner_heading', 'ci_collection', __( 'Banner heading', 'customimpulse' ) );
		$add( 'banner_text', 'ci_collection', __( 'Banner text', 'customimpulse' ) );
		$add( 'banner_link', 'ci_collection', __( 'Banner link (optional)', 'customimpulse' ), 'url', 'esc_url_raw' );
		$add(
			'banner_label',
			'ci_collection',
			__( 'Banner link label (optional)', 'customimpulse' ),
			'text',
			'sanitize_text_field',
			array( 'description' => __( 'Without a label the whole banner is clickable.', 'customimpulse' ) )
		);
		$wp_customize->add_setting(
			'banner_tint',
			array(
				'default'           => $d['banner_tint'],
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'banner_tint',
				array(
					'label'   => __( 'Banner tint color', 'customimpulse' ),
					'section' => 'ci_collection',
				)
			)
		);
		$add(
			'banner_tint_opacity',
			'ci_collection',
			__( 'Banner tint opacity (%)', 'customimpulse' ),
			'number',
			'absint',
			array( 'input_attrs' => array( 'min' => 0, 'max' => 100 ) )
		);

		// Product page.
		$wp_customize->add_section(
			'ci_product',
			array(
				'title' => __( 'Product page', 'customimpulse' ),
				'panel' => 'ci_theme',
			)
		);
		$add( 'variant_preselect', 'ci_product', __( 'Preselect the first available variant', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'product_qty_enable', 'ci_product', __( 'Show quantity selector', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'product_description_inline', 'ci_product', __( 'Show description under the add to cart button (otherwise as a tab)', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'sticky_atc_enable', 'ci_product', __( 'Sticky add to cart bar on mobile', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'related_enable', 'ci_product', __( 'Show related products', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'stock_status_enable', 'ci_product', __( 'Show stock status', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add(
			'stock_low_threshold',
			'ci_product',
			__( 'Show "Low stock" at or below (units)', 'customimpulse' ),
			'number',
			'absint',
			array( 'input_attrs' => array( 'min' => 0, 'max' => 100 ) )
		);
		for ( $i = 1; $i <= CI_SALES_POINT_SLOTS; $i++ ) {
			/* translators: %d: selling point number */
			$add( "sales_point_{$i}_text", 'ci_product', sprintf( __( 'Selling point %d', 'customimpulse' ), $i ) );
			$add(
				"sales_point_{$i}_icon",
				'ci_product',
				/* translators: %d: selling point number */
				sprintf( __( 'Selling point %d icon', 'customimpulse' ), $i ),
				'select',
				'ci_sanitize_sales_point_icon',
				array( 'choices' => ci_sales_point_icons() )
			);
		}
		$add( 'trust_badges_enable', 'ci_product', __( 'Show trust badges under add to cart (payment icons from Footer settings)', 'customimpulse' ), 'checkbox', 'ci_sanitize_checkbox' );
		$add( 'trust_badges_title', 'ci_product', __( 'Trust badges title (optional)', 'customimpulse' ) );
		for ( $i = 1; $i <= CI_PRODUCT_TAB_SLOTS; $i++ ) {
			/* translators: %d: tab number */
			$add( "product_tab_{$i}_title", 'ci_product', sprintf( __( 'Tab %d title', 'customimpulse' ), $i ) );
			/* translators: %d: tab number */
			$add( "product_tab_{$i}_content", 'ci_product', sprintf( __( 'Tab %d content (HTML allowed)', 'customimpulse' ), $i ), 'textarea', 'wp_kses_post' );
		}

		// Cart drawer.
		$wp_customize->add_section(
			'ci_cart',
			array(
				'title' => __( 'Cart drawer', 'customimpulse' ),
				'panel' => 'ci_theme',
			)
		);
		$add(
			'cart_note_text',
			'ci_cart',
			__( 'Note under the subtotal', 'customimpulse' ),
			'text',
			'sanitize_text_field',
			array( 'description' => __( 'Leave empty for "Shipping, taxes, and discount codes calculated at checkout."', 'customimpulse' ) )
		);

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
