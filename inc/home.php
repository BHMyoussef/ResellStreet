<?php
/**
 * Homepage data: product grids (the regular WooCommerce card), brands, trending tabs (+ REST for tab switches),
 * store rating summary. Settings: inc/customizer-home.php. Templates: template-parts/home/*.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Section heading: eyebrow + title (+ optional link on the right).
 *
 * @param string $prefix Customizer prefix (home_new, home_brands…).
 * @param array  $args   id (title id), link_label, link_url, center.
 */
function ci_home_heading( $prefix, array $args = array() ) {
	$eyebrow = (string) ci_mod( "{$prefix}_eyebrow" );
	$title   = (string) ci_mod( "{$prefix}_title" );
	if ( '' === $eyebrow && '' === $title ) {
		return;
	}
	$class = 'home-heading' . ( empty( $args['center'] ) ? '' : ' home-heading--center' );
	?>
	<div class="<?php echo esc_attr( $class ); ?>">
		<div class="home-heading__text">
			<?php if ( $eyebrow ) : ?>
				<p class="home-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<?php if ( $title ) : ?>
				<h2 class="home-heading__title" id="<?php echo esc_attr( $args['id'] ?? '' ); ?>"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $args['link_label'] ) && ! empty( $args['link_url'] ) ) : ?>
			<a class="home-heading__link" href="<?php echo esc_url( $args['link_url'] ); ?>"><?php echo esc_html( $args['link_label'] ); ?></a>
		<?php endif; ?>
		<?php if ( ! empty( $args['extra'] ) ) : ?>
			<?php echo $args['extra']; // phpcs:ignore WordPress.Security.EscapeOutput -- built by the caller with escaping. ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Product cards (the shop's card markup) for a query.
 *
 * @param array  $query  WP_Query args (post_type/status added).
 * @param string $class  Extra class on the <ul>.
 * @param array  $props  WooCommerce loop props (e.g. ci_badge => new).
 * @return string HTML ('' when no products).
 */
function ci_home_products( array $query, $class = '', array $props = array() ) {
	// Hidden products (and sold-out ones when the store hides them) never show, like in the shop.
	$hidden = array( 'exclude-from-catalog' );
	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
		$hidden[] = 'outofstock';
	}
	$tax   = array_merge(
		array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => $hidden,
				'operator' => 'NOT IN',
			),
		),
		$query['tax_query'] ?? array()
	);
	$query = new WP_Query(
		array_merge(
			$query,
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'tax_query'           => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
	if ( ! $query->have_posts() ) {
		return '';
	}
	wc_setup_loop( array_merge( array( 'name' => 'ci_home', 'columns' => 4 ), $props ) );
	ob_start();
	echo '<ul class="products home-products ' . esc_attr( $class ) . '">';
	while ( $query->have_posts() ) {
		$query->the_post();
		wc_get_template_part( 'content', 'product' );
	}
	echo '</ul>';
	wp_reset_postdata();
	wc_reset_loop();
	return (string) ob_get_clean();
}

/**
 * Brand tiles: Customizer slots in order, or (all empty) the brands with the most products.
 *
 * @return WP_Term[]
 */
function ci_home_brands() {
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return array();
	}
	$ids = array();
	for ( $i = 1; $i <= CI_BRAND_SLOTS; $i++ ) {
		$id = absint( ci_mod( "home_brand_{$i}" ) );
		if ( $id && ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
	}
	if ( $ids ) {
		$terms = get_terms( array( 'taxonomy' => 'product_brand', 'include' => $ids, 'orderby' => 'include', 'hide_empty' => false ) );
	} else {
		$terms = get_terms( array( 'taxonomy' => 'product_brand', 'orderby' => 'count', 'order' => 'DESC', 'number' => CI_BRAND_SLOTS, 'hide_empty' => false ) );
	}
	return is_array( $terms ) ? $terms : array();
}

/**
 * Trending tabs that have products: label, category, product query args.
 *
 * @return array<int,array{label:string,cat:int,slug:string}>
 */
function ci_home_trending_tabs() {
	$tag  = sanitize_title( (string) ci_mod( 'home_trending_tag' ) );
	$tabs = array();
	for ( $i = 1; $i <= CI_TRENDING_TABS; $i++ ) {
		$term = get_term( absint( ci_mod( "home_trending_{$i}_cat" ) ), 'product_cat' );
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$label = trim( (string) ci_mod( "home_trending_{$i}_label" ) );
		$tab   = array(
			'label' => '' !== $label ? $label : $term->name,
			'cat'   => $term->term_id,
			'slug'  => $term->slug,
		);
		if ( ci_home_trending_query( $tab['cat'], $tag, 1, true ) ) {
			$tabs[] = $tab;
		}
	}
	return $tabs;
}

/**
 * Trending products of one category: query args, or (with $exists) whether there is at least one.
 *
 * @param int    $cat    product_cat term ID.
 * @param string $tag    product_tag slug ('' = no tag filter).
 * @param int    $count  Number of products.
 * @param bool   $exists Return a bool instead of the args.
 * @return array|bool
 */
function ci_home_trending_query( $cat, $tag, $count, $exists = false ) {
	$tax = array(
		array(
			'taxonomy' => 'product_cat',
			'terms'    => array( (int) $cat ),
		),
	);
	if ( '' !== $tag ) {
		$tax[] = array(
			'taxonomy' => 'product_tag',
			'field'    => 'slug',
			'terms'    => array( $tag ),
		);
	}
	$args = array(
		'posts_per_page' => $count,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'tax_query'      => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery
	);
	if ( ! $exists ) {
		return $args;
	}
	$q = new WP_Query(
		array_merge(
			$args,
			array(
				'post_type'     => 'product',
				'post_status'   => 'publish',
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		)
	);
	return $q->have_posts();
}

/**
 * Trending grid HTML for one tab.
 *
 * @param int $cat product_cat term ID.
 */
function ci_home_trending_html( $cat ) {
	$count = min( 16, max( 2, absint( ci_mod( 'home_trending_count' ) ) ) );
	return ci_home_products( ci_home_trending_query( $cat, sanitize_title( (string) ci_mod( 'home_trending_tag' ) ), $count ), 'home-products--grid' );
}

/* Tab switches: GET ci/v1/trending/<cat> → { html } (public, cacheable; only configured tabs). */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ci/v1',
			'/trending/(?P<cat>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array( 'cat' => array( 'sanitize_callback' => 'absint' ) ),
				'callback'            => function ( WP_REST_Request $request ) {
					$cat     = (int) $request['cat'];
					$allowed = wp_list_pluck( ci_home_trending_tabs(), 'cat' );
					if ( ! in_array( $cat, $allowed, true ) ) {
						return new WP_Error( 'ci_not_found', 'Unknown tab', array( 'status' => 404 ) );
					}
					$response = rest_ensure_response( array( 'html' => ci_home_trending_html( $cat ) ) );
					$response->header( 'Cache-Control', 'public, max-age=300' );
					return $response;
				},
			)
		);
	}
);

/**
 * Store rating: average + count of all approved product reviews (cached until a review changes).
 *
 * @return array{average:float,count:int}
 */
function ci_store_rating() {
	$key    = 'ci_store_rating_' . get_option( 'ci_reviews_cache_version', 1 );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$row    = $wpdb->get_row(
		"SELECT AVG(cm.meta_value) AS average, COUNT(*) AS total
		FROM {$wpdb->comments} c
		JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rating'
		JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID AND p.post_type = 'product' AND p.post_status = 'publish'
		WHERE c.comment_approved = '1' AND c.comment_type = 'review' AND cm.meta_value > 0"
	);
	$rating = array(
		'average' => $row ? round( (float) $row->average, 1 ) : 0.0,
		'count'   => $row ? (int) $row->total : 0,
	);
	set_transient( $key, $rating, 12 * HOUR_IN_SECONDS );
	return $rating;
}

/* Any review change invalidates the cached review data (rating summary, product review sections). */
$ci_bump_reviews_cache = function () {
	update_option( 'ci_reviews_cache_version', (int) get_option( 'ci_reviews_cache_version', 1 ) + 1, false );
};
foreach ( array( 'wp_insert_comment', 'edit_comment', 'deleted_comment', 'transition_comment_status', 'added_comment_meta', 'updated_comment_meta' ) as $ci_hook ) {
	add_action( $ci_hook, $ci_bump_reviews_cache );
}

/**
 * Full-width image with an optional phone image: <picture> whose phone source never exceeds 1024px wide
 * (otherwise 3× phones pick the 2400px original).
 *
 * @param int    $image  Attachment ID.
 * @param int    $mobile Phone attachment ID (0 = same image).
 * @param string $focal  object-position.
 * @param bool   $lcp    Above the fold: eager + fetchpriority=high (else lazy).
 */
function ci_home_picture( $image, $mobile, $focal, $lcp = false ) {
	$attrs = array(
		'class'   => 'home-media__img',
		'sizes'   => '100vw',
		'loading' => $lcp ? 'eager' : 'lazy',
		'alt'     => '',
		'style'   => 'object-position:' . ci_sanitize_focal( $focal ),
	);
	if ( $lcp ) {
		$attrs['fetchpriority'] = 'high';
		$attrs['decoding']      = 'async';
	}
	return '<picture class="home-media">'
		. '<source media="(max-width: 768px)" srcset="' . esc_attr( ci_mobile_srcset( $mobile ? $mobile : $image ) ) . '" sizes="100vw">'
		. wp_get_attachment_image( $image, 'full', false, $attrs )
		. '</picture>';
}

/* Hero image preload (the LCP): the phone or desktop candidate set, matching the <picture> sources. */
add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() || ! ci_mod( 'home_hero_enable' ) ) {
			return;
		}
		$image = absint( ci_mod( 'home_hero_image' ) );
		if ( ! $image ) {
			return;
		}
		$mobile = absint( ci_mod( 'home_hero_image_mobile' ) );
		printf(
			'<link rel="preload" as="image" fetchpriority="high" media="(max-width: 768px)" imagesrcset="%s" imagesizes="100vw">' . "\n",
			esc_attr( ci_mobile_srcset( $mobile ? $mobile : $image ) )
		);
		printf(
			'<link rel="preload" as="image" fetchpriority="high" media="(min-width: 769px)" imagesrcset="%s" imagesizes="100vw">' . "\n",
			esc_attr( (string) wp_get_attachment_image_srcset( $image, 'full' ) )
		);
	},
	2
);

/**
 * Up to two buttons (label + URL pairs; incomplete pairs skipped).
 *
 * @param array<int,array{0:string,1:string,2:string}> $buttons [label, url, class].
 */
function ci_home_buttons( array $buttons ) {
	$html = '';
	foreach ( $buttons as $b ) {
		if ( '' !== trim( (string) $b[0] ) && '' !== trim( (string) $b[1] ) ) {
			$html .= '<a class="btn btn--home ' . esc_attr( $b[2] ) . '" href="' . esc_url( $b[1] ) . '">' . esc_html( $b[0] ) . '</a>';
		}
	}
	return $html ? '<div class="home-buttons">' . $html . '</div>' : '';
}

/**
 * Brand tiles (homepage "Shop by brand", [ci_brands] page): logo = brand thumbnail, else the name.
 *
 * @param WP_Term[] $terms Brands.
 * @param bool      $lazy  Lazy-load logos.
 */
function ci_brand_tiles( array $terms, $lazy = true ) {
	if ( ! $terms ) {
		return '';
	}
	$html = '<ul class="brand-tiles" role="list">';
	foreach ( $terms as $term ) {
		$logo  = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
		$inner = $logo
			? wp_get_attachment_image(
				$logo,
				'medium',
				false,
				array(
					'class'   => 'brand-tiles__logo',
					'alt'     => $term->name,
					'loading' => $lazy ? 'lazy' : 'eager',
					'sizes'   => '(min-width: 769px) 120px, 100px',
				)
			)
			: '<span class="brand-tiles__name">' . esc_html( $term->name ) . '</span>';
		$html .= '<li><a class="brand-tiles__tile" href="' . esc_url( get_term_link( $term ) ) . '">' . $inner . '</a></li>';
	}
	return $html . '</ul>';
}

/* [ci_brands]: every brand A–Z as tiles (the "All brands" page). */
add_shortcode(
	'ci_brands',
	function () {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return '';
		}
		$terms = get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false, 'orderby' => 'name' ) );
		return ci_brand_tiles( is_array( $terms ) ? $terms : array(), false );
	}
);
