<?php
/**
 * Single product page, built on WooCommerce hooks/filters (no template overrides).
 *
 * Summary order: title → [ci_product_after_title] → divider → price → excerpt → variations (buttons)
 * → selling points + stock → add to cart → [ci_product_after_add_to_cart] (trust badges) → description → tabs.
 * Below the product: [ci_product_reviews] (theme review section, inc/reviews.php), upsells, related (optional).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Summary layout
 * ------------------------------------------------------------------------ */

remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

/* Brand above the title (WooCommerce Brands), linking to the brand page. */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		$brand = $product instanceof WC_Product && function_exists( 'ci_product_brand' ) ? ci_product_brand( $product->get_id() ) : null;
		if ( $brand ) {
			printf( '<a class="product-brand" href="%1$s">%2$s</a>', esc_url( get_term_link( $brand ) ), esc_html( $brand->name ) );
		}
	},
	4
);

/* Slot under the title for review stars (inc/reviews.php: theme stars + count). */
add_action(
	'woocommerce_single_product_summary',
	function () {
		echo '<div class="product-summary__after-title">';
		do_action( 'ci_product_after_title' );
		echo '</div><hr class="product-summary__divider">';
	},
	6
);

add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		echo '<div class="product-price" data-product-price>' . ci_price_html( $product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from wc_price().
	},
	10
);

/* Colour: this product's colour + its other colourways (design v2), before the size buttons. */
add_action( 'woocommerce_single_product_summary', 'ci_product_colourways', 25 );

/* Stock status, right above quantity + add to cart (inside the form). */
add_action( 'woocommerce_before_add_to_cart_button', 'ci_product_selling_points', 5 );

/* Slot after the add to cart form: express checkout slot, trust lines, authenticity box, (payment icons). */
add_action(
	'woocommerce_after_add_to_cart_form',
	function () {
		do_action( 'ci_product_after_add_to_cart' );
	},
	10
);
add_action( 'ci_product_after_add_to_cart', 'ci_product_express_checkout', 2 );
add_action( 'ci_product_after_add_to_cart', 'ci_product_trust_lines', 5 );
add_action( 'ci_product_after_add_to_cart', 'ci_product_authenticity', 8 );
add_action( 'ci_product_after_add_to_cart', 'ci_product_trust_badges', 10 );

/* Short description lives in the "Product details" accordion now. */
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );

/* Accordions under the product (design v2), before the reviews. */
add_action( 'woocommerce_after_single_product_summary', 'ci_product_accordions', 5 );

/* Reviews: full width under the product (like the reference store's review app section); filled by inc/reviews.php. */
add_action(
	'woocommerce_after_single_product_summary',
	function () {
		ob_start();
		do_action( 'ci_product_reviews' );
		$html = trim( (string) ob_get_clean() );
		if ( '' !== $html ) {
			echo '<section class="product-reviews" id="reviews">' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput -- hook output.
		}
	},
	12
);

/* Related products are off on the reference store. */
add_action(
	'wp',
	function () {
		if ( ! ci_mod( 'related_enable' ) ) {
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		}
	}
);

/* No quantity box unless enabled (reference store has none). The input stays in the form with value 1. */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_product() && ! ci_mod( 'product_qty_enable' ) ) {
			$classes[] = 'product--no-qty';
		}
		return $classes;
	}
);

/* ---------------------------------------------------------------------------
 * Variation buttons
 * ------------------------------------------------------------------------ */

/**
 * Compact availability/price map for every variation, independent of WooCommerce's AJAX threshold.
 * Lets the buttons cross out sold-out options and update price/stock even in AJAX mode (> 30 variations).
 *
 * @param WC_Product_Variable $product Product.
 * @return array{attrs:string[],variations:array,prices:string[],threshold:int}
 */
function ci_variation_map( WC_Product_Variable $product ) {
	static $cache = array();
	$id = $product->get_id();
	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}

	$attrs = array();
	foreach ( array_keys( $product->get_variation_attributes() ) as $name ) {
		$attrs[] = 'attribute_' . sanitize_title( $name );
	}

	$prices     = array();
	$variations = array();
	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );
		if ( ! $variation || ! $variation->exists() || 'publish' !== $variation->get_status() ) {
			continue;
		}
		$price_html = ci_price_html( $variation );
		$price_idx  = array_search( $price_html, $prices, true );
		if ( false === $price_idx ) {
			$prices[]  = $price_html;
			$price_idx = count( $prices ) - 1;
		}
		$qty   = $variation->managing_stock() ? (int) $variation->get_stock_quantity() : null;
		$entry = array(
			'i' => $child_id,
			'a' => (object) $variation->get_variation_attributes(),
			's' => (int) ( $variation->is_in_stock() && $variation->is_purchasable() ),
			'p' => $price_idx,
			'q' => $qty,
		);
		// Own image (used by Quick Shop, where WooCommerce's variation script doesn't run).
		$image_id = $variation->get_image_id( 'edit' );
		if ( $image_id && (int) $image_id !== (int) $product->get_image_id() ) {
			$entry['img'] = array(
				'src'    => wp_get_attachment_image_url( $image_id, 'woocommerce_single' ),
				'srcset' => (string) wp_get_attachment_image_srcset( $image_id, 'woocommerce_single' ),
			);
		}
		$variations[] = $entry;
	}

	$cache[ $id ] = array(
		'attrs'      => $attrs,
		'variations' => $variations,
		'prices'     => $prices,
		'threshold'  => absint( ci_mod( 'stock_low_threshold' ) ),
	);
	return $cache[ $id ];
}

/**
 * Attributes of the first in-stock variation (used to preselect options).
 *
 * @param WC_Product_Variable $product Product.
 * @return array<string,string> attribute_x => value
 */
function ci_first_available_attributes( WC_Product_Variable $product ) {
	foreach ( ci_variation_map( $product )['variations'] as $v ) {
		if ( $v['s'] ) {
			return (array) $v['a'];
		}
	}
	return array();
}

add_action(
	'woocommerce_before_variations_form',
	function () {
		global $product;
		if ( ! $product instanceof WC_Product_Variable ) {
			return;
		}
		printf(
			'<script type="application/json" class="ci-variant-data">%s</script>',
			wp_json_encode( ci_variation_map( $product ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE )
		);
	}
);

/* Preselect: product defaults first, otherwise the first in-stock variation (like Shopify). */
add_filter(
	'woocommerce_dropdown_variation_attribute_options_args',
	function ( $args ) {
		if ( ! empty( $args['selected'] ) || ! ci_mod( 'variant_preselect' ) || ! $args['product'] instanceof WC_Product_Variable ) {
			return $args;
		}
		$first = ci_first_available_attributes( $args['product'] );
		$key   = 'attribute_' . sanitize_title( $args['attribute'] );
		if ( ! empty( $first[ $key ] ) ) {
			$args['selected'] = $first[ $key ];
		}
		return $args;
	}
);

/* Keep WC's <select> (plugins rely on it) but visually hidden; render radio buttons that drive it. */
add_filter(
	'woocommerce_dropdown_variation_attribute_options_html',
	function ( $html, $args ) {
		$product = $args['product'] ?? null;
		if ( ! $product instanceof WC_Product_Variable || empty( $args['options'] ) ) {
			return $html;
		}
		$attribute = $args['attribute'];
		$name      = 'attribute_' . sanitize_title( $attribute );
		$label     = wc_attribute_label( $attribute, $product );

		// Option values + display names in the same order WooCommerce uses for the select.
		$options = array();
		if ( taxonomy_exists( $attribute ) ) {
			foreach ( wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) ) as $term ) {
				if ( in_array( $term->slug, $args['options'], true ) ) {
					$options[ $term->slug ] = apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product );
				}
			}
		} else {
			foreach ( $args['options'] as $option ) {
				$options[ $option ] = apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product );
			}
		}

		$select  = str_replace( '<select ', '<select tabindex="-1" aria-hidden="true" ', $html );
		$out     = '<div class="variant-select visually-hidden">' . $select . '</div>';
		$is_size = ci_is_size_attribute( $attribute, $label );
		$swatch  = CI_COLOUR_TAXONOMY === $attribute;
		$heading = $label;
		$system  = trim( (string) ci_mod( 'size_system_label' ) );
		if ( $is_size && '' !== $system && false === stripos( $label, $system ) ) {
			$heading .= " ({$system})";
		}

		// Visible head: "SIZE (UK): 8" (value kept in sync by product.js) + size guide link on the right.
		$out .= sprintf(
			'<div class="variant-head"><p class="variant-head__label">%1$s: <span class="variant-head__value" data-variant-value="%2$s" data-empty="%3$s">%4$s</span></p>%5$s</div>',
			esc_html( $heading ),
			esc_attr( $name ),
			esc_attr__( 'Select', 'customimpulse' ),
			esc_html( (string) ( $options[ (string) $args['selected'] ] ?? __( 'Select', 'customimpulse' ) ) ),
			$is_size && ci_product_size_guide( $product ) ? ci_size_guide_button() : ''
		);
		$out .= sprintf( '<fieldset class="variant-buttons%3$s" data-variant-group="%1$s"%2$s>', esc_attr( $name ), $is_size ? ' data-size-group' : '', $swatch ? ' variant-buttons--swatches' : '' );
		$out .= '<legend class="visually-hidden">' . esc_html( $label ) . '</legend>';
		foreach ( $options as $value => $text ) {
			$id   = wp_unique_id( 'variant-' );
			$term = $swatch ? get_term_by( 'slug', $value, $attribute ) : null;
			$css  = $term ? ci_swatch_css( $term->term_id ) : '';
			$out .= sprintf(
				'<input class="variant-buttons__input visually-hidden" type="radio" id="%1$s" name="ci-%2$s" value="%3$s"%4$s><label class="variant-buttons__label%6$s" for="%1$s">%5$s</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				checked( (string) $args['selected'], (string) $value, false ),
				$css
					? '<span class="colour-swatch__fill" style="background:' . esc_attr( $css ) . '"></span><span class="visually-hidden">' . esc_html( $text ) . '</span>'
					: esc_html( $text ),
				$css ? ' variant-buttons__label--swatch' : ''
			);
		}
		$out .= '</fieldset>';
		return $out;
	},
	10,
	2
);

/* The "Clear" link makes no sense with an always-complete selection. */
add_filter( 'woocommerce_reset_variations_link', '__return_empty_string' );

/* ---------------------------------------------------------------------------
 * Selling points, stock status, trust badges
 * ------------------------------------------------------------------------ */

/**
 * Stock line above quantity + add to cart (design v2; the selling points became the trust lines under it).
 */
function ci_product_selling_points() {
	global $product;
	$stock = ci_mod( 'stock_status_enable' ) ? ci_stock_status_html( $product ) : '';
	if ( $stock ) {
		echo '<ul class="selling-points">' . $stock . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built in ci_stock_status_html().
	}
}

/**
 * Trust lines under add to cart: the Customizer selling points, each with a check mark.
 */
function ci_product_trust_lines() {
	$lines = array();
	for ( $i = 1; $i <= CI_SALES_POINT_SLOTS; $i++ ) {
		$text = trim( (string) ci_mod( "sales_point_{$i}_text" ) );
		if ( '' !== $text ) {
			$lines[] = $text;
		}
	}
	if ( ! $lines ) {
		return;
	}
	echo '<ul class="trust-lines">';
	foreach ( $lines as $line ) {
		echo '<li class="trust-lines__item"><span class="trust-lines__check" aria-hidden="true">' . ci_icon( 'check' ) . '</span>' . esc_html( $line ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul>';
}

/**
 * Express checkout slot: payment plugins (Stripe, PayPal…) render their wallet buttons through the
 * `ci_product_express_checkout` action. The "Express checkout" divider only shows when something is inside.
 */
function ci_product_express_checkout() {
	ob_start();
	do_action( 'ci_product_express_checkout' );
	$slot = trim( (string) ob_get_clean() );
	printf(
		'<div class="express-checkout"%3$s><p class="express-checkout__label"><span>%1$s</span></p><div class="express-checkout__slot" data-express-checkout>%2$s</div></div>',
		esc_html__( 'Express checkout', 'customimpulse' ),
		$slot, // phpcs:ignore WordPress.Security.EscapeOutput -- plugin output.
		'' === $slot ? ' hidden' : ''
	);
}

/**
 * Authenticity box (Customizer → Product page).
 */
function ci_product_authenticity() {
	$title = trim( (string) ci_mod( 'product_auth_title' ) );
	$text  = trim( (string) ci_mod( 'product_auth_text' ) );
	if ( ! ci_mod( 'product_auth_enable' ) || ( '' === $title && '' === $text ) ) {
		return;
	}
	$badge = trim( (string) ci_mod( 'product_auth_badge' ) );
	$label = trim( (string) ci_mod( 'product_auth_link_label' ) );
	$url   = (string) ci_mod( 'product_auth_link_url' );
	echo '<div class="authenticity">';
	if ( '' !== $badge ) {
		echo '<span class="authenticity__badge" aria-hidden="true">' . esc_html( $badge ) . '</span>';
	}
	echo '<div class="authenticity__body">';
	if ( '' !== $title ) {
		echo '<p class="authenticity__title">' . esc_html( $title ) . '</p>';
	}
	echo '<p class="authenticity__text">' . esc_html( $text );
	if ( '' !== $label && '' !== $url ) {
		echo ' <a class="authenticity__link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</p></div></div>';
}

/**
 * Colour row: this product's colour (pa_color, non-variation) + the other colourways of the same model
 * (products in the same deepest category with a colour), as swatch links. Colour variations use the
 * variation buttons instead.
 */
function ci_product_colourways() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$attr = $product->get_attributes()[ CI_COLOUR_TAXONOMY ] ?? null;
	if ( ! $attr || $attr->get_variation() ) {
		return;
	}
	$own = $attr->get_terms()[0] ?? null;
	if ( ! $own ) {
		return;
	}
	// The model = the deepest category of the product (e.g. Shoes > New Balance > New Balance 530).
	$model = null;
	$depth = -1;
	foreach ( wc_get_product_terms( $product->get_id(), 'product_cat', array( 'fields' => 'all' ) ) as $cat ) {
		$d = count( get_ancestors( $cat->term_id, 'product_cat', 'taxonomy' ) );
		if ( $d > $depth ) {
			$depth = $d;
			$model = $cat;
		}
	}
	$items = array( $product->get_id() => $own );
	if ( $model && $depth > 0 ) {
		foreach ( wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => 12,
				'category' => array( $model->slug ),
				'orderby'  => 'title',
				'order'    => 'ASC',
				'exclude'  => array( $product->get_id() ),
			)
		) as $sibling ) {
			$a = $sibling->get_attributes()[ CI_COLOUR_TAXONOMY ] ?? null;
			$t = $a && ! $a->get_variation() ? ( $a->get_terms()[0] ?? null ) : null;
			if ( $t && ci_swatch_css( $t->term_id ) ) {
				$items[ $sibling->get_id() ] = $t;
			}
		}
	}
	echo '<div class="product-colours"><div class="variant-head"><p class="variant-head__label">' . esc_html( wc_attribute_label( CI_COLOUR_TAXONOMY, $product ) ) . ': <span class="variant-head__value">' . esc_html( $own->name ) . '</span></p></div><ul class="colour-swatches">';
	foreach ( $items as $id => $term ) {
		$current = $id === $product->get_id();
		printf(
			'<li><a class="colour-swatch%1$s" href="%2$s"%3$s title="%4$s"><span class="colour-swatch__fill" style="background:%5$s"></span><span class="visually-hidden">%4$s</span></a></li>',
			$current ? ' is-current' : '',
			esc_url( get_permalink( $id ) ),
			$current ? ' aria-current="page"' : '',
			esc_attr( $term->name ),
			esc_attr( ci_swatch_css( $term->term_id ) ? ci_swatch_css( $term->term_id ) : 'var(--color-border)' )
		);
	}
	echo '</ul></div>';
}

/**
 * Stock line for the product (variable products start from the preselected variation; JS keeps it in sync).
 *
 * @param WC_Product $product Product.
 */
function ci_stock_status_html( WC_Product $product ) {
	$state = 'ok';
	$qty   = null;
	if ( $product instanceof WC_Product_Variable ) {
		$first = ci_first_available_attributes( $product );
		if ( ! $first ) {
			$state = 'out';
		}
	} elseif ( ! $product->is_in_stock() ) {
		$state = 'out';
	} elseif ( $product->managing_stock() ) {
		$qty = (int) $product->get_stock_quantity();
	}
	$threshold = absint( ci_mod( 'stock_low_threshold' ) );
	if ( 'ok' === $state && null !== $qty && $qty <= $threshold ) {
		$state = 'low';
	}
	return sprintf(
		'<li class="selling-points__item product-stock product-stock--%1$s" data-product-stock data-label-ok="%2$s" data-label-low="%3$s" data-label-out="%4$s" data-label-pick="%6$s"><span class="product-stock__dot" aria-hidden="true"></span><span class="product-stock__text">%5$s</span></li>',
		esc_attr( $state ),
		esc_attr__( 'In stock, ready to ship', 'customimpulse' ),
		/* translators: %d: units left. Keep the %d placeholder. */
		esc_attr__( 'Low stock - %d items left', 'customimpulse' ),
		esc_attr__( 'Sold out', 'customimpulse' ),
		esc_html( ci_stock_label( $state, $qty ) ),
		esc_attr__( 'In stock, ready to ship – select your size', 'customimpulse' )
	);
}

function ci_stock_label( $state, $qty ) {
	if ( 'out' === $state ) {
		return __( 'Sold out', 'customimpulse' );
	}
	if ( 'low' === $state ) {
		/* translators: %d: units left */
		return sprintf( __( 'Low stock - %d items left', 'customimpulse' ), $qty );
	}
	return __( 'In stock, ready to ship', 'customimpulse' );
}

/**
 * Trust badges: static theme component reusing the footer's payment icon selection.
 */
function ci_product_trust_badges() {
	if ( ! ci_mod( 'trust_badges_enable' ) ) {
		return;
	}
	$icons = array_filter( array_keys( ci_payment_icon_choices() ), fn( $key ) => (bool) ci_mod( "payment_{$key}" ) );
	if ( ! $icons ) {
		return;
	}
	$title = (string) ci_mod( 'trust_badges_title' );
	echo '<div class="trust-badges">';
	if ( $title ) {
		echo '<p class="trust-badges__title">' . esc_html( $title ) . '</p>';
	}
	echo '<ul class="payment-icons" aria-label="' . esc_attr__( 'Payment methods', 'customimpulse' ) . '">';
	foreach ( $icons as $key ) {
		echo '<li>' . ci_payment_icon( $key ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul></div>';
}

/* ---------------------------------------------------------------------------
 * Description, tabs, reviews
 * ------------------------------------------------------------------------ */

/**
 * Accordion rows: Description (open), Product details, Shipping & returns, Authenticity guarantee, then any tab a
 * plugin adds through woocommerce_product_tabs (description/reviews/additional information are ours).
 *
 * @param WC_Product $product Product.
 * @return array<int,array{title:string,html:string}>
 */
function ci_product_accordion_items( WC_Product $product ) {
	$items = array();
	$desc  = trim( (string) get_post_field( 'post_content', $product->get_id() ) );
	if ( '' !== $desc ) {
		$items[] = array(
			'title' => __( 'Description', 'customimpulse' ),
			'html'  => apply_filters( 'the_content', $desc ),
		);
	}

	$details = trim( (string) $product->get_short_description() );
	$items[] = array(
		'title' => __( 'Product details', 'customimpulse' ),
		'html'  => '' !== $details ? wp_kses_post( wpautop( $details ) ) : ci_product_details_list( $product ),
	);

	foreach ( array(
		'shipping'     => __( 'Shipping & returns', 'customimpulse' ),
		'authenticity' => __( 'Authenticity guarantee', 'customimpulse' ),
	) as $key => $default_title ) {
		$own   = trim( (string) get_post_meta( $product->get_id(), "_ci_{$key}_text", true ) );
		$text  = '' !== $own ? $own : trim( (string) ci_mod( "product_{$key}_text" ) );
		$title = trim( (string) ci_mod( "product_{$key}_title" ) );
		if ( '' !== $text ) {
			$items[] = array(
				'title' => '' !== $title ? $title : $default_title,
				'html'  => wp_kses_post( wpautop( $text ) ),
			);
		}
	}

	foreach ( apply_filters( 'woocommerce_product_tabs', array() ) as $key => $tab ) {
		if ( in_array( $key, array( 'description', 'additional_information', 'reviews' ), true ) || empty( $tab['callback'] ) ) {
			continue;
		}
		ob_start();
		call_user_func( $tab['callback'], $key, $tab );
		$items[] = array(
			'title' => wp_strip_all_tags( apply_filters( "woocommerce_product_{$key}_tab_title", $tab['title'], $key ) ),
			'html'  => (string) ob_get_clean(),
		);
	}
	return array_values( array_filter( $items, fn( $i ) => '' !== trim( wp_strip_all_tags( $i['html'] ) ) ) );
}

/**
 * "Product details" when the product has no short description: brand, visible attributes (sizes as a range), SKU.
 *
 * @param WC_Product $product Product.
 */
function ci_product_details_list( WC_Product $product ) {
	$rows  = array();
	$brand = function_exists( 'ci_product_brand' ) ? ci_product_brand( $product->get_id() ) : null;
	if ( $brand ) {
		$rows[ __( 'Brand', 'customimpulse' ) ] = $brand->name;
	}
	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_visible() ) {
			continue;
		}
		$values = $attribute->is_taxonomy() ? wp_list_pluck( $attribute->get_terms(), 'name' ) : $attribute->get_options();
		$label  = wc_attribute_label( $attribute->get_name(), $product );
		if ( ci_is_size_attribute( $attribute->get_name(), $label ) && count( $values ) > 2 ) {
			$nums = array_map( fn( $v ) => (float) str_replace( ',', '.', $v ), $values );
			if ( count( array_filter( $nums ) ) === count( $nums ) ) {
				$system = trim( (string) ci_mod( 'size_system_label' ) );
				$values = array( trim( $system . ' ' . wc_format_localized_decimal( min( $nums ) ) . '–' . wc_format_localized_decimal( max( $nums ) ) ) );
			}
		}
		$rows[ $label ] = implode( ', ', $values );
	}
	if ( $product->get_sku() ) {
		$rows[ __( 'SKU', 'customimpulse' ) ] = $product->get_sku();
	}
	if ( ! $rows ) {
		return '';
	}
	$html = '<dl class="product-details">';
	foreach ( $rows as $label => $value ) {
		$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
	}
	return $html . '</dl>';
}

/**
 * Accordion block under the product (native <details>; the first row is open).
 */
function ci_product_accordions() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$items = ci_product_accordion_items( $product );
	if ( ! $items ) {
		return;
	}
	echo '<section class="product-accordions" aria-label="' . esc_attr__( 'Product information', 'customimpulse' ) . '">';
	foreach ( $items as $i => $item ) {
		printf(
			'<details class="product-accordion"%1$s><summary class="product-accordion__title">%2$s%3$s</summary><div class="product-accordion__content rte">%4$s</div></details>',
			0 === $i ? ' open' : '',
			esc_html( $item['title'] ),
			ci_icon( 'chevron-down', 'product-accordion__icon' ), // phpcs:ignore WordPress.Security.EscapeOutput
			$item['html'] // phpcs:ignore WordPress.Security.EscapeOutput -- kses'd / the_content / plugin tab output.
		);
	}
	echo '</section>';
}

/* Per-product override of the Shipping & returns / Authenticity guarantee accordion text. */
add_action(
	'add_meta_boxes_product',
	function () {
		add_meta_box(
			'ci-product-texts',
			__( 'Product page: accordion texts', 'customimpulse' ),
			function ( $post ) {
				wp_nonce_field( 'ci_product_texts', 'ci_product_texts_nonce' );
				foreach ( array(
					'shipping'     => __( 'Shipping & returns (empty = Customizer text)', 'customimpulse' ),
					'authenticity' => __( 'Authenticity guarantee (empty = Customizer text)', 'customimpulse' ),
				) as $key => $label ) {
					printf(
						'<p><label for="ci-%1$s-text"><strong>%2$s</strong></label><textarea class="widefat" rows="4" id="ci-%1$s-text" name="ci_%1$s_text">%3$s</textarea></p>',
						esc_attr( $key ),
						esc_html( $label ),
						esc_textarea( (string) get_post_meta( $post->ID, "_ci_{$key}_text", true ) )
					);
				}
			},
			'product',
			'normal',
			'low'
		);
	}
);
add_action(
	'save_post_product',
	function ( $post_id ) {
		if ( ! isset( $_POST['ci_product_texts_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ci_product_texts_nonce'] ), 'ci_product_texts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array( 'shipping', 'authenticity' ) as $key ) {
			$text = trim( wp_kses_post( wp_unslash( $_POST[ "ci_{$key}_text" ] ?? '' ) ) );
			if ( '' === $text ) {
				delete_post_meta( $post_id, "_ci_{$key}_text" );
			} else {
				update_post_meta( $post_id, "_ci_{$key}_text", $text );
			}
		}
	}
);

/* ---------------------------------------------------------------------------
 * Size guide: HTML per product category (term meta), shown in a modal
 * ------------------------------------------------------------------------ */

/**
 * Whether an attribute is the "size" attribute the size guide link attaches to.
 *
 * @param string $attribute Attribute name (pa_size, Size…).
 * @param string $label     Attribute label.
 */
function ci_is_size_attribute( $attribute, $label ) {
	$is_size = false !== stripos( $attribute, 'size' ) || false !== stripos( $label, 'size' );
	return (bool) apply_filters( 'ci_is_size_attribute', $is_size, $attribute, $label );
}

/**
 * Size guide HTML for a product: first product category (or ancestor) that has one.
 *
 * @param WC_Product $product Product.
 */
function ci_product_size_guide( WC_Product $product ) {
	static $cache = array();
	$id = $product->get_id();
	if ( array_key_exists( $id, $cache ) ) {
		return $cache[ $id ];
	}
	$guide = '';
	foreach ( wc_get_product_term_ids( $id, 'product_cat' ) as $term_id ) {
		foreach ( array_merge( array( $term_id ), get_ancestors( $term_id, 'product_cat', 'taxonomy' ) ) as $candidate ) {
			$guide = (string) get_term_meta( $candidate, 'ci_size_guide', true );
			if ( '' !== trim( $guide ) ) {
				break 2;
			}
		}
	}
	$cache[ $id ] = $guide;
	return $guide;
}

function ci_size_guide_button() {
	return sprintf(
		'<button type="button" class="size-guide-link" data-modal-open="size-guide">%s<span>%s</span></button>',
		ci_icon( 'ruler' ),
		esc_html__( 'Size guide', 'customimpulse' )
	);
}

/* Products without a size attribute (or simple products) get the link above the add to cart button. */
add_action(
	'woocommerce_before_add_to_cart_button',
	function () {
		global $product;
		if ( ! ci_product_size_guide( $product ) ) {
			return;
		}
		if ( $product instanceof WC_Product_Variable ) {
			foreach ( $product->get_variation_attributes() as $attribute => $unused ) {
				if ( ci_is_size_attribute( $attribute, wc_attribute_label( $attribute, $product ) ) ) {
					return; // Already next to the size buttons.
				}
			}
		}
		echo ci_size_guide_button(); // phpcs:ignore WordPress.Security.EscapeOutput
	},
	4
);

add_action(
	'wp_footer',
	function () {
		if ( ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		$guide   = $product ? ci_product_size_guide( $product ) : '';
		if ( ! $guide ) {
			return;
		}
		echo ci_size_guide_modal( $guide, 'size-guide-title' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/**
 * Size guide modal. The guide is the category's HTML (Products → Categories); the theme styles its table and
 * highlights the row of the selected size when it opens (product.js).
 *
 * @param string $guide    Guide HTML.
 * @param string $title_id Unique id for the title.
 */
function ci_size_guide_modal( $guide, $title_id ) {
	ob_start();
	?>
	<dialog class="modal modal--size-guide" data-modal="size-guide" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
		<div class="modal__inner">
			<button type="button" class="modal__close" data-modal-close>
				<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="visually-hidden"><?php esc_html_e( 'Close', 'customimpulse' ); ?></span>
			</button>
			<div class="size-guide__head">
				<?php echo ci_icon( 'ruler' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h2 class="modal__title h3" id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Size guide', 'customimpulse' ); ?></h2>
			</div>
			<p class="size-guide__current" data-size-guide-current hidden><?php esc_html_e( 'Your selected size:', 'customimpulse' ); ?> <strong></strong></p>
			<div class="modal__content size-guide rte"><?php echo wp_kses_post( $guide ); ?></div>
		</div>
	</dialog>
	<?php
	return ob_get_clean();
}

/* Admin: size guide field on product categories. */
add_action(
	'product_cat_add_form_fields',
	function () {
		?>
		<div class="form-field">
			<label for="ci-size-guide"><?php esc_html_e( 'Size guide', 'customimpulse' ); ?></label>
			<textarea name="ci_size_guide" id="ci-size-guide" rows="6"></textarea>
			<p class="description"><?php esc_html_e( 'HTML allowed (tables, lists). Shown in a modal on products of this category and its subcategories.', 'customimpulse' ); ?></p>
		</div>
		<?php
	}
);
add_action(
	'product_cat_edit_form_fields',
	function ( $term ) {
		?>
		<tr class="form-field">
			<th scope="row"><label for="ci-size-guide"><?php esc_html_e( 'Size guide', 'customimpulse' ); ?></label></th>
			<td>
				<?php
				wp_editor(
					(string) get_term_meta( $term->term_id, 'ci_size_guide', true ),
					'ci-size-guide',
					array(
						'textarea_name' => 'ci_size_guide',
						'textarea_rows' => 10,
						'media_buttons' => false,
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'Shown in a modal on products of this category and its subcategories (unless a subcategory has its own).', 'customimpulse' ); ?></p>
			</td>
		</tr>
		<?php
	}
);
$ci_save_size_guide = function ( $term_id ) {
	// Core verified the term form nonce before firing created_/edited_ hooks.
	if ( ! isset( $_POST['ci_size_guide'] ) || ! current_user_can( 'manage_product_terms' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$guide = wp_kses_post( wp_unslash( $_POST['ci_size_guide'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === trim( $guide ) ) {
		delete_term_meta( $term_id, 'ci_size_guide' );
	} else {
		update_term_meta( $term_id, 'ci_size_guide', $guide );
	}
};
add_action( 'created_product_cat', $ci_save_size_guide );
add_action( 'edited_product_cat', $ci_save_size_guide );

/* ---------------------------------------------------------------------------
 * Sticky add to cart bar (mobile)
 * ------------------------------------------------------------------------ */

add_action(
	'wp_footer',
	function () {
		if ( ! is_product() || ! ci_mod( 'sticky_atc_enable' ) ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || $product->is_type( array( 'external', 'grouped' ) ) ) {
			return;
		}
		get_template_part( 'template-parts/product/sticky-atc', null, array( 'product' => $product ) );
	}
);

/*
 * Gallery extras (design v2): Sale / Sold out badge, "1 / N" counter (phones), "Hover to zoom" label for WC's zoom
 * button. Printed after the gallery; product.js moves them into it (no template override needed).
 */
add_action(
	'woocommerce_before_single_product_summary',
	function () {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$count = count( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );
		$badge = '';
		if ( ! $product->is_in_stock() ) {
			$badge = '<span class="gallery-badge gallery-badge--sold-out">' . esc_html__( 'Sold out', 'customimpulse' ) . '</span>';
		} elseif ( $product->is_on_sale() ) {
			$badge = '<span class="gallery-badge">' . esc_html__( 'Sale', 'customimpulse' ) . '</span>';
		}
		printf(
			'<div class="gallery-extras" data-gallery-extras data-zoom-label="%1$s">%2$s%3$s</div>',
			esc_attr__( 'Hover to zoom', 'customimpulse' ),
			$badge, // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
			$count > 1 ? '<span class="gallery-counter" data-gallery-counter aria-hidden="true">1 / ' . absint( $count ) . '</span>' : ''
		);
	},
	21
);

/* ---------------------------------------------------------------------------
 * Gallery images: main image is the LCP; give it priority and correct sizes
 * ------------------------------------------------------------------------ */

add_filter(
	'woocommerce_gallery_image_html_attachment_image_params',
	function ( $params, $attachment_id, $image_size, $main_image ) {
		// Phones: the slide is full width, but "100vw" makes 3× phones pick the original (≈250 KB). 87vw caps the
		// choice at the 1024px file (≈2.6× density, no visible difference) — same rule as the homepage images.
		$params['sizes'] = '(min-width: 769px) 590px, 87vw';
		if ( $main_image ) {
			$params['loading']       = 'eager';
			$params['fetchpriority'] = 'high';
		} else {
			$params['loading'] = 'lazy';
		}
		return $params;
	},
	10,
	4
);

/*
 * Gallery slides: no "sizes=auto" (WordPress adds it to lazy images). Flexslider resizes the slides after init,
 * and with "auto" the browser re-picks a candidate and downloads every slide a second time.
 */
add_filter(
	'wp_get_attachment_image_attributes',
	function ( $attr ) {
		if ( isset( $attr['data-large_image'], $attr['sizes'] ) ) {
			$attr['sizes'] = preg_replace( '/^auto,\s*/i', '', $attr['sizes'] );
		}
		return $attr;
	}
);

/*
 * Hover zoom only where there is a hover: on touch screens it does nothing useful, but jquery.zoom still downloads
 * the full-size image (≈250 KB) while the page loads, competing with the LCP image on mobile data.
 * The plugin becomes a no-op there; WC's zoom setting stays on, so the magnifier button + lightbox are unchanged.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$handle = wp_script_is( 'wc-zoom', 'registered' ) ? 'wc-zoom' : 'zoom';
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			wp_add_inline_script( $handle, 'if(window.jQuery&&jQuery.fn.zoom&&!matchMedia("(hover:hover)").matches){jQuery.fn.zoom=function(){return this;};}' );
		}
	},
	30
);

/*
 * Thumbnail nav (80px on desktop, dots on mobile): flexslider sets the thumb's srcset before its sizes, so the browser
 * first picks for 100vw and downloads the full-size file (≈250 KB each), then the 300px one. Offer only small files.
 */
add_filter(
	'woocommerce_single_product_image_thumbnail_html',
	function ( $html ) {
		return preg_replace_callback(
			'/data-thumb-srcset="([^"]*)"/',
			fn( $m ) => 'data-thumb-srcset="' . esc_attr( ci_srcset_upto( html_entity_decode( $m[1] ), 300 ) ) . '"',
			$html
		);
	}
);

/* Thumbnails as dots on mobile are cheap; keep WC's thumbnail nav, no direction arrows. */
add_filter(
	'woocommerce_single_product_carousel_options',
	function ( $options ) {
		$options['directionNav'] = false;
		$options['controlNav']   = 'thumbnails';
		return $options;
	}
);

/*
 * Product pages have only our Add to Cart: Stripe's express buttons (Apple/Google Pay, Link, Amazon Pay) are turned
 * off for the product page in Stripe's settings (Express checkout → locations: cart + checkout). Stripe still prints
 * stripe.js there by default; nothing uses it without the buttons, so don't load it (≈270 KB + iframes on phones).
 */
add_filter( 'wc_stripe_load_scripts_on_product_page_when_prbs_disabled', '__return_false' );
