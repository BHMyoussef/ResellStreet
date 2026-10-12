<?php
/**
 * Trending styles: products with the trending tag, one tab per category (tab bar from 2 tabs).
 * The first tab is rendered here; others load from REST ci/v1/trending/<cat> on click (home.js).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_trending_enable' ) || ! function_exists( 'wc_get_template_part' ) ) {
	return;
}
$ci_tabs = ci_home_trending_tabs();
if ( ! $ci_tabs ) {
	return;
}
$ci_html = ci_home_trending_html( $ci_tabs[0]['cat'] );
if ( '' === $ci_html ) {
	return;
}
$ci_url = (string) ci_mod( 'home_trending_btn_url' );
if ( '' === $ci_url ) {
	$ci_tag = get_term_by( 'slug', sanitize_title( (string) ci_mod( 'home_trending_tag' ) ), 'product_tag' );
	$ci_url = $ci_tag ? (string) get_term_link( $ci_tag ) : '';
}
$ci_has_tabs = count( $ci_tabs ) > 1;
?>
<section class="home-section home-trending" aria-labelledby="home-trending-title" data-trending="<?php echo esc_url( rest_url( 'ci/v1/trending/' ) ); ?>">
	<div class="home-width">
		<?php ci_home_heading( 'home_trending', array( 'id' => 'home-trending-title', 'center' => true ) ); ?>
		<?php if ( $ci_has_tabs ) : ?>
			<div class="home-tabs" role="tablist" aria-labelledby="home-trending-title">
				<?php foreach ( $ci_tabs as $ci_i => $ci_tab ) : ?>
					<button type="button" class="home-tabs__tab" role="tab" id="trending-tab-<?php echo esc_attr( $ci_tab['cat'] ); ?>" aria-controls="trending-panel" aria-selected="<?php echo 0 === $ci_i ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $ci_i ? '0' : '-1'; ?>" data-trending-cat="<?php echo esc_attr( $ci_tab['cat'] ); ?>"><?php echo esc_html( $ci_tab['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="home-trending__panel" id="trending-panel"<?php echo $ci_has_tabs ? ' role="tabpanel" aria-labelledby="trending-tab-' . esc_attr( $ci_tabs[0]['cat'] ) . '"' : ''; ?> data-trending-panel>
			<?php echo $ci_html; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce card templates. ?>
		</div>
		<?php echo ci_home_buttons( array( array( ci_mod( 'home_trending_btn_label' ), $ci_url, '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
</section>
