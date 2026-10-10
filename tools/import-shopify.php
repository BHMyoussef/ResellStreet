<?php
/**
 * Import products from a Shopify product CSV export as WooCommerce products (variable when they have options).
 *
 *   tools/wp eval-file tools/import-shopify.php [handle ...]
 *   npm run import:products -- [handle ...]
 *
 * Without handles, the 5 default products below are imported.
 * Env: CI_SHOPIFY_CSV (default reference/products_export_1.csv), CI_SHOPIFY_STORE (default https://novarende.com,
 *      empty to skip the live label lookup).
 *
 * Mapping
 * - Title, Body (HTML) → name, description (wp_kses_post). Status active + Published → publish, else draft.
 * - Product Category "Apparel & Accessories > Shoes > Sneakers" → product_cat Shoes › Sneakers (top level + leaf).
 * - Tags → product_tag. SEO Title/Description → meta _shopify_seo_title / _shopify_seo_description.
 * - Options → global attributes (pa_*), each variant row → variation. Price → sale price, Compare At → regular price.
 *   SKU (if unique), weight (Variant Grams → store weight unit), Variant Image → variation image.
 *   The CSV has no stock quantities: variants are imported "in stock".
 * - Images: downloaded once (meta _shopify_image_src), Image Alt Text kept, first = featured, rest = gallery.
 * - Option values linked to Shopify category metafields are exported as metaobject handles ("39", "7-5", "beige-1").
 *   Labels come from the live store's /products/<handle>.js (same variant order as the CSV), else a cleanup rule.
 * - Idempotent: products are matched by meta _shopify_handle and their variations rebuilt.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$ci_default_handles = array(
	'mens-casual-leather-sneakers-1',            // Flaireve – Men's Casual Leather Sneakers (live reference product)
	'retro-style-low-top-sneakers-orange-white', // size only
	'calzado-deportivo-transpirable',            // SpringFlow: Color + Size, unlinked options
	'men-s-ribbed-knit-zip-up-sweater',          // Color + Size, 42 variations (AJAX mode)
	'pretoconto',                                // Varsity Bomber Jacket: Color + Size
);

$ci_csv   = getenv( 'CI_SHOPIFY_CSV' ) ? getenv( 'CI_SHOPIFY_CSV' ) : get_theme_file_path( 'reference/products_export_1.csv' );
$ci_store = false === getenv( 'CI_SHOPIFY_STORE' ) ? 'https://novarende.com' : rtrim( (string) getenv( 'CI_SHOPIFY_STORE' ), '/' );
$ci_wants = ! empty( $args ) ? array_values( $args ) : $ci_default_handles; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

if ( ! file_exists( $ci_csv ) ) {
	WP_CLI::error( "CSV not found: {$ci_csv}" );
}

/* ---------------------------------------------------------------------------
 * CSV
 * ------------------------------------------------------------------------ */

/**
 * Rows grouped by handle (only the requested handles).
 *
 * @return array<string,array<int,array<string,string>>>
 */
function ci_shopify_rows( $csv, array $handles ) {
	$fh     = fopen( $csv, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$header = fgetcsv( $fh );
	$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] ); // BOM
	$out    = array();
	while ( ( $row = fgetcsv( $fh ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		if ( count( $row ) !== count( $header ) ) {
			continue;
		}
		$row = array_combine( $header, $row );
		if ( in_array( $row['Handle'], $handles, true ) ) {
			$out[ $row['Handle'] ][] = $row;
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $out;
}

/* ---------------------------------------------------------------------------
 * Option labels
 * ------------------------------------------------------------------------ */

/**
 * Handle → label per option index, from the live store's product JSON (same variant order as the CSV).
 *
 * @return array<int,array<string,string>>|null Null when unavailable or inconsistent.
 */
function ci_shopify_live_labels( $store, $handle, array $variants, $option_count ) {
	if ( ! $store ) {
		return null;
	}
	$res = wp_remote_get( $store . '/products/' . rawurlencode( $handle ) . '.js', array( 'timeout' => 20 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $json['variants'] ) || count( $json['variants'] ) !== count( $variants ) ) {
		return null;
	}
	$map = array();
	foreach ( $variants as $k => $row ) {
		for ( $i = 1; $i <= $option_count; $i++ ) {
			$raw   = $row[ "Option{$i} Value" ];
			$label = (string) ( $json['variants'][ $k ][ "option{$i}" ] ?? '' );
			if ( isset( $map[ $i ][ $raw ] ) && $map[ $i ][ $raw ] !== $label ) {
				return null; // Order mismatch: don't guess.
			}
			$map[ $i ][ $raw ] = $label;
		}
	}
	return $map;
}

/**
 * Readable label for a metaobject-handle-like value ("7-5" → "7.5", "beige-1" → "Beige", "2xl" → "2XL").
 */
function ci_shopify_pretty_label( $value, $option_name ) {
	$v = trim( $value );
	if ( preg_match( '/^(\d+)-5$/', $v, $m ) ) {
		return $m[1] . '.5';
	}
	if ( preg_match( '/^(\d?x{0,3}[sml]|\d?xl|xxs|xs)$/i', $v ) && false !== stripos( $option_name, 'size' ) ) {
		return strtoupper( $v );
	}
	$v = preg_replace( '/-\d+$/', '', $v );
	return ucwords( str_replace( '-', ' ', $v ) );
}

/* ---------------------------------------------------------------------------
 * Taxonomies
 * ------------------------------------------------------------------------ */

/**
 * Global attribute taxonomy for an option name (created if missing). Returns the taxonomy name.
 */
function ci_shopify_attribute( $option_name ) {
	$label = trim( $option_name );
	$slug  = wc_sanitize_taxonomy_name( $label );
	$slug  = substr( $slug, 0, 28 );
	$tax   = wc_attribute_taxonomy_name( $slug );
	if ( ! wc_attribute_taxonomy_id_by_name( $tax ) ) {
		$id = wc_create_attribute(
			array(
				'name'     => ucfirst( $label ),
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
 * Sort rank for a new attribute term (sizes sort naturally: 5 < 5.5 < 6 …, S < M < L < XL < 2XL).
 */
function ci_shopify_term_rank( $label, $fallback ) {
	if ( is_numeric( $label ) ) {
		return (int) round( (float) $label * 10 );
	}
	$letters = array( 'XXS' => 1, 'XS' => 2, 'S' => 3, 'M' => 4, 'L' => 5, 'XL' => 6, 'XXL' => 7, '2XL' => 7, 'XXXL' => 8, '3XL' => 8, '4XL' => 9, '5XL' => 10 );
	$upper   = strtoupper( $label );
	return isset( $letters[ $upper ] ) ? $letters[ $upper ] : 1000 + $fallback;
}

/**
 * Term (created if missing) for an attribute label.
 */
function ci_shopify_term( $taxonomy, $label, $position ) {
	$term = get_term_by( 'name', $label, $taxonomy );
	if ( ! $term ) {
		$res = wp_insert_term( $label, $taxonomy );
		if ( is_wp_error( $res ) ) {
			$existing = $res->get_error_data( 'term_exists' );
			$term     = $existing ? get_term( $existing, $taxonomy ) : null;
		} else {
			$term = get_term( $res['term_id'], $taxonomy );
			update_term_meta( $term->term_id, 'order', ci_shopify_term_rank( $label, $position ) );
		}
	}
	return $term;
}

/**
 * Product category from the Shopify taxonomy path (top level + leaf), matching existing terms by name.
 */
function ci_shopify_category( $path, $title, array $option_names ) {
	$parts = array_values( array_filter( array_map( 'trim', explode( '>', (string) $path ) ) ) );
	if ( $parts && 'Apparel & Accessories' === $parts[0] ) {
		array_shift( $parts );
	}
	if ( ! $parts || 'Uncategorized' === $parts[0] ) {
		// Shopify left it uncategorized: shoes are recognisable from their options or title.
		$is_shoe = preg_match( '/sneaker|trainer|shoe|sandal|slide|boot/i', $title ) || preg_grep( '/shoe/i', $option_names );
		$parts   = $is_shoe ? array( 'Shoes' ) : array();
	}
	if ( ! $parts ) {
		return array();
	}
	$chain  = count( $parts ) > 1 ? array( $parts[0], end( $parts ) ) : array( $parts[0] );
	$parent = 0;
	$ids    = array();
	foreach ( $chain as $name ) {
		$found = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'name'       => $name,
				'parent'     => $parent,
				'hide_empty' => false,
				'number'     => 1,
			)
		);
		if ( $found ) {
			$term_id = $found[0]->term_id;
		} else {
			$res     = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
			$term_id = is_wp_error( $res ) ? (int) $res->get_error_data( 'term_exists' ) : $res['term_id'];
		}
		$ids[]  = $term_id;
		$parent = $term_id;
	}
	return $ids;
}

/* ---------------------------------------------------------------------------
 * Images
 * ------------------------------------------------------------------------ */

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

/* ---------------------------------------------------------------------------
 * Import
 * ------------------------------------------------------------------------ */

$ci_all = ci_shopify_rows( $ci_csv, $ci_wants );

foreach ( $ci_wants as $handle ) {
	if ( empty( $ci_all[ $handle ] ) ) {
		WP_CLI::warning( "{$handle}: not in the CSV, skipped." );
		continue;
	}
	$rows     = $ci_all[ $handle ];
	$main     = $rows[0];
	$variants = array_values( array_filter( $rows, fn( $r ) => '' !== $r['Variant Price'] ) );
	$names    = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		if ( '' !== $main[ "Option{$i} Name" ] && 'Title' !== $main[ "Option{$i} Name" ] ) {
			$names[ $i ] = $main[ "Option{$i} Name" ];
		}
	}
	$is_variable = $names && count( $variants ) > 1;

	// Labels: live store lookup, else the cleanup rule.
	$labels = $names ? ci_shopify_live_labels( $ci_store, $handle, $variants, max( array_keys( $names ) ) ) : array();
	$source = $labels ? 'live store' : 'cleanup rule';
	$label  = function ( $i, $raw ) use ( &$labels, $names ) {
		return ! empty( $labels[ $i ][ $raw ] ) ? $labels[ $i ][ $raw ] : ci_shopify_pretty_label( $raw, $names[ $i ] );
	};

	// Product (matched by Shopify handle).
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
	$id      = $existing ? (int) $existing[0] : 0;
	$product = $is_variable ? new WC_Product_Variable( $id ) : new WC_Product_Simple( $id );
	if ( $id && $product instanceof WC_Product_Variable ) {
		foreach ( $product->get_children() as $child ) {
			wp_delete_post( $child, true );
		}
	}
	$published = 'active' === strtolower( $main['Status'] ) && 'true' === strtolower( $main['Published'] );
	$product->set_name( wp_strip_all_tags( $main['Title'] ) );
	$product->set_slug( sanitize_title( $handle ) );
	$product->set_description( wp_kses_post( $main['Body (HTML)'] ) );
	$product->set_status( $published ? 'publish' : 'draft' );
	$product->set_category_ids( ci_shopify_category( $main['Product Category'], $main['Title'], $names ) );
	$product->set_tag_ids(
		array_filter(
			array_map(
				function ( $tag ) {
					$tag = trim( $tag );
					if ( '' === $tag ) {
						return 0;
					}
					$term = term_exists( $tag, 'product_tag' );
					$term = $term ? $term : wp_insert_term( $tag, 'product_tag' );
					return is_wp_error( $term ) ? 0 : (int) $term['term_id'];
				},
				explode( ',', $main['Tags'] )
			)
		)
	);
	$id = $product->save();
	update_post_meta( $id, '_shopify_handle', $handle );
	if ( $main['SEO Title'] ) {
		update_post_meta( $id, '_shopify_seo_title', sanitize_text_field( $main['SEO Title'] ) );
	}
	if ( $main['SEO Description'] ) {
		update_post_meta( $id, '_shopify_seo_description', sanitize_textarea_field( $main['SEO Description'] ) );
	}

	// Images in Shopify's order.
	$image_rows = array_filter( $rows, fn( $r ) => '' !== $r['Image Src'] );
	usort( $image_rows, fn( $a, $b ) => (int) $a['Image Position'] <=> (int) $b['Image Position'] );
	$image_ids = array();
	foreach ( $image_rows as $r ) {
		$aid = ci_shopify_image( $r['Image Src'], $r['Image Alt Text'], $id );
		if ( $aid ) {
			$image_ids[] = $aid;
		}
	}
	$product = wc_get_product( $id );
	if ( $image_ids ) {
		$product->set_image_id( $image_ids[0] );
		$product->set_gallery_image_ids( array_slice( $image_ids, 1 ) );
	}

	$weight = function ( $grams ) {
		$grams = (float) $grams;
		return $grams > 0 ? (string) wc_get_weight( $grams, get_option( 'woocommerce_weight_unit', 'kg' ), 'g' ) : '';
	};
	$prices = function ( WC_Product $p, array $r ) {
		$price   = $r['Variant Price'];
		$compare = $r['Variant Compare At Price'];
		if ( '' !== $compare && (float) $compare > (float) $price ) {
			$p->set_regular_price( $compare );
			$p->set_sale_price( $price );
		} else {
			$p->set_regular_price( $price );
			$p->set_sale_price( '' );
		}
	};
	$sku = function ( WC_Product $p, $value ) {
		$value = trim( (string) $value );
		if ( '' !== $value && ! wc_get_product_id_by_sku( $value ) ) {
			$p->set_sku( $value );
		}
	};

	if ( ! $is_variable ) {
		$prices( $product, $variants[0] );
		$sku( $product, $variants[0]['Variant SKU'] );
		$product->set_weight( $weight( $variants[0]['Variant Grams'] ) );
		$product->set_stock_status( 'instock' );
		$product->save();
		WP_CLI::success( sprintf( '%s (#%d): simple product, %d images', $product->get_name(), $id, count( $image_ids ) ) );
		continue;
	}

	// Attributes from the options (labels in variant order).
	$attributes = array();
	$terms      = array();
	$position   = 0;
	foreach ( $names as $i => $name ) {
		$taxonomy = ci_shopify_attribute( $name );
		$options  = array();
		foreach ( $variants as $k => $r ) {
			$value = $label( $i, $r[ "Option{$i} Value" ] );
			if ( '' === $value || isset( $terms[ $i ][ $value ] ) ) {
				continue;
			}
			$term = ci_shopify_term( $taxonomy, $value, $k );
			if ( $term ) {
				$terms[ $i ][ $value ] = $term;
				$options[]             = $term->term_id;
			}
		}
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $options );
		$attribute->set_position( $position++ );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$attributes[ $i ] = array( $taxonomy, $attribute );
	}
	$product->set_attributes( array_column( $attributes, 1 ) );
	$product->save();

	// Variations.
	foreach ( $variants as $k => $r ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $id );
		$atts = array();
		foreach ( $attributes as $i => list( $taxonomy ) ) {
			$value = $label( $i, $r[ "Option{$i} Value" ] );
			if ( isset( $terms[ $i ][ $value ] ) ) {
				$atts[ $taxonomy ] = $terms[ $i ][ $value ]->slug;
			}
		}
		$variation->set_attributes( $atts );
		$prices( $variation, $r );
		$sku( $variation, $r['Variant SKU'] );
		$variation->set_weight( $weight( $r['Variant Grams'] ) );
		$variation->set_stock_status( 'instock' ); // no quantities in Shopify's product CSV
		$variation->set_menu_order( $k );
		if ( '' !== $r['Variant Image'] ) {
			$aid = ci_shopify_image( $r['Variant Image'], '', $id );
			if ( $aid ) {
				$variation->set_image_id( $aid );
			}
		}
		$variation->save();
	}
	WC_Product_Variable::sync( $id );
	wc_delete_product_transients( $id );

	$summary = array();
	foreach ( $names as $i => $name ) {
		$summary[] = $name . ': ' . implode( ', ', array_keys( $terms[ $i ] ?? array() ) );
	}
	WP_CLI::success( sprintf( '%s (#%d): %d variations, %d images, labels from %s', wc_get_product( $id )->get_name(), $id, count( $variants ), count( $image_ids ), $source ) );
	WP_CLI::log( '  ' . implode( ' | ', $summary ) );
}
