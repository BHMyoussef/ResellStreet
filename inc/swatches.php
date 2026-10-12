<?php
/**
 * Colour swatches: hex per term of the global Colour attribute (pa_color), term meta `ci_swatch`
 * = "#hex" or "#hex1,#hex2" (two-tone). Filled from product photos by tools/product-colours.mjs; editable in
 * Products → Attributes → Color → Configure terms.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

const CI_COLOUR_TAXONOMY = 'pa_color';

/**
 * Swatch colours of a colour term.
 *
 * @param int $term_id pa_color term ID.
 * @return string[] 0–2 hex colours.
 */
function ci_swatch_colours( $term_id ) {
	$hexes = array_filter( array_map( 'sanitize_hex_color', explode( ',', (string) get_term_meta( $term_id, 'ci_swatch', true ) ) ) );
	return array_slice( array_values( $hexes ), 0, 2 );
}

/**
 * CSS background for a swatch: plain colour, or a diagonal split for two-tone colourways.
 *
 * @param int $term_id pa_color term ID.
 */
function ci_swatch_css( $term_id ) {
	$c = ci_swatch_colours( $term_id );
	if ( ! $c ) {
		return '';
	}
	return 2 === count( $c ) ? "linear-gradient(135deg,{$c[0]} 50%,{$c[1]} 50%)" : $c[0];
}

/* Admin: two colour pickers on colour terms (add + edit). */
$ci_swatch_fields = function ( $term = null ) {
	$c       = $term instanceof WP_Term ? ci_swatch_colours( $term->term_id ) : array();
	$inputs  = '<input type="color" name="ci_swatch[]" value="' . esc_attr( $c[0] ?? '#cccccc' ) . '"> ';
	$inputs .= '<label><input type="checkbox" name="ci_swatch_two"' . checked( isset( $c[1] ), true, false ) . '> ' . esc_html__( 'Two-tone:', 'customimpulse' ) . '</label> ';
	$inputs .= '<input type="color" name="ci_swatch[]" value="' . esc_attr( $c[1] ?? '#ffffff' ) . '">';
	$help    = esc_html__( 'Colour shown in the product page swatch. Two-tone = diagonal split (e.g. White/Indigo).', 'customimpulse' );
	if ( $term instanceof WP_Term ) {
		printf( '<tr class="form-field"><th scope="row">%s</th><td>%s<p class="description">%s</p></td></tr>', esc_html__( 'Swatch', 'customimpulse' ), $inputs, $help ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		printf( '<div class="form-field"><label>%s</label>%s<p>%s</p></div>', esc_html__( 'Swatch', 'customimpulse' ), $inputs, $help ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
};
add_action( CI_COLOUR_TAXONOMY . '_add_form_fields', $ci_swatch_fields );
add_action( CI_COLOUR_TAXONOMY . '_edit_form_fields', $ci_swatch_fields );

$ci_swatch_save = function ( $term_id ) {
	// Core verified the term form nonce before firing created_/edited_ hooks.
	if ( ! isset( $_POST['ci_swatch'] ) || ! current_user_can( 'manage_product_terms' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$hexes = array_map( 'sanitize_hex_color', (array) wp_unslash( $_POST['ci_swatch'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$keep  = empty( $_POST['ci_swatch_two'] ) ? array_slice( $hexes, 0, 1 ) : array_slice( $hexes, 0, 2 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_term_meta( $term_id, 'ci_swatch', implode( ',', array_filter( $keep ) ) );
};
add_action( 'created_' . CI_COLOUR_TAXONOMY, $ci_swatch_save );
add_action( 'edited_' . CI_COLOUR_TAXONOMY, $ci_swatch_save );

/* Terms list: a swatch column. */
add_filter( 'manage_edit-' . CI_COLOUR_TAXONOMY . '_columns', fn( $cols ) => array_slice( $cols, 0, 1, true ) + array( 'ci_swatch' => __( 'Swatch', 'customimpulse' ) ) + array_slice( $cols, 1, null, true ) );
add_filter(
	'manage_' . CI_COLOUR_TAXONOMY . '_custom_column',
	function ( $content, $column, $term_id ) {
		if ( 'ci_swatch' !== $column ) {
			return $content;
		}
		$css = ci_swatch_css( $term_id );
		return $css ? '<span style="display:inline-block;width:24px;height:24px;border:1px solid #ccc;background:' . esc_attr( $css ) . '"></span>' : '—';
	},
	10,
	3
);
