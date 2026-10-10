<?php
/**
 * Import products from a Shopify product CSV export into WooCommerce.
 *
 *   tools/wp eval-file tools/import-shopify.php                 # first 5 products of the CSV
 *   tools/wp eval-file tools/import-shopify.php handle-a handle-b
 *   CI_LIMIT=0 tools/wp eval-file tools/import-shopify.php      # whole CSV
 *   CI_DRY_RUN=1 tools/wp eval-file tools/import-shopify.php    # print what would happen, write nothing
 *   (npm run import:products -- [handle ...] works too)
 *
 * Env: CI_SHOPIFY_CSV (default reference/products_export_1.csv), CI_LIMIT (default 5, 0 = all), CI_DRY_RUN (1 = no writes),
 *      CI_IMAGES (default 0: nothing is downloaded from Shopify; 1 = download images during the import).
 *
 * Kept simple on purpose:
 * - Only the plain CSV columns are read. Metafield columns and "Option Linked To" are ignored. Collections/categories
 *   are not imported (assign them in WooCommerce afterwards).
 * - Two global attributes for the whole store: any option containing "color/colour" → Color, "size" → Size.
 * - Values are normalized: "7-5" → 7.5, s/xl/xxl → S/XL/2XL, slug-like colours ("grey-dark", "beige-1") → "Grey Dark",
 *   "Beige". Names differing only by case reuse the same term.
 * - Only the variations that are needed: combinations that become duplicates after normalizing are created once;
 *   an option with a single value for the whole product is a plain attribute (not a variation); a product with no
 *   real choice left is a simple product.
 * - Size fix: in products whose sizes mix EU-like numbers with UK/US sizes, the Shopify metafield handles
 *   37/38/39/40 are the sizes 4/5/6/7 (they sit exactly in those gaps; confirmed on the live store for 39/40), and
 *   "6.6" is 6.5. They are converted unless the converted size already exists in that product (then kept + warned).
 *   Other EU-like values (35, 36, 41–44) are kept as-is and listed in a warning.
 * - After a run, Color/Size terms that no product uses any more are deleted.
 * - Price → sale price, Compare At → regular price. SKU when unique, weight from grams. The CSV has no stock
 *   quantities: everything is imported "in stock".
 * - Images: by default NOTHING is fetched from Shopify. The image list is stored for a later step:
 *   product meta `_shopify_images` = [{src, alt}, …] in Shopify's order, variation meta `_shopify_variant_image` = src.
 *   With CI_IMAGES=1 they are downloaded now instead (first = featured, rest = gallery, Variant Image → variation).
 * - Re-running updates products in place (matched by meta _shopify_handle; variations rebuilt).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$ci_csv   = getenv( 'CI_SHOPIFY_CSV' ) ? getenv( 'CI_SHOPIFY_CSV' ) : get_theme_file_path( 'reference/products_export_1.csv' );
$ci_limit = false === getenv( 'CI_LIMIT' ) ? 5 : (int) getenv( 'CI_LIMIT' );
$ci_dry   = (bool) getenv( 'CI_DRY_RUN' );
define( 'CI_SHOPIFY_DOWNLOAD_IMAGES', (bool) getenv( 'CI_IMAGES' ) );

if ( ! file_exists( $ci_csv ) ) {
	WP_CLI::error( "CSV not found: {$ci_csv}" );
}

/* ---------------------------------------------------------------------------
 * Reading + normalizing
 * ------------------------------------------------------------------------ */

/**
 * All CSV rows grouped by handle, in file order.
 *
 * @return array<string,array<int,array<string,string>>>
 */
function ci_shopify_read_csv( $csv ) {
	$fh        = fopen( $csv, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$header    = fgetcsv( $fh );
	$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] ); // BOM
	$out       = array();
	while ( ( $row = fgetcsv( $fh ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		if ( count( $row ) === count( $header ) ) {
			$row                     = array_combine( $header, $row );
			$out[ $row['Handle'] ][] = $row;
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $out;
}

/**
 * Store-wide attribute for a Shopify option name: "Color", "Size", or the option name itself.
 */
function ci_shopify_attribute_name( $option_name ) {
	if ( preg_match( '/colou?r/i', $option_name ) ) {
		return 'Color';
	}
	if ( preg_match( '/size/i', $option_name ) ) {
		return 'Size';
	}
	return ucfirst( strtolower( trim( $option_name ) ) );
}

/**
 * Normalized option value.
 */
function ci_shopify_normalize_value( $attribute, $value ) {
	$v = preg_replace( '/\s+/', ' ', trim( $value ) );
	if ( '' === $v ) {
		return '';
	}
	if ( 'Size' === $attribute ) {
		if ( preg_match( '/^(\d+)-5$/', $v, $m ) ) {
			return $m[1] . '.5'; // "7-5" → "7.5"
		}
		$letters = array( 'XXS' => 'XXS', 'XS' => 'XS', 'S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => '2XL', 'XXXL' => '3XL', '2XL' => '2XL', '3XL' => '3XL', '4XL' => '4XL', '5XL' => '5XL' );
		$upper   = strtoupper( $v );
		return $letters[ $upper ] ?? $v;
	}
	// Slug-like values ("grey-dark", "beige-1", "black") come from Shopify handles: make them readable.
	if ( preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $v ) ) {
		$v = preg_replace( '/-\d+$/', '', $v );
		return ucwords( str_replace( '-', ' ', $v ) );
	}
	return $v;
}

/**
 * Sort rank for a new Size/Color term (sizes sort naturally: 4 < 4.5 < 5 …, S < M < L < XL < 2XL).
 */
function ci_shopify_term_rank( $label, $fallback ) {
	if ( is_numeric( $label ) ) {
		return (int) round( (float) $label * 10 );
	}
	$letters = array( 'XXS' => 1, 'XS' => 2, 'S' => 3, 'M' => 4, 'L' => 5, 'XL' => 6, '2XL' => 7, '3XL' => 8, '4XL' => 9, '5XL' => 10 );
	return $letters[ strtoupper( $label ) ] ?? 1000 + $fallback;
}

/**
 * Normalized plan for one product: options, variants (deduplicated), warnings. No database access.
 *
 * @param array<int,array<string,string>> $rows CSV rows of the product.
 */
function ci_shopify_plan( array $rows ) {
	$main     = $rows[0];
	$variants = array_values( array_filter( $rows, fn( $r ) => '' !== $r['Variant Price'] ) );
	$warnings = array();
	$notes    = array();

	// Options → store attributes (two Shopify options mapping to the same attribute keep the first one).
	$options = array(); // attribute => CSV option index
	for ( $i = 1; $i <= 3; $i++ ) {
		$name = trim( $main[ "Option{$i} Name" ] );
		if ( '' === $name || 'Title' === $name ) {
			continue; // "Title / Default Title" = no options
		}
		$attribute = ci_shopify_attribute_name( $name );
		if ( isset( $options[ $attribute ] ) ) {
			$warnings[] = "options \"{$name}\" and another one both map to {$attribute}; only the first is used";
			continue;
		}
		$options[ $attribute ] = $i;
	}

	// Normalized values per variant.
	$normalized = array();
	foreach ( $variants as $r ) {
		$values = array();
		foreach ( $options as $attribute => $i ) {
			$values[ $attribute ] = ci_shopify_normalize_value( $attribute, $r[ "Option{$i} Value" ] );
		}
		$normalized[] = array( $r, $values );
	}

	// Size fix (see header): metafield handles 37/38/39/40 → 4/5/6/7 and 6.6 → 6.5 in mixed UK/US size lists.
	if ( isset( $options['Size'] ) ) {
		$sizes   = array_unique( array_map( fn( $n ) => $n[1]['Size'], $normalized ) );
		$numbers = array_filter( $sizes, 'is_numeric' );
		$mixed   = array_filter( $numbers, fn( $x ) => (float) $x >= 30 ) && array_filter( $numbers, fn( $x ) => (float) $x < 20 );
		$map     = array( '6.6' => '6.5' );
		if ( $mixed ) {
			$map += array( '37' => '4', '38' => '5', '39' => '6', '40' => '7' );
		}
		$fixed = array();
		foreach ( $map as $from => $to ) {
			if ( ! in_array( (string) $from, $sizes, true ) ) {
				continue;
			}
			if ( in_array( $to, $sizes, true ) ) {
				$warnings[] = "size {$from} kept: {$to} already exists in this product";
				unset( $map[ $from ] );
				continue;
			}
			$fixed[] = "{$from}→{$to}";
		}
		foreach ( $normalized as &$n ) {
			if ( isset( $map[ $n[1]['Size'] ] ) ) {
				$n[1]['Size'] = $map[ $n[1]['Size'] ];
			}
		}
		unset( $n );
		if ( $fixed ) {
			$notes[] = 'sizes fixed: ' . implode( ', ', $fixed );
		}
	}

	// Duplicates after normalizing are dropped.
	$clean = array();
	$seen  = array();
	foreach ( $normalized as list( $r, $values ) ) {
		$key = strtolower( implode( '|', $values ) );
		if ( isset( $seen[ $key ] ) ) {
			$warnings[] = 'duplicate variant after normalizing skipped: ' . implode( ' / ', $values );
			continue;
		}
		$seen[ $key ] = true;
		$clean[]      = array(
			'row'    => $r,
			'values' => $values,
		);
	}

	// Values per attribute (variant order); single-value options are not a real choice.
	$values_by_attribute = array();
	foreach ( $options as $attribute => $i ) {
		$values_by_attribute[ $attribute ] = array_values( array_unique( array_map( fn( $v ) => $v['values'][ $attribute ], $clean ) ) );
		$values_by_attribute[ $attribute ] = array_values( array_filter( $values_by_attribute[ $attribute ], 'strlen' ) );
	}
	$variation_attributes = array_keys( array_filter( $values_by_attribute, fn( $vals ) => count( $vals ) > 1 ) );

	// Sizes that look like EU numbers among UK/US numbers (Shopify metafield handles).
	if ( ! empty( $values_by_attribute['Size'] ) ) {
		$numbers = array_filter( $values_by_attribute['Size'], 'is_numeric' );
		$eu      = array_filter( $numbers, fn( $n ) => (float) $n >= 30 );
		$small   = array_filter( $numbers, fn( $n ) => (float) $n < 20 );
		if ( $eu && $small ) {
			$warnings[] = 'EU-like sizes kept as-is, check them: ' . implode( ', ', $eu );
		}
	}

	return array(
		'main'                 => $main,
		'variants'             => $clean,
		'values'               => $values_by_attribute,
		'variation_attributes' => $variation_attributes,
		'is_variable'          => count( $variation_attributes ) > 0 && count( $clean ) > 1,
		'warnings'             => $warnings,
		'notes'                => $notes,
	);
}

/* ---------------------------------------------------------------------------
 * Writing
 * ------------------------------------------------------------------------ */

/**
 * Global attribute taxonomy (created if missing): Color → pa_color, Size → pa_size.
 */
function ci_shopify_attribute_taxonomy( $label ) {
	$slug = substr( wc_sanitize_taxonomy_name( $label ), 0, 28 );
	$tax  = wc_attribute_taxonomy_name( $slug );
	if ( ! wc_attribute_taxonomy_id_by_name( $tax ) ) {
		$id = wc_create_attribute(
			array(
				'name'     => $label,
				'slug'     => $slug,
				'type'     => 'select',
				'order_by' => 'menu_order',
			)
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( "Attribute {$label}: " . $id->get_error_message() );
		}
	}
	if ( ! taxonomy_exists( $tax ) ) {
		register_taxonomy( $tax, array( 'product' ), array( 'hierarchical' => false ) );
	}
	return $tax;
}

/**
 * Term for a value (case-insensitive match on name, created if missing).
 */
function ci_shopify_term( $taxonomy, $label, $position ) {
	$term = get_term_by( 'name', $label, $taxonomy ); // MySQL collation: case-insensitive
	if ( $term ) {
		return $term;
	}
	$res = wp_insert_term( $label, $taxonomy );
	if ( is_wp_error( $res ) ) {
		$existing = $res->get_error_data( 'term_exists' );
		return $existing ? get_term( $existing, $taxonomy ) : null;
	}
	update_term_meta( $res['term_id'], 'order', ci_shopify_term_rank( $label, $position ) );
	return get_term( $res['term_id'], $taxonomy );
}

/**
 * Attachment for a Shopify image URL (downloaded once, matched by URL without query string).
 */
function ci_shopify_image( $url, $alt, $product_id ) {
	$key   = strtok( $url, '?' );
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_shopify_image_src', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	$id = media_sideload_image( $url, $product_id, $alt ? $alt : null, 'id' );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "  image failed ({$key}): " . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_shopify_image_src', $key );
	if ( $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	return (int) $id;
}

/**
 * Prices, SKU, weight and stock for a product or variation.
 */
function ci_shopify_apply_variant_data( WC_Product $p, array $r ) {
	$price   = $r['Variant Price'];
	$compare = $r['Variant Compare At Price'];
	if ( '' !== $compare && (float) $compare > (float) $price ) {
		$p->set_regular_price( $compare );
		$p->set_sale_price( $price );
	} else {
		$p->set_regular_price( $price );
		$p->set_sale_price( '' );
	}
	$sku = trim( $r['Variant SKU'] );
	if ( '' !== $sku && ! wc_get_product_id_by_sku( $sku ) ) {
		$p->set_sku( $sku );
	}
	$grams = (float) $r['Variant Grams'];
	$p->set_weight( $grams > 0 ? (string) wc_get_weight( $grams, get_option( 'woocommerce_weight_unit', 'kg' ), 'g' ) : '' );
	$p->set_stock_status( 'instock' ); // the product CSV has no stock quantities
}

/**
 * Create or update one product from its plan. Returns the product ID.
 */
function ci_shopify_import( $handle, array $plan, array $rows ) {
	$main     = $plan['main'];
	$existing = get_posts(
		array(
			'post_type'   => 'product',
			'post_status' => 'any',
			'meta_key'    => '_shopify_handle', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $handle, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	$id = $existing ? (int) $existing[0] : 0;
	if ( $id ) {
		$old = wc_get_product( $id );
		if ( $old instanceof WC_Product_Variable ) {
			foreach ( $old->get_children() as $child ) {
				wp_delete_post( $child, true );
			}
		}
		// The type may change (e.g. a duplicate-only variant became a simple product).
		wp_set_object_terms( $id, $plan['is_variable'] ? 'variable' : 'simple', 'product_type' );
	}
	$product = $plan['is_variable'] ? new WC_Product_Variable( $id ) : new WC_Product_Simple( $id );

	$published = 'active' === strtolower( $main['Status'] ) && 'true' === strtolower( $main['Published'] );
	$product->set_name( wp_strip_all_tags( $main['Title'] ) );
	$product->set_slug( sanitize_title( $handle ) );
	$product->set_description( wp_kses_post( $main['Body (HTML)'] ) );
	$product->set_status( $published ? 'publish' : 'draft' );
	$id = $product->save();
	update_post_meta( $id, '_shopify_handle', $handle );

	// Images in Shopify's order: stored for the image step, or downloaded now with CI_IMAGES=1.
	$image_rows = array_values( array_filter( $rows, fn( $r ) => '' !== $r['Image Src'] ) );
	usort( $image_rows, fn( $a, $b ) => (int) $a['Image Position'] <=> (int) $b['Image Position'] );
	update_post_meta(
		$id,
		'_shopify_images',
		array_map(
			fn( $r ) => array(
				'src' => $r['Image Src'],
				'alt' => $r['Image Alt Text'],
			),
			$image_rows
		)
	);
	$images = array();
	if ( CI_SHOPIFY_DOWNLOAD_IMAGES ) {
		foreach ( $image_rows as $r ) {
			$aid = ci_shopify_image( $r['Image Src'], $r['Image Alt Text'], $id );
			if ( $aid ) {
				$images[] = $aid;
			}
		}
	}
	$product = wc_get_product( $id );
	if ( $images ) {
		$product->set_image_id( $images[0] );
		$product->set_gallery_image_ids( array_slice( $images, 1 ) );
	}

	// Attributes: variation attributes for real choices, plain (visible) attributes for single values.
	$attributes = array();
	$terms      = array();
	$position   = 0;
	foreach ( $plan['values'] as $label => $values ) {
		if ( ! $values ) {
			continue;
		}
		$taxonomy = ci_shopify_attribute_taxonomy( $label );
		$ids      = array();
		foreach ( $values as $k => $value ) {
			$term = ci_shopify_term( $taxonomy, $value, $k );
			if ( $term ) {
				$terms[ $label ][ $value ] = $term;
				$ids[]                     = $term->term_id;
			}
		}
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $ids );
		$attribute->set_position( $position++ );
		$attribute->set_visible( true );
		$attribute->set_variation( $plan['is_variable'] && in_array( $label, $plan['variation_attributes'], true ) );
		$attributes[ $label ] = array( $taxonomy, $attribute );
	}
	$product->set_attributes( array_column( $attributes, 1 ) );

	if ( ! $plan['is_variable'] ) {
		ci_shopify_apply_variant_data( $product, $plan['variants'][0]['row'] );
		$product->save();
		return $id;
	}
	$product->save();

	foreach ( $plan['variants'] as $k => $variant ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $id );
		$atts = array();
		foreach ( $plan['variation_attributes'] as $label ) {
			$value = $variant['values'][ $label ];
			if ( isset( $terms[ $label ][ $value ] ) ) {
				$atts[ $attributes[ $label ][0] ] = $terms[ $label ][ $value ]->slug;
			}
		}
		$variation->set_attributes( $atts );
		ci_shopify_apply_variant_data( $variation, $variant['row'] );
		$variation->set_menu_order( $k );
		$variant_image = $variant['row']['Variant Image'];
		if ( '' !== $variant_image && CI_SHOPIFY_DOWNLOAD_IMAGES ) {
			$aid = ci_shopify_image( $variant_image, '', $id );
			if ( $aid ) {
				$variation->set_image_id( $aid );
			}
		}
		$variation_id = $variation->save();
		if ( '' !== $variant_image ) {
			update_post_meta( $variation_id, '_shopify_variant_image', $variant_image );
		}
	}
	WC_Product_Variable::sync( $id );
	wc_delete_product_transients( $id );
	return $id;
}

/* ---------------------------------------------------------------------------
 * Run
 * ------------------------------------------------------------------------ */

$ci_all     = ci_shopify_read_csv( $ci_csv );
$ci_handles = ! empty( $args ) ? array_values( $args ) : array_keys( $ci_all ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
if ( empty( $args ) && $ci_limit > 0 ) {
	$ci_handles = array_slice( $ci_handles, 0, $ci_limit );
}

WP_CLI::log( sprintf( '%s %d of %d products from %s', $ci_dry ? 'DRY RUN:' : 'Importing', count( $ci_handles ), count( $ci_all ), basename( $ci_csv ) ) );
$ci_done = 0;
foreach ( $ci_handles as $handle ) {
	if ( empty( $ci_all[ $handle ] ) ) {
		WP_CLI::warning( "{$handle}: not in the CSV, skipped." );
		continue;
	}
	$plan = ci_shopify_plan( $ci_all[ $handle ] );
	if ( ! $plan['variants'] ) {
		WP_CLI::warning( "{$handle}: no variant rows (no price), skipped." );
		continue;
	}

	$summary = array();
	foreach ( $plan['values'] as $label => $values ) {
		$kind      = in_array( $label, $plan['variation_attributes'], true ) && $plan['is_variable'] ? '' : ' (plain attribute)';
		$summary[] = "{$label}{$kind}: " . implode( ', ', $values );
	}
	$line = sprintf(
		'%s — %s, %d %s, %d images',
		wp_strip_all_tags( $plan['main']['Title'] ),
		$plan['is_variable'] ? 'variable' : 'simple',
		count( $plan['variants'] ),
		$plan['is_variable'] ? 'variations' : 'variant',
		count( array_filter( $ci_all[ $handle ], fn( $r ) => '' !== $r['Image Src'] ) )
	);

	if ( $ci_dry ) {
		WP_CLI::log( "• {$line}" );
	} else {
		$id = ci_shopify_import( $handle, $plan, $ci_all[ $handle ] );
		WP_CLI::success( "{$line} (#{$id})" );
	}
	if ( $summary ) {
		WP_CLI::log( '    ' . implode( ' | ', $summary ) );
	}
	foreach ( $plan['notes'] as $note ) {
		WP_CLI::log( "    {$note}" );
	}
	foreach ( $plan['warnings'] as $warning ) {
		WP_CLI::warning( "  {$handle}: {$warning}" );
	}
	++$ci_done;
}
WP_CLI::log( sprintf( '%s %d product(s).', $ci_dry ? 'Would import' : 'Imported', $ci_done ) );

/* Color/Size terms that no product uses any more (e.g. "39" after the size fix). */
if ( ! $ci_dry ) {
	$removed = array();
	foreach ( array( 'pa_color', 'pa_size' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
			$used = get_posts(
				array(
					'post_type'   => array( 'product', 'product_variation' ),
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
					'tax_query'   => array( array( 'taxonomy' => $taxonomy, 'terms' => $term->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			if ( ! $used ) {
				wp_delete_term( $term->term_id, $taxonomy );
				$removed[] = $term->name;
			}
		}
	}
	if ( $removed ) {
		WP_CLI::log( 'Removed unused Color/Size values: ' . implode( ', ', $removed ) );
	}
}
