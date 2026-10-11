<?php
/**
 * Shoe size guide on the "Shoes" category (term meta ci_size_guide), shown on every product under Shoes.
 * Editable afterwards in Products → Categories → Shoes. Re-running overwrites it.
 *
 *   tools/wp eval-file tools/size-guide.php
 *
 * Sizes = every UK size the shoes are sold in (3.5–11.5); EU and foot length follow the common unisex sneaker chart.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$shoes = get_term_by( 'slug', 'shoes', 'product_cat' );
if ( ! $shoes ) {
	WP_CLI::error( 'Category "shoes" not found.' );
}

// UK, EU, foot length (cm).
$rows = array(
	array( '3.5', '36', '22.5' ),
	array( '4', '37', '22.9' ),
	array( '4.5', '37.5', '23.3' ),
	array( '5', '38', '23.8' ),
	array( '5.5', '38.5', '24.2' ),
	array( '6', '39', '24.6' ),
	array( '6.5', '40', '25.1' ),
	array( '7', '40.5', '25.5' ),
	array( '7.5', '41', '25.9' ),
	array( '8', '42', '26.3' ),
	array( '8.5', '42.5', '26.7' ),
	array( '9', '43', '27.1' ),
	array( '9.5', '44', '27.6' ),
	array( '10', '44.5', '28.0' ),
	array( '10.5', '45', '28.4' ),
	array( '11', '46', '28.8' ),
	array( '11.5', '46.5', '29.3' ),
);

$html  = '<p>Our sizes are <strong>UK sizes</strong>. Find yours in the first column, or measure your foot and match the length.</p>';
$html .= '<table><thead><tr><th>UK</th><th>EU</th><th>Foot length (cm)</th></tr></thead><tbody>';
foreach ( $rows as $r ) {
	$html .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
}
$html .= '</tbody></table>';
$html .= '<h3>How to measure</h3><ol>';
$html .= '<li>Stand on a sheet of paper with your heel against a wall.</li>';
$html .= '<li>Mark the tip of your longest toe and measure from the wall to the mark.</li>';
$html .= '<li>Match the length to the table. Between two sizes? Choose the larger one.</li>';
$html .= '</ol>';
$html .= '<p>Fit can vary slightly between brands and models. Not sure? Contact us before ordering and we\'ll help you choose.</p>';

update_term_meta( $shoes->term_id, 'ci_size_guide', $html );
WP_CLI::success( "Size guide saved on \"{$shoes->name}\" (#{$shoes->term_id})." );
