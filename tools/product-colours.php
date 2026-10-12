<?php
/**
 * Colour data for the product page swatches (global attribute pa_color, swatch = term meta ci_swatch: "#hex" or
 * "#hex1,#hex2" for two-tone colourways). Run through tools/product-colours.mjs:
 *
 *   tools/wp eval-file tools/product-colours.php export > list.json   products without a colour: colourway from the
 *                                                                       title ("… – White/Indigo") + photo URL
 *   tools/wp eval-file tools/product-colours.php import list.json      create/assign terms, set swatch hex
 *
 * Re-running only touches products that still have no pa_color, and never overwrites a swatch set in the admin.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_mode = $args[0] ?? '';
$ci_tax  = 'pa_color';
if ( ! taxonomy_exists( $ci_tax ) ) {
	WP_CLI::error( 'The Color attribute (pa_color) does not exist.' );
}

/**
 * Colourway from a product title: the part after the last " – " / " - " (None if the title has no separator).
 *
 * @param string $title Product title.
 */
function ci_colourway_from_title( $title ) {
	return preg_match( '/\s[–-]\s([^–-]+)$/u', $title, $m ) ? trim( $m[1] ) : '';
}

if ( 'export' === $ci_mode ) {
	$out = array();
	foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) ) as $p ) {
		if ( 0 === strpos( (string) $p->get_sku(), 'CI-' ) || isset( $p->get_attributes()[ $ci_tax ] ) ) {
			continue; // test fixtures; products that already have a colour
		}
		$colour = ci_colourway_from_title( $p->get_name() );
		$image  = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), 'medium' ) : '';
		if ( '' === $colour || ! $image ) {
			continue;
		}
		$out[] = array(
			'id'     => $p->get_id(),
			'title'  => $p->get_name(),
			'colour' => $colour,
			'image'  => $image,
		);
	}
	echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return;
}

if ( 'import' === $ci_mode ) {
	$list = json_decode( (string) file_get_contents( $args[1] ?? '' ), true );
	if ( ! is_array( $list ) ) {
		WP_CLI::error( 'Pass the JSON file written by tools/product-colours.mjs.' );
	}
	$attribute_id = wc_attribute_taxonomy_id_by_name( $ci_tax );
	$terms        = 0;
	$assigned     = 0;
	foreach ( $list as $row ) {
		$product = wc_get_product( (int) $row['id'] );
		if ( ! $product || isset( $product->get_attributes()[ $ci_tax ] ) || empty( $row['swatch'] ) ) {
			continue;
		}
		$term = get_term_by( 'name', $row['colour'], $ci_tax );
		if ( ! $term ) {
			$res = wp_insert_term( $row['colour'], $ci_tax );
			if ( is_wp_error( $res ) ) {
				WP_CLI::warning( "{$row['colour']}: " . $res->get_error_message() );
				continue;
			}
			$term = get_term( $res['term_id'], $ci_tax );
			++$terms;
		}
		if ( '' === (string) get_term_meta( $term->term_id, 'ci_swatch', true ) ) {
			update_term_meta( $term->term_id, 'ci_swatch', $row['swatch'] );
		}
		// Plain attribute (not used for variations), shown first.
		$attributes = $product->get_attributes();
		$a          = new WC_Product_Attribute();
		$a->set_id( $attribute_id );
		$a->set_name( $ci_tax );
		$a->set_options( array( $term->term_id ) );
		$a->set_position( 0 );
		$a->set_visible( true );
		$a->set_variation( false );
		$product->set_attributes( array( $ci_tax => $a ) + $attributes );
		$product->save();
		++$assigned;
	}
	WP_CLI::success( "{$assigned} products got a colour ({$terms} new colour terms)." );
	return;
}

WP_CLI::error( 'Usage: tools/wp eval-file tools/product-colours.php export|import <file>' );
