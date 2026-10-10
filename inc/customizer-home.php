<?php
/**
 * Customizer → Homepage. Section order follows the reference templates/index.json:
 * slideshow → featured collections → testimonials → background image + text.
 * Content defaults are empty; tools/seed.sh fills in the store's content.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_HERO_SLOTS        = 3;
const CI_TILE_SLOTS        = 8;

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

function ci_sanitize_align( $value ) {
	return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : 'center';
}

function ci_sanitize_rating( $value ) {
	return min( 5, max( 1, absint( $value ) ) );
}

add_filter(
	'ci_defaults',
	function ( $d ) {
		$d += array(
			'home_hero_enable'            => true,
			'home_hero_height'            => 750,
			'home_hero_title_size'        => 80,
			'home_hero_autoplay'          => 7,
			'home_hero_parallax'          => true,
			'home_tiles_enable'           => true,
			'home_tiles_title'            => '',
			'home_tiles_per_row'          => 4,
			'home_testimonials_enable'    => true,
			'home_testimonials_title'     => '',
			'home_testimonials_count'     => 8,
			'home_testimonials_min'       => 5,
			'home_testimonials_photos'    => true,
			'home_testimonials_verified'  => false,
			'home_story_enable'           => true,
			'home_story_subtitle'         => '',
			'home_story_title'            => '',
			'home_story_text'             => '',
			'home_story_btn_label'        => '',
			'home_story_btn_url'          => '',
			'home_story_image'            => 0,
			'home_story_focal'            => 'center center',
			'home_story_height'           => 550,
			'home_story_layout'           => 'left',
			'home_story_parallax'         => true,
		);
		for ( $i = 1; $i <= CI_HERO_SLOTS; $i++ ) {
			$d += array(
				"home_hero_{$i}_image"        => 0,
				"home_hero_{$i}_image_mobile" => 0,
				"home_hero_{$i}_eyebrow"      => '',
				"home_hero_{$i}_title"        => '',
				"home_hero_{$i}_text"         => '',
				"home_hero_{$i}_btn1_label"   => '',
				"home_hero_{$i}_btn1_url"     => '',
				"home_hero_{$i}_btn2_label"   => '',
				"home_hero_{$i}_btn2_url"     => '',
				"home_hero_{$i}_focal"        => 'center center',
				"home_hero_{$i}_align"        => 'center',
				"home_hero_{$i}_overlay"      => 0,
			);
		}
		for ( $i = 1; $i <= CI_TILE_SLOTS; $i++ ) {
			$d += array(
				"home_tile_{$i}_cat"   => 0,
				"home_tile_{$i}_title" => '',
				"home_tile_{$i}_image" => 0,
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
		$check = fn( $id, $section, $label ) => $add( $id, $section, $label, 'checkbox', 'ci_sanitize_checkbox' );
		$px    = fn( $min, $max ) => array( 'input_attrs' => array( 'min' => $min, 'max' => $max ) );

		$wp_customize->add_panel(
			'ci_home',
			array(
				'title'       => __( 'Homepage', 'customimpulse' ),
				'priority'    => 31,
				'description' => __( 'Sections appear in this order on the front page.', 'customimpulse' ),
			)
		);

		/* Hero slideshow */
		$wp_customize->add_section( 'ci_home_hero', array( 'title' => __( 'Hero slideshow', 'customimpulse' ), 'panel' => 'ci_home' ) );
		$check( 'home_hero_enable', 'ci_home_hero', __( 'Show hero', 'customimpulse' ) );
		$add( 'home_hero_height', 'ci_home_hero', __( 'Height on desktop (px)', 'customimpulse' ), 'number', 'absint', $px( 300, 1200 ) );
		$add( 'home_hero_title_size', 'ci_home_hero', __( 'Title size on desktop (px, mobile is half)', 'customimpulse' ), 'number', 'absint', $px( 30, 140 ) );
		$add( 'home_hero_autoplay', 'ci_home_hero', __( 'Seconds per slide (0 = no autoplay)', 'customimpulse' ), 'number', 'absint', $px( 0, 30 ) );
		$check( 'home_hero_parallax', 'ci_home_hero', __( 'Parallax on desktop', 'customimpulse' ) );
		for ( $i = 1; $i <= CI_HERO_SLOTS; $i++ ) {
			/* translators: %d: slide number */
			$n = fn( $label ) => sprintf( $label, $i );
			$image( "home_hero_{$i}_image", 'ci_home_hero', $n( __( 'Slide %d: image', 'customimpulse' ) ), $i > 1 ? __( 'Slides without an image are skipped.', 'customimpulse' ) : '' );
			$image( "home_hero_{$i}_image_mobile", 'ci_home_hero', $n( __( 'Slide %d: mobile image (optional)', 'customimpulse' ) ) );
			$add( "home_hero_{$i}_eyebrow", 'ci_home_hero', $n( __( 'Slide %d: small text above the title', 'customimpulse' ) ) );
			$add( "home_hero_{$i}_title", 'ci_home_hero', $n( __( 'Slide %d: title', 'customimpulse' ) ) );
			$add( "home_hero_{$i}_text", 'ci_home_hero', $n( __( 'Slide %d: text under the title', 'customimpulse' ) ) );
			$add( "home_hero_{$i}_btn1_label", 'ci_home_hero', $n( __( 'Slide %d: button 1 label', 'customimpulse' ) ) );
			$add( "home_hero_{$i}_btn1_url", 'ci_home_hero', $n( __( 'Slide %d: button 1 link', 'customimpulse' ) ), 'url', 'esc_url_raw' );
			$add( "home_hero_{$i}_btn2_label", 'ci_home_hero', $n( __( 'Slide %d: button 2 label', 'customimpulse' ) ) );
			$add( "home_hero_{$i}_btn2_url", 'ci_home_hero', $n( __( 'Slide %d: button 2 link', 'customimpulse' ) ), 'url', 'esc_url_raw' );
			$add( "home_hero_{$i}_focal", 'ci_home_hero', $n( __( 'Slide %d: image focal point', 'customimpulse' ) ), 'select', 'ci_sanitize_focal', array( 'choices' => ci_focal_choices() ) );
			$add(
				"home_hero_{$i}_align",
				'ci_home_hero',
				$n( __( 'Slide %d: text alignment', 'customimpulse' ) ),
				'select',
				'ci_sanitize_align',
				array(
					'choices' => array(
						'center' => __( 'Center', 'customimpulse' ),
						'left'   => __( 'Left', 'customimpulse' ),
						'right'  => __( 'Right', 'customimpulse' ),
					),
				)
			);
			$add( "home_hero_{$i}_overlay", 'ci_home_hero', $n( __( 'Slide %d: dark overlay (%%)', 'customimpulse' ) ), 'number', 'absint', $px( 0, 90 ) );
		}

		/* Collection tiles */
		$cats = array( 0 => __( '— Select a category —', 'customimpulse' ) );
		if ( taxonomy_exists( 'product_cat' ) ) {
			foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $term ) {
				$cats[ $term->term_id ] = $term->name;
			}
		}
		$wp_customize->add_section(
			'ci_home_tiles',
			array(
				'title'       => __( 'Collection tiles', 'customimpulse' ),
				'panel'       => 'ci_home',
				'description' => __( 'Tile images default to the category thumbnail (Products → Categories).', 'customimpulse' ),
			)
		);
		$check( 'home_tiles_enable', 'ci_home_tiles', __( 'Show collection tiles', 'customimpulse' ) );
		$add( 'home_tiles_title', 'ci_home_tiles', __( 'Section title', 'customimpulse' ) );
		$add( 'home_tiles_per_row', 'ci_home_tiles', __( 'Tiles per row on desktop', 'customimpulse' ), 'select', 'absint', array( 'choices' => array( 2 => '2', 3 => '3', 4 => '4', 5 => '5' ) ) );
		for ( $i = 1; $i <= CI_TILE_SLOTS; $i++ ) {
			/* translators: %d: tile number */
			$n = fn( $label ) => sprintf( $label, $i );
			$add( "home_tile_{$i}_cat", 'ci_home_tiles', $n( __( 'Tile %d: category', 'customimpulse' ) ), 'select', 'absint', array( 'choices' => $cats ) );
			$add( "home_tile_{$i}_title", 'ci_home_tiles', $n( __( 'Tile %d: label (optional, defaults to the category name)', 'customimpulse' ) ) );
			$image( "home_tile_{$i}_image", 'ci_home_tiles', $n( __( 'Tile %d: image (optional)', 'customimpulse' ) ) );
		}

		/* Testimonials */
		$wp_customize->add_section(
			'ci_home_testimonials',
			array(
				'title'       => __( 'Testimonials', 'customimpulse' ),
				'panel'       => 'ci_home',
				'description' => __( 'Shows real product reviews (newest first). Reviews are collected with Customer Reviews for WooCommerce.', 'customimpulse' ),
			)
		);
		$check( 'home_testimonials_enable', 'ci_home_testimonials', __( 'Show testimonials', 'customimpulse' ) );
		$add( 'home_testimonials_title', 'ci_home_testimonials', __( 'Section title', 'customimpulse' ) );
		$add( 'home_testimonials_count', 'ci_home_testimonials', __( 'Number of reviews', 'customimpulse' ), 'number', 'absint', $px( 1, 12 ) );
		$add(
			'home_testimonials_min',
			'ci_home_testimonials',
			__( 'Minimum rating', 'customimpulse' ),
			'select',
			'ci_sanitize_rating',
			array( 'choices' => array( 5 => '★★★★★', 4 => '★★★★ +', 3 => '★★★ +', 2 => '★★ +', 1 => '★ +' ) )
		);
		$check( 'home_testimonials_photos', 'ci_home_testimonials', __( 'Only reviews with photos', 'customimpulse' ) );
		$check( 'home_testimonials_verified', 'ci_home_testimonials', __( 'Only verified buyers', 'customimpulse' ) );

		/* Image with text */
		$wp_customize->add_section( 'ci_home_story', array( 'title' => __( 'Image with text', 'customimpulse' ), 'panel' => 'ci_home' ) );
		$check( 'home_story_enable', 'ci_home_story', __( 'Show image with text', 'customimpulse' ) );
		$image( 'home_story_image', 'ci_home_story', __( 'Background image', 'customimpulse' ) );
		$add( 'home_story_subtitle', 'ci_home_story', __( 'Subtitle', 'customimpulse' ) );
		$add( 'home_story_title', 'ci_home_story', __( 'Title', 'customimpulse' ) );
		$add( 'home_story_text', 'ci_home_story', __( 'Text (HTML allowed)', 'customimpulse' ), 'textarea', 'wp_kses_post' );
		$add( 'home_story_btn_label', 'ci_home_story', __( 'Button label (optional)', 'customimpulse' ) );
		$add( 'home_story_btn_url', 'ci_home_story', __( 'Button link', 'customimpulse' ), 'url', 'esc_url_raw' );
		$add( 'home_story_focal', 'ci_home_story', __( 'Image focal point', 'customimpulse' ), 'select', 'ci_sanitize_focal', array( 'choices' => ci_focal_choices() ) );
		$add( 'home_story_height', 'ci_home_story', __( 'Height on desktop (px)', 'customimpulse' ), 'number', 'absint', $px( 300, 1000 ) );
		$add(
			'home_story_layout',
			'ci_home_story',
			__( 'Text box position', 'customimpulse' ),
			'select',
			'ci_sanitize_align',
			array(
				'choices' => array(
					'left'   => __( 'Left', 'customimpulse' ),
					'center' => __( 'Center', 'customimpulse' ),
					'right'  => __( 'Right', 'customimpulse' ),
				),
			)
		);
		$check( 'home_story_parallax', 'ci_home_story', __( 'Parallax on desktop', 'customimpulse' ) );
	}
);

/* ---------------------------------------------------------------------------
 * Data helpers for the templates
 * ------------------------------------------------------------------------ */

/**
 * Hero slides that have an image.
 *
 * @return array<int,array<string,mixed>>
 */
function ci_home_slides() {
	$slides = array();
	for ( $i = 1; $i <= CI_HERO_SLOTS; $i++ ) {
		$image = absint( ci_mod( "home_hero_{$i}_image" ) );
		if ( ! $image ) {
			continue;
		}
		$slides[] = array(
			'image'        => $image,
			'image_mobile' => absint( ci_mod( "home_hero_{$i}_image_mobile" ) ),
			'eyebrow'      => (string) ci_mod( "home_hero_{$i}_eyebrow" ),
			'title'        => (string) ci_mod( "home_hero_{$i}_title" ),
			'text'         => (string) ci_mod( "home_hero_{$i}_text" ),
			'buttons'      => array_filter(
				array(
					array( 'label' => (string) ci_mod( "home_hero_{$i}_btn1_label" ), 'url' => (string) ci_mod( "home_hero_{$i}_btn1_url" ) ),
					array( 'label' => (string) ci_mod( "home_hero_{$i}_btn2_label" ), 'url' => (string) ci_mod( "home_hero_{$i}_btn2_url" ) ),
				),
				fn( $b ) => '' !== $b['label'] && '' !== $b['url']
			),
			'focal'        => ci_sanitize_focal( ci_mod( "home_hero_{$i}_focal" ) ),
			'align'        => ci_sanitize_align( ci_mod( "home_hero_{$i}_align" ) ),
			'overlay'      => min( 90, absint( ci_mod( "home_hero_{$i}_overlay" ) ) ),
		);
	}
	return $slides;
}

/**
 * Collection tiles with a valid category.
 *
 * @return array<int,array{url:string,label:string,image:int}>
 */
function ci_home_tiles() {
	$tiles = array();
	for ( $i = 1; $i <= CI_TILE_SLOTS; $i++ ) {
		$term = get_term( absint( ci_mod( "home_tile_{$i}_cat" ) ), 'product_cat' );
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$image = absint( ci_mod( "home_tile_{$i}_image" ) );
		if ( ! $image ) {
			$image = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
		}
		$label   = trim( (string) ci_mod( "home_tile_{$i}_title" ) );
		$tiles[] = array(
			'url'   => get_term_link( $term ),
			'label' => '' !== $label ? $label : $term->name,
			'image' => $image,
		);
	}
	return $tiles;
}

/**
 * Homepage testimonials: real product reviews, newest first, filtered by the Customizer settings.
 * Cached; the cache is invalidated whenever a review changes.
 *
 * @return array<int,array{text:string,stars:int,image:int,image_url:string,author:string,info:string,url:string}>
 */
function ci_home_testimonials() {
	if ( ! function_exists( 'ci_review_photos' ) ) {
		return array();
	}
	$args = array(
		'count'    => min( 12, max( 1, absint( ci_mod( 'home_testimonials_count' ) ) ) ),
		'min'      => ci_sanitize_rating( ci_mod( 'home_testimonials_min' ) ),
		'photos'   => (bool) ci_mod( 'home_testimonials_photos' ),
		'verified' => (bool) ci_mod( 'home_testimonials_verified' ),
	);
	$key    = 'ci_home_reviews_' . md5( wp_json_encode( $args ) . get_option( 'ci_reviews_cache_version', 1 ) );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$meta = array(
		'relation' => 'AND',
		array(
			'key'     => 'rating',
			'value'   => $args['min'],
			'compare' => '>=',
			'type'    => 'NUMERIC',
		),
	);
	if ( $args['photos'] ) {
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => 'ivole_review_image2', 'compare' => 'EXISTS' ),
			array( 'key' => 'ivole_review_image', 'compare' => 'EXISTS' ),
		);
	}
	if ( $args['verified'] ) {
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => 'verified', 'value' => '1' ),
			array( 'key' => 'ivole_order', 'compare' => 'EXISTS' ),
		);
	}

	$items = array();
	foreach ( get_comments(
		array(
			'type'        => 'review',
			'status'      => 'approve',
			'post_status' => 'publish',
			'parent'      => 0,
			'number'      => $args['count'],
			'orderby'     => 'comment_date_gmt',
			'order'       => 'DESC',
			'meta_query'  => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	) as $review ) {
		$photos  = ci_review_photos( $review->comment_ID, 'thumbnail' );
		$items[] = array(
			'text'      => wp_trim_words( $review->comment_content, 40, '…' ),
			'stars'     => (int) get_comment_meta( $review->comment_ID, 'rating', true ),
			'image'     => $photos ? (int) $photos[0]['id'] : 0,
			'image_url' => $photos ? $photos[0]['thumb'] : '',
			'author'    => get_comment_author( $review ),
			'info'      => get_the_title( $review->comment_post_ID ),
			'url'       => get_permalink( $review->comment_post_ID ) . '#review-' . $review->comment_ID,
		);
	}
	set_transient( $key, $items, 12 * HOUR_IN_SECONDS );
	return $items;
}

/* Any review change invalidates the homepage testimonials cache. */
$ci_bump_reviews_cache = function () {
	update_option( 'ci_reviews_cache_version', (int) get_option( 'ci_reviews_cache_version', 1 ) + 1, false );
};
foreach ( array( 'wp_insert_comment', 'edit_comment', 'deleted_comment', 'transition_comment_status', 'added_comment_meta', 'updated_comment_meta' ) as $ci_hook ) {
	add_action( $ci_hook, $ci_bump_reviews_cache );
}
