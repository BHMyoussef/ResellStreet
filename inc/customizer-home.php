<?php
/**
 * Customizer → Homepage (design: reference/homepage-v2). One Customizer section per homepage section,
 * in page order: hero → new arrivals → image with text → brands → banner → trending → video reviews → trust row.
 * Content defaults are empty; tools/seed.sh fills in the store's content.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_BRAND_SLOTS    = 12;
const CI_TRENDING_TABS  = 4;
const CI_TRUST_ITEMS    = 4;
const CI_HOME_SECTIONS  = array( 'hero', 'new-arrivals', 'split', 'brands', 'banner', 'trending', 'video-reviews', 'trust' );

function ci_focal_choices() {
	return array(
		'center center' => __( 'Center', 'customimpulse' ),
		'top center'    => __( 'Top', 'customimpulse' ),
		'bottom center' => __( 'Bottom', 'customimpulse' ),
		'center left'   => __( 'Left', 'customimpulse' ),
		'center right'  => __( 'Right', 'customimpulse' ),
	);
}

function ci_sanitize_focal( $value ) {
	return array_key_exists( $value, ci_focal_choices() ) ? $value : 'center center';
}

function ci_sanitize_side( $value ) {
	return 'right' === $value ? 'right' : 'left';
}

/** Icons offered for the trust row (all in ci_icon()). */
function ci_trust_icon_choices() {
	return array(
		'truck'  => __( 'Delivery truck', 'customimpulse' ),
		'lock'   => __( 'Padlock', 'customimpulse' ),
		'return' => __( 'Return arrow', 'customimpulse' ),
		'shield' => __( 'Shield', 'customimpulse' ),
		'check'  => __( 'Check mark', 'customimpulse' ),
		'gift'   => __( 'Gift', 'customimpulse' ),
		'chat'   => __( 'Chat bubble', 'customimpulse' ),
	);
}

function ci_sanitize_trust_icon( $value ) {
	return array_key_exists( $value, ci_trust_icon_choices() ) ? $value : 'check';
}

add_filter(
	'ci_defaults',
	function ( $d ) {
		$d += array(
			// Hero
			'home_hero_enable'         => true,
			'home_hero_image'          => 0,
			'home_hero_image_mobile'   => 0,
			'home_hero_focal'          => 'center center',
			'home_hero_overlay'        => 75,
			'home_hero_height'         => 750,
			'home_hero_height_mobile'  => 600,
			'home_hero_eyebrow'        => '',
			'home_hero_title'          => '',
			'home_hero_text'           => '',
			'home_hero_btn1_label'     => '',
			'home_hero_btn1_url'       => '',
			'home_hero_btn2_label'     => '',
			'home_hero_btn2_url'       => '',
			// New arrivals
			'home_new_enable'          => true,
			'home_new_eyebrow'         => '',
			'home_new_title'           => '',
			'home_new_count'           => 8,
			'home_new_link_label'      => '',
			'home_new_link_url'        => '',
			// Image with text
			'home_split_enable'        => true,
			'home_split_image'         => 0,
			'home_split_focal'         => 'center center',
			'home_split_side'          => 'left',
			'home_split_eyebrow'       => '',
			'home_split_title'         => '',
			'home_split_text'          => '',
			'home_split_btn_label'     => '',
			'home_split_btn_url'       => '',
			// Brands
			'home_brands_enable'       => true,
			'home_brands_eyebrow'      => '',
			'home_brands_title'        => '',
			'home_brands_link_label'   => '',
			'home_brands_link_url'     => '',
			// Banner
			'home_banner_enable'       => true,
			'home_banner_image'        => 0,
			'home_banner_image_mobile' => 0,
			'home_banner_focal'        => 'center center',
			'home_banner_overlay'      => 80,
			'home_banner_height'       => 635,
			'home_banner_eyebrow'      => '',
			'home_banner_title'        => '',
			'home_banner_text'         => '',
			'home_banner_btn_label'    => '',
			'home_banner_btn_url'      => '',
			// Trending
			'home_trending_enable'     => true,
			'home_trending_eyebrow'    => '',
			'home_trending_title'      => '',
			'home_trending_tag'        => 'trending',
			'home_trending_count'      => 8,
			'home_trending_btn_label'  => '',
			'home_trending_btn_url'    => '',
			// Video reviews
			'home_videos_enable'       => true,
			'home_videos_eyebrow'      => '',
			'home_videos_title'        => '',
			'home_videos_count'        => 10,
			'home_videos_summary'      => true,
			// Trust row
			'home_trust_enable'        => true,
		);
		for ( $i = 1; $i <= CI_BRAND_SLOTS; $i++ ) {
			$d += array( "home_brand_{$i}" => 0 );
		}
		for ( $i = 1; $i <= CI_TRENDING_TABS; $i++ ) {
			$d += array(
				"home_trending_{$i}_label" => '',
				"home_trending_{$i}_cat"   => 0,
			);
		}
		$icons = array( 'truck', 'lock', 'return', 'shield' );
		for ( $i = 1; $i <= CI_TRUST_ITEMS; $i++ ) {
			$d += array(
				"home_trust_{$i}_icon"  => $icons[ $i - 1 ],
				"home_trust_{$i}_title" => '',
				"home_trust_{$i}_text"  => '',
			);
		}
		return $d;
	}
);

add_action(
	'customize_register',
	function ( WP_Customize_Manager $wp_customize ) {
		$d = ci_defaults();

		$setting = function ( $id, $sanitize ) use ( $wp_customize, $d ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $d[ $id ] ?? '',
					'sanitize_callback' => $sanitize,
				)
			);
		};
		$add = function ( $id, $section, $label, $type = 'text', $sanitize = 'sanitize_text_field', $args = array() ) use ( $wp_customize, $setting ) {
			$setting( $id, $sanitize );
			$wp_customize->add_control( $id, array_merge( array( 'label' => $label, 'section' => $section, 'type' => $type ), $args ) );
		};
		$image = function ( $id, $section, $label, $description = '' ) use ( $wp_customize, $setting ) {
			$setting( $id, 'absint' );
			$wp_customize->add_control(
				new WP_Customize_Media_Control(
					$wp_customize,
					$id,
					array(
						'label'       => $label,
						'section'     => $section,
						'mime_type'   => 'image',
						'description' => $description,
					)
				)
			);
		};
		$check   = fn( $id, $section, $label ) => $add( $id, $section, $label, 'checkbox', 'ci_sanitize_checkbox' );
		$range   = fn( $min, $max ) => array( 'input_attrs' => array( 'min' => $min, 'max' => $max ) );
		$section = function ( $id, $title, $description = '' ) use ( $wp_customize ) {
			$wp_customize->add_section(
				$id,
				array(
					'title'       => $title,
					'panel'       => 'ci_home',
					'description' => $description,
				)
			);
		};
		$heading = function ( $s, $prefix ) use ( $add ) {
			$add( "{$prefix}_eyebrow", $s, __( 'Small text above the title', 'customimpulse' ) );
			$add( "{$prefix}_title", $s, __( 'Title', 'customimpulse' ) );
		};
		$focal   = fn( $id, $s ) => $add( $id, $s, __( 'Image focal point', 'customimpulse' ), 'select', 'ci_sanitize_focal', array( 'choices' => ci_focal_choices() ) );

		$wp_customize->add_panel(
			'ci_home',
			array(
				'title'       => __( 'Homepage', 'customimpulse' ),
				'priority'    => 31,
				'description' => __( 'Sections appear in this order on the front page. A section without content is hidden.', 'customimpulse' ),
			)
		);

		/* Hero */
		$s = 'ci_home_hero';
		$section( $s, __( 'Hero', 'customimpulse' ) );
		$check( 'home_hero_enable', $s, __( 'Show hero', 'customimpulse' ) );
		$image( 'home_hero_image', $s, __( 'Image', 'customimpulse' ), __( 'Landscape, at least 2400px wide.', 'customimpulse' ) );
		$image( 'home_hero_image_mobile', $s, __( 'Mobile image (optional)', 'customimpulse' ), __( 'Portrait crop for phones; otherwise the image above is used.', 'customimpulse' ) );
		$focal( 'home_hero_focal', $s );
		$add( 'home_hero_overlay', $s, __( 'Dark overlay (%)', 'customimpulse' ), 'number', 'absint', $range( 0, 90 ) );
		$add( 'home_hero_height', $s, __( 'Height on desktop (px)', 'customimpulse' ), 'number', 'absint', $range( 400, 1200 ) );
		$add( 'home_hero_height_mobile', $s, __( 'Height on phones (px)', 'customimpulse' ), 'number', 'absint', $range( 300, 900 ) );
		$heading( $s, 'home_hero' );
		$add( 'home_hero_text', $s, __( 'Text', 'customimpulse' ), 'textarea' );
		$add( 'home_hero_btn1_label', $s, __( 'Button 1 label', 'customimpulse' ) );
		$add( 'home_hero_btn1_url', $s, __( 'Button 1 link', 'customimpulse' ), 'url', 'esc_url_raw' );
		$add( 'home_hero_btn2_label', $s, __( 'Button 2 label', 'customimpulse' ) );
		$add( 'home_hero_btn2_url', $s, __( 'Button 2 link', 'customimpulse' ), 'url', 'esc_url_raw' );

		/* New arrivals */
		$s = 'ci_home_new';
		$section( $s, __( 'New arrivals', 'customimpulse' ), __( 'Latest published products.', 'customimpulse' ) );
		$check( 'home_new_enable', $s, __( 'Show new arrivals', 'customimpulse' ) );
		$heading( $s, 'home_new' );
		$add( 'home_new_count', $s, __( 'Number of products', 'customimpulse' ), 'number', 'absint', $range( 2, 16 ) );
		$add( 'home_new_link_label', $s, __( 'Link label (e.g. View all)', 'customimpulse' ) );
		$add( 'home_new_link_url', $s, __( 'Link URL (empty = shop, newest first)', 'customimpulse' ), 'url', 'esc_url_raw' );

		/* Image with text */
		$s = 'ci_home_split';
		$section( $s, __( 'Image with text', 'customimpulse' ) );
		$check( 'home_split_enable', $s, __( 'Show image with text', 'customimpulse' ) );
		$image( 'home_split_image', $s, __( 'Image', 'customimpulse' ), __( 'Portrait 4:5.', 'customimpulse' ) );
		$focal( 'home_split_focal', $s );
		$add(
			'home_split_side',
			$s,
			__( 'Image side on desktop', 'customimpulse' ),
			'select',
			'ci_sanitize_side',
			array(
				'choices' => array(
					'left'  => __( 'Left', 'customimpulse' ),
					'right' => __( 'Right', 'customimpulse' ),
				),
			)
		);
		$heading( $s, 'home_split' );
		$add( 'home_split_text', $s, __( 'Text (bold allowed: <strong>…</strong>)', 'customimpulse' ), 'textarea', 'wp_kses_post' );
		$add( 'home_split_btn_label', $s, __( 'Button label', 'customimpulse' ) );
		$add( 'home_split_btn_url', $s, __( 'Button link', 'customimpulse' ), 'url', 'esc_url_raw' );

		/* Brands */
		$brands = array( 0 => __( '— None —', 'customimpulse' ) );
		if ( taxonomy_exists( 'product_brand' ) ) {
			foreach ( get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) ) as $term ) {
				$brands[ $term->term_id ] = $term->name;
			}
		}
		$s = 'ci_home_brands';
		$section( $s, __( 'Shop by brand', 'customimpulse' ), __( 'Logos are the brand thumbnails (Products → Brands). Tiles show in slot order; empty slots are skipped. All slots empty = brands with the most products.', 'customimpulse' ) );
		$check( 'home_brands_enable', $s, __( 'Show brands', 'customimpulse' ) );
		$heading( $s, 'home_brands' );
		$add( 'home_brands_link_label', $s, __( 'Link label (e.g. All brands)', 'customimpulse' ) );
		$add( 'home_brands_link_url', $s, __( 'Link URL', 'customimpulse' ), 'url', 'esc_url_raw' );
		for ( $i = 1; $i <= CI_BRAND_SLOTS; $i++ ) {
			/* translators: %d: slot number */
			$add( "home_brand_{$i}", $s, sprintf( __( 'Brand %d', 'customimpulse' ), $i ), 'select', 'absint', array( 'choices' => $brands ) );
		}

		/* Banner */
		$s = 'ci_home_banner';
		$section( $s, __( 'Full-width banner', 'customimpulse' ) );
		$check( 'home_banner_enable', $s, __( 'Show banner', 'customimpulse' ) );
		$image( 'home_banner_image', $s, __( 'Image', 'customimpulse' ) );
		$image( 'home_banner_image_mobile', $s, __( 'Mobile image (optional)', 'customimpulse' ) );
		$focal( 'home_banner_focal', $s );
		$add( 'home_banner_overlay', $s, __( 'Dark overlay (%)', 'customimpulse' ), 'number', 'absint', $range( 0, 90 ) );
		$add( 'home_banner_height', $s, __( 'Height on desktop (px)', 'customimpulse' ), 'number', 'absint', $range( 300, 1000 ) );
		$heading( $s, 'home_banner' );
		$add( 'home_banner_text', $s, __( 'Text', 'customimpulse' ), 'textarea' );
		$add( 'home_banner_btn_label', $s, __( 'Button label', 'customimpulse' ) );
		$add( 'home_banner_btn_url', $s, __( 'Button link', 'customimpulse' ), 'url', 'esc_url_raw' );

		/* Trending */
		$cats = array( 0 => __( '— None —', 'customimpulse' ) );
		if ( taxonomy_exists( 'product_cat' ) ) {
			foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $term ) {
				$cats[ $term->term_id ] = $term->name;
			}
		}
		$s = 'ci_home_trending';
		$section( $s, __( 'Trending styles', 'customimpulse' ), __( 'Products with the tag below, one tab per category. Tabs without products are hidden; the tab bar shows from 2 tabs.', 'customimpulse' ) );
		$check( 'home_trending_enable', $s, __( 'Show trending styles', 'customimpulse' ) );
		$heading( $s, 'home_trending' );
		$add( 'home_trending_tag', $s, __( 'Product tag (slug)', 'customimpulse' ), 'text', 'sanitize_title' );
		$add( 'home_trending_count', $s, __( 'Products per tab', 'customimpulse' ), 'number', 'absint', $range( 2, 16 ) );
		for ( $i = 1; $i <= CI_TRENDING_TABS; $i++ ) {
			/* translators: %d: tab number */
			$add( "home_trending_{$i}_label", $s, sprintf( __( 'Tab %d: label', 'customimpulse' ), $i ) );
			/* translators: %d: tab number */
			$add( "home_trending_{$i}_cat", $s, sprintf( __( 'Tab %d: category', 'customimpulse' ), $i ), 'select', 'absint', array( 'choices' => $cats ) );
		}
		$add( 'home_trending_btn_label', $s, __( 'Button label', 'customimpulse' ) );
		$add( 'home_trending_btn_url', $s, __( 'Button link (empty = the tag page)', 'customimpulse' ), 'url', 'esc_url_raw' );

		/* Video reviews */
		$s = 'ci_home_videos';
		$section( $s, __( 'Video reviews', 'customimpulse' ), __( 'Videos are managed in Products → Video reviews (the Order field sets the order). The rating summary uses all approved product reviews.', 'customimpulse' ) );
		$check( 'home_videos_enable', $s, __( 'Show video reviews', 'customimpulse' ) );
		$heading( $s, 'home_videos' );
		$add( 'home_videos_count', $s, __( 'Number of videos', 'customimpulse' ), 'number', 'absint', $range( 1, 20 ) );
		$check( 'home_videos_summary', $s, __( 'Show the rating summary (average + number of reviews)', 'customimpulse' ) );

		/* Trust row */
		$s = 'ci_home_trust';
		$section( $s, __( 'Trust row', 'customimpulse' ) );
		$check( 'home_trust_enable', $s, __( 'Show trust row', 'customimpulse' ) );
		for ( $i = 1; $i <= CI_TRUST_ITEMS; $i++ ) {
			/* translators: %d: item number */
			$add( "home_trust_{$i}_icon", $s, sprintf( __( 'Item %d: icon', 'customimpulse' ), $i ), 'select', 'ci_sanitize_trust_icon', array( 'choices' => ci_trust_icon_choices() ) );
			/* translators: %d: item number */
			$add( "home_trust_{$i}_title", $s, sprintf( __( 'Item %d: title', 'customimpulse' ), $i ) );
			/* translators: %d: item number */
			$add( "home_trust_{$i}_text", $s, sprintf( __( 'Item %d: text', 'customimpulse' ), $i ) );
		}
	}
);
