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

/* Selling points + stock status, right above the add to cart button (inside the form). */
add_action( 'woocommerce_before_add_to_cart_button', 'ci_product_selling_points', 5 );

/* Slot after the add to cart form; trust badges are the default occupant. */
add_action(
	'woocommerce_after_add_to_cart_form',
	function () {
		do_action( 'ci_product_after_add_to_cart' );
	},
	10
);
add_action( 'ci_product_after_add_to_cart', 'ci_product_trust_badges', 10 );

add_action( 'woocommerce_single_product_summary', 'ci_product_description', 35 );
add_action( 'woocommerce_single_product_summary', 'ci_product_tabs', 36 );

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

		$select = str_replace( '<select ', '<select tabindex="-1" aria-hidden="true" ', $html );
		$out    = '<div class="variant-select visually-hidden">' . $select . '</div>';
		$out   .= sprintf( '<fieldset class="variant-buttons" data-variant-group="%s">', esc_attr( $name ) );
		$out   .= '<legend class="visually-hidden">' . esc_html( $label ) . '</legend>';
		foreach ( $options as $value => $text ) {
			$id   = wp_unique_id( 'variant-' );
			$out .= sprintf(
				'<input class="variant-buttons__input visually-hidden" type="radio" id="%1$s" name="ci-%2$s" value="%3$s"%4$s><label class="variant-buttons__label" for="%1$s">%5$s</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				checked( (string) $args['selected'], (string) $value, false ),
				esc_html( $text )
			);
		}
		$out .= '</fieldset>';

		if ( ci_is_size_attribute( $attribute, $label ) && ci_product_size_guide( $product ) ) {
			$out .= ci_size_guide_button();
		}
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

function ci_product_selling_points() {
	global $product;
	$points = array();
	for ( $i = 1; $i <= CI_SALES_POINT_SLOTS; $i++ ) {
		$text = trim( (string) ci_mod( "sales_point_{$i}_text" ) );
		if ( '' !== $text ) {
			$points[] = array(
				'icon' => (string) ci_mod( "sales_point_{$i}_icon" ),
				'text' => $text,
			);
		}
	}
	$stock = ci_mod( 'stock_status_enable' ) ? ci_stock_status_html( $product ) : '';
	if ( ! $points && ! $stock ) {
		return;
	}

	echo '<ul class="selling-points">';
	foreach ( $points as $i => $point ) {
		printf(
			'<li class="selling-points__item">%s<span>%s</span></li>',
			ci_icon( array_key_exists( $point['icon'], ci_sales_point_icons() ) ? $point['icon'] : 'check' ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $point['text'] )
		);
		// Stock status sits after the first selling point, as on the reference store.
		if ( 0 === $i && $stock ) {
			echo $stock; // phpcs:ignore WordPress.Security.EscapeOutput -- built below.
			$stock = '';
		}
	}
	echo $stock; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</ul>';
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
		'<li class="selling-points__item product-stock product-stock--%1$s" data-product-stock data-label-ok="%2$s" data-label-low="%3$s" data-label-out="%4$s"><span class="product-stock__dot" aria-hidden="true"></span><span class="product-stock__text">%5$s</span></li>',
		esc_attr( $state ),
		esc_attr__( 'In stock, ready to ship', 'customimpulse' ),
		/* translators: %d: units left. Keep the %d placeholder. */
		esc_attr__( 'Low stock - %d items left', 'customimpulse' ),
		esc_attr__( 'Sold out', 'customimpulse' ),
		esc_html( ci_stock_label( $state, $qty ) )
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

function ci_product_description() {
	if ( ! ci_mod( 'product_description_inline' ) ) {
		return;
	}
	$content = get_the_content();
	if ( '' === trim( $content ) ) {
		return;
	}
	echo '<div class="product-description rte">';
	the_content();
	echo '</div>';
}

/**
 * Collapsible tabs: Customizer tabs (shared by all products) + tabs added to woocommerce_product_tabs by plugins.
 */
function ci_product_tabs() {
	$tabs = array();
	for ( $i = 1; $i <= CI_PRODUCT_TAB_SLOTS; $i++ ) {
		$title   = trim( (string) ci_mod( "product_tab_{$i}_title" ) );
		$content = (string) ci_mod( "product_tab_{$i}_content" );
		if ( '' !== $title && '' !== trim( $content ) ) {
			$tabs[] = array(
				'title' => $title,
				'html'  => wp_kses_post( wpautop( $content ) ),
			);
		}
	}

	// Description is shown inline (or as a tab when inline is off); reviews get their own section.
	$skip = array( 'additional_information', 'reviews' );
	if ( ci_mod( 'product_description_inline' ) ) {
		$skip[] = 'description';
	}
	foreach ( apply_filters( 'woocommerce_product_tabs', array() ) as $key => $tab ) {
		if ( in_array( $key, $skip, true ) || empty( $tab['callback'] ) ) {
			continue;
		}
		ob_start();
		call_user_func( $tab['callback'], $key, $tab );
		$tabs[] = array(
			'title' => wp_strip_all_tags( apply_filters( "woocommerce_product_{$key}_tab_title", $tab['title'], $key ) ),
			'html'  => (string) ob_get_clean(),
		);
	}

	if ( ! $tabs ) {
		return;
	}
	echo '<div class="product-tabs">';
	foreach ( $tabs as $tab ) {
		printf(
			'<details class="product-tab"><summary class="product-tab__title">%s%s</summary><div class="product-tab__content rte">%s</div></details>',
			esc_html( $tab['title'] ),
			ci_icon( 'chevron-down', 'product-tab__icon' ), // phpcs:ignore WordPress.Security.EscapeOutput
			$tab['html'] // phpcs:ignore WordPress.Security.EscapeOutput -- kses'd above or plugin tab output.
		);
	}
	echo '</div>';
}

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
		?>
		<dialog class="modal" data-modal="size-guide" aria-labelledby="size-guide-title">
			<div class="modal__inner">
				<button type="button" class="modal__close" data-modal-close>
					<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="visually-hidden"><?php esc_html_e( 'Close', 'customimpulse' ); ?></span>
				</button>
				<h2 class="modal__title h3" id="size-guide-title"><?php esc_html_e( 'Size guide', 'customimpulse' ); ?></h2>
				<div class="modal__content rte"><?php echo wp_kses_post( $guide ); ?></div>
			</div>
		</dialog>
		<?php
	}
);

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

/* ---------------------------------------------------------------------------
 * Gallery images: main image is the LCP; give it priority and correct sizes
 * ------------------------------------------------------------------------ */

add_filter(
	'woocommerce_gallery_image_html_attachment_image_params',
	function ( $params, $attachment_id, $image_size, $main_image ) {
		$params['sizes'] = '(min-width: 769px) 575px, 75vw';
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

/* Thumbnails as dots on mobile are cheap; keep WC's thumbnail nav, no direction arrows. */
add_filter(
	'woocommerce_single_product_carousel_options',
	function ( $options ) {
		$options['directionNav'] = false;
		$options['controlNav']   = 'thumbnails';
		return $options;
	}
);
