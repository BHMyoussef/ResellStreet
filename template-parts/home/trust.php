<?php
/**
 * Trust row: up to 4 items (icon, title, text) from the Customizer.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_trust_enable' ) ) {
	return;
}
$ci_items = array();
for ( $ci_i = 1; $ci_i <= CI_TRUST_ITEMS; $ci_i++ ) {
	$ci_title = trim( (string) ci_mod( "home_trust_{$ci_i}_title" ) );
	$ci_text  = trim( (string) ci_mod( "home_trust_{$ci_i}_text" ) );
	if ( '' !== $ci_title || '' !== $ci_text ) {
		$ci_items[] = array( ci_sanitize_trust_icon( ci_mod( "home_trust_{$ci_i}_icon" ) ), $ci_title, $ci_text );
	}
}
if ( ! $ci_items ) {
	return;
}
?>
<section class="home-trust" aria-label="<?php esc_attr_e( 'Why shop with us', 'customimpulse' ); ?>">
	<div class="home-width">
	<ul class="home-trust__list" role="list">
		<?php foreach ( $ci_items as $ci_item ) : ?>
			<li class="home-trust__item">
				<span class="home-trust__icon"><?php echo ci_icon( $ci_item[0] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<?php if ( $ci_item[1] ) : ?>
					<h3 class="home-trust__title"><?php echo esc_html( $ci_item[1] ); ?></h3>
				<?php endif; ?>
				<?php if ( $ci_item[2] ) : ?>
					<p class="home-trust__text"><?php echo esc_html( $ci_item[2] ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	</div>
</section>
