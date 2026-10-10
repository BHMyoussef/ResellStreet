<?php
/**
 * Search: header overlay with predictive product results + product search results page.
 *
 * - Overlay: <dialog> opened from the header search icon (falls back to the results page without JS).
 * - Predictive results: lightweight REST endpoint `ci/v1/search?q=` (name, link, thumbnail, price).
 * - Results page: WooCommerce product archive (collection layout) with the title and a search form.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_SEARCH_LIMIT = 6;

/**
 * Product search query args shared by the predictive endpoint (respects catalog visibility).
 *
 * @param string $term  Search terms.
 * @param int    $limit Max results.
 */
function ci_search_query_args( $term, $limit ) {
	$exclude = array( 'exclude-from-search' );
	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
		$exclude[] = 'outofstock';
	}
	return array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		's'                   => $term,
		'posts_per_page'      => $limit,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => false,
		'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => $exclude,
				'operator' => 'NOT IN',
			),
		),
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ci/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'callback'            => function ( WP_REST_Request $request ) {
					$term  = trim( (string) $request['q'] );
					$items = array();
					$total = 0;
					if ( mb_strlen( $term ) >= 2 ) {
						$query = new WP_Query( ci_search_query_args( $term, CI_SEARCH_LIMIT ) );
						$total = (int) $query->found_posts;
						foreach ( $query->posts as $post ) {
							$product = wc_get_product( $post );
							if ( ! $product ) {
								continue;
							}
							$image   = $product->get_image_id();
							$items[] = array(
								'name'  => wp_strip_all_tags( $product->get_name() ),
								'url'   => $product->get_permalink(),
								'image' => $image ? (string) wp_get_attachment_image_url( $image, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' ),
								'price' => ci_price_html( $product ),
							);
						}
					}
					$response = rest_ensure_response(
						array(
							'items' => $items,
							'total' => $total,
							'url'   => add_query_arg(
								array(
									's'         => rawurlencode( $term ),
									'post_type' => 'product',
								),
								home_url( '/' )
							),
						)
					);
					$response->header( 'Cache-Control', 'public, max-age=300' );
					return $response;
				},
			)
		);
	}
);

/* Plain WordPress searches (?s= without post_type) go to the product search: this store's content is its products. */
add_action(
	'template_redirect',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect.
		if ( is_search() && ! is_admin() && empty( $_GET['post_type'] ) ) {
			wp_safe_redirect( add_query_arg( 'post_type', 'product' ) );
			exit;
		}
	}
);

/* Results page: search form under the title (also shown when nothing was found). */
add_action(
	'woocommerce_archive_description',
	function () {
		if ( is_search() ) {
			echo '<div class="search-page__form">';
			get_search_form();
			echo '</div>';
		}
	},
	20
);

/* No results: a friendlier message than WooCommerce's default. */
add_action(
	'woocommerce_no_products_found',
	function () {
		if ( ! is_search() ) {
			return;
		}
		remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
		printf(
			'<div class="search-page__empty"><p>%s</p><p><a class="btn" href="%s">%s</a></p></div>',
			/* translators: %s: search terms */
			esc_html( sprintf( __( 'No products found for “%s”. Check the spelling or try a more general term.', 'customimpulse' ), get_search_query( false ) ) ),
			esc_url( wc_get_page_permalink( 'shop' ) ),
			esc_html__( 'Browse all products', 'customimpulse' )
		);
	},
	5
);

/* Assets: the overlay script is tiny and loads everywhere the header search icon is. */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( function_exists( 'ci_is_minimal_checkout' ) && ci_is_minimal_checkout() ) {
			return; // No header search on the checkout.
		}
		ci_enqueue_script( 'search' );
		wp_localize_script(
			'ci-search',
			'ciSearch',
			array(
				'endpoint' => esc_url_raw( rest_url( 'ci/v1/search' ) ),
				/* translators: %s: search terms */
				'viewAll'  => __( 'View all results for “%s”', 'customimpulse' ),
				/* translators: %s: search terms */
				'none'     => __( 'No products found for “%s”', 'customimpulse' ),
				'products' => __( 'Products', 'customimpulse' ),
				/* translators: %d: number of products found (1) */
				'countOne' => __( '%d product found', 'customimpulse' ),
				/* translators: %d: number of products found */
				'count'    => __( '%d products found', 'customimpulse' ),
				'error'    => __( 'Search is unavailable right now. Press Enter to see all results.', 'customimpulse' ),
			)
		);
	}
);
