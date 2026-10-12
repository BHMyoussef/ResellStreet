<?php
/**
 * WooCommerce integration via hooks. Template overrides are a last resort (listed in CLAUDE.md).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/* Page wrappers: header.php already opens <main>, so WC only needs the page-width container. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
add_action(
	'woocommerce_before_main_content',
	function () {
		echo '<div class="page-width wc-page">';
	},
	10
);
add_action(
	'woocommerce_after_main_content',
	function () {
		echo '</div>';
	},
	10
);

/* No sidebar and no breadcrumbs (both off on the reference store). */
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

/* ---------------------------------------------------------------------------
 * Collection (product archive) page
 * ------------------------------------------------------------------------ */

/**
 * True on product archive pages that use the collection layout.
 */
function ci_is_collection() {
	return is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) );
}

/* Collection title is off on the reference store: the <h1> stays for SEO/screen readers, hidden visually. */
add_filter(
	'body_class',
	function ( $classes ) {
		// The search results page always shows its title ("Search results: …").
		if ( function_exists( 'is_shop' ) && ci_is_collection() && ! is_search() && ! ci_mod( 'collection_title_enable' ) ) {
			$classes[] = 'collection--title-hidden';
		}
		return $classes;
	}
);

/* Promo banner above the grid (Customizer → Collection page). */
add_action(
	'woocommerce_before_main_content',
	function () {
		if ( ci_is_collection() && ! is_search() ) {
			get_template_part( 'template-parts/collection/banner' );
		}
	},
	15
);

/* "9 products" instead of "Showing all 9 results"; sorting only when enabled. */
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
add_action(
	'woocommerce_before_shop_loop',
	function () {
		$total = (int) wc_get_loop_prop( 'total' );
		echo '<div class="collection-toolbar">';
		printf(
			'<p class="collection-count">%s</p>',
			/* translators: %d: number of products */
			esc_html( sprintf( _n( '%d product', '%d products', $total, 'customimpulse' ), $total ) )
		);
		if ( ci_mod( 'collection_sort_enable' ) ) {
			woocommerce_catalog_ordering();
		}
		echo '</div>';
	},
	20
);

/* Pagination arrows: theme chevrons instead of text arrows. */
add_filter(
	'woocommerce_pagination_args',
	function ( $args ) {
		$args['prev_text'] = ci_icon( 'chevron-left' ) . '<span class="visually-hidden">' . esc_html__( 'Previous page', 'customimpulse' ) . '</span>';
		$args['next_text'] = ci_icon( 'chevron-right' ) . '<span class="visually-hidden">' . esc_html__( 'Next page', 'customimpulse' ) . '</span>';
		return $args;
	}
);

/* ---------------------------------------------------------------------------
 * Product card (used by every product loop: archives, search, related, shortcodes)
 * ------------------------------------------------------------------------ */

remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

add_action( 'woocommerce_before_shop_loop_item_title', 'ci_loop_media', 10 );
add_action( 'woocommerce_shop_loop_item_title', 'ci_loop_meta_open', 1 );
add_action( 'woocommerce_shop_loop_item_title', 'ci_loop_brand', 5 );
add_action( 'woocommerce_shop_loop_item_title', 'ci_loop_title', 10 );
add_action( 'woocommerce_after_shop_loop_item_title', 'ci_loop_price', 10 );
add_action( 'woocommerce_after_shop_loop_item_title', 'ci_loop_meta_close', 100 );

/**
 * Card image(s) + status tag. The first cards of the main catalog loop are above the fold on mobile:
 * load them eagerly and give the first one high fetch priority (LCP).
 */
function ci_loop_media() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	static $main_index = 0;
	$is_main = ci_is_collection() && '' === wc_get_loop_prop( 'name' );
	if ( $is_main ) {
		++$main_index;
	}

	$img_args = array(
		'class'   => 'product-card__img',
		'sizes'   => '(min-width: 769px) calc((min(100vw, 1500px) - 124px) / 3), calc(50vw - 26px)',
		'loading' => $is_main && $main_index <= 4 ? 'eager' : 'lazy',
	);
	if ( $is_main && 1 === $main_index ) {
		$img_args['fetchpriority'] = 'high';
	}

	$image_id = $product->get_image_id();
	if ( $image_id && ! get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) {
		$img_args['alt'] = $product->get_name();
	}

	echo '<div class="product-card__media">';
	echo $image_id
		? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, $img_args )
		: wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'product-card__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput

	if ( ci_mod( 'card_hover_image' ) ) {
		$gallery = $product->get_gallery_image_ids();
		if ( $gallery ) {
			echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput
				$gallery[0],
				'woocommerce_thumbnail',
				false,
				array(
					'class'       => 'product-card__img product-card__img--hover',
					'sizes'       => $img_args['sizes'],
					'loading'     => 'lazy',
					'alt'         => '',
					'aria-hidden' => 'true',
				)
			);
		}
	}

	// Loop prop ci_badge: 'new' (homepage New Arrivals) shows "New" instead of "Sale"; 'recent' = "New" only for products
	// published in the last 30 days (ci_new_badge_days), else nothing; 'none' only "Sold out".
	if ( ! $product->is_in_stock() ) {
		echo '<span class="product-card__tag product-card__tag--sold-out">' . esc_html__( 'Sold out', 'customimpulse' ) . '</span>';
	} elseif ( 'new' === wc_get_loop_prop( 'ci_badge' ) || ( 'recent' === wc_get_loop_prop( 'ci_badge' ) && ci_product_is_recent( $product ) ) ) {
		echo '<span class="product-card__tag product-card__tag--new">' . esc_html__( 'New', 'customimpulse' ) . '</span>';
	} elseif ( $product->is_on_sale() && ! in_array( wc_get_loop_prop( 'ci_badge' ), array( 'none', 'recent' ), true ) ) {
		echo '<span class="product-card__tag">' . esc_html__( 'Sale', 'customimpulse' ) . '</span>';
	}
	echo '</div>';
}

function ci_loop_meta_open() {
	echo '<div class="product-card__meta">';
}

function ci_loop_meta_close() {
	echo '</div>';
}

/**
 * Published recently enough for a "New" badge (ci_new_badge_days filter, default 30).
 *
 * @param WC_Product $product Product.
 */
function ci_product_is_recent( WC_Product $product ) {
	$created = $product->get_date_created();
	return $created && $created->getTimestamp() > time() - DAY_IN_SECONDS * (int) apply_filters( 'ci_new_badge_days', 30 );
}

/**
 * Brand name above the card title (first WooCommerce brand).
 */
function ci_loop_brand() {
	$brand = ci_product_brand( get_the_ID() );
	if ( $brand ) {
		echo '<p class="product-card__brand">' . esc_html( $brand->name ) . '</p>';
	}
}

/**
 * First product_brand term of a product (null when brands are off or none is set).
 *
 * @param int $product_id Product ID.
 */
function ci_product_brand( $product_id ) {
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return null;
	}
	$terms = get_the_terms( $product_id, 'product_brand' );
	return is_array( $terms ) && $terms ? $terms[0] : null;
}

function ci_loop_title() {
	echo '<h2 class="product-card__title woocommerce-loop-product__title">' . esc_html( get_the_title() ) . '</h2>';
}

function ci_loop_price() {
	global $product;
	if ( $product instanceof WC_Product ) {
		echo '<div class="product-card__price">' . ci_price_html( $product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from wc_price().
	}
}

/**
 * Price markup: [from] [compare-at] price [Save X%]. Shared by cards and the product page.
 *
 * @param WC_Product $product Product.
 */
function ci_price_html( WC_Product $product ) {
	if ( $product->is_type( 'variable' ) ) {
		$price   = (float) $product->get_variation_price( 'min', true );
		$regular = (float) $product->get_variation_regular_price( 'min', true );
		$from    = $price !== (float) $product->get_variation_price( 'max', true );
	} elseif ( ! $product->is_type( 'grouped' ) && '' !== $product->get_price() ) {
		$price   = (float) wc_get_price_to_display( $product );
		$regular = '' === $product->get_regular_price()
			? $price
			: (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
		$from    = false;
	} else {
		return $product->get_price_html(); // Grouped, or no price set.
	}

	$on_sale = $product->is_on_sale() && $regular > $price;
	$html    = '';

	if ( $on_sale ) {
		$html .= sprintf(
			'<s class="price__compare"><span class="visually-hidden">%s</span>%s</s> ',
			esc_html__( 'Regular price', 'customimpulse' ),
			wc_price( $regular )
		);
	}

	$current = wc_price( $price );
	if ( $from ) {
		/* translators: %s: lowest price */
		$current = sprintf( esc_html__( 'from %s', 'customimpulse' ), $current );
	}
	$html .= sprintf(
		'<span class="price__current">%s%s</span>',
		$on_sale ? '<span class="visually-hidden">' . esc_html__( 'Sale price', 'customimpulse' ) . '</span>' : '',
		$current
	);

	$savings_type = ci_mod( 'savings_type' );
	if ( $on_sale && 'none' !== $savings_type ) {
		$saving = 'amount' === $savings_type
			? wc_price( $regular - $price )
			: esc_html( round( ( $regular - $price ) / $regular * 100 ) . '%' );
		/* translators: %s: amount or percentage saved */
		$html .= ' <span class="price__savings">' . sprintf( esc_html__( 'Save %s', 'customimpulse' ), $saving ) . '</span>';
	}

	return $html;
}
