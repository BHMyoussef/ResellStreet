<?php
/**
 * Hero: full-bleed image (the LCP: preloaded, eager, fetchpriority=high), navy overlay, text bottom-left.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_image = absint( ci_mod( 'home_hero_image' ) );
$ci_title = (string) ci_mod( 'home_hero_title' );
if ( ! ci_mod( 'home_hero_enable' ) || ( ! $ci_image && '' === $ci_title ) ) {
	return;
}
$ci_style = sprintf(
	'--hero-h:%dpx;--hero-h-mobile:%dpx;--overlay:%s',
	max( 400, absint( ci_mod( 'home_hero_height' ) ) ),
	max( 300, absint( ci_mod( 'home_hero_height_mobile' ) ) ),
	min( 90, absint( ci_mod( 'home_hero_overlay' ) ) ) / 100
);
?>
<section class="home-hero" style="<?php echo esc_attr( $ci_style ); ?>"<?php echo $ci_title ? ' aria-labelledby="home-hero-title"' : ''; ?>>
	<?php
	if ( $ci_image ) {
		echo ci_home_picture( $ci_image, absint( ci_mod( 'home_hero_image_mobile' ) ), (string) ci_mod( 'home_hero_focal' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
	<div class="home-hero__content home-width">
		<?php if ( ci_mod( 'home_hero_eyebrow' ) ) : ?>
			<p class="home-eyebrow home-eyebrow--light"><?php echo esc_html( ci_mod( 'home_hero_eyebrow' ) ); ?></p>
		<?php endif; ?>
		<?php if ( $ci_title ) : ?>
			<h2 class="home-hero__title" id="home-hero-title"><?php echo esc_html( $ci_title ); ?></h2>
		<?php endif; ?>
		<?php if ( ci_mod( 'home_hero_text' ) ) : ?>
			<p class="home-hero__text"><?php echo esc_html( ci_mod( 'home_hero_text' ) ); ?></p>
		<?php endif; ?>
		<?php
		echo ci_home_buttons( // phpcs:ignore WordPress.Security.EscapeOutput
			array(
				array( ci_mod( 'home_hero_btn1_label' ), ci_mod( 'home_hero_btn1_url' ), '' ),
				array( ci_mod( 'home_hero_btn2_label' ), ci_mod( 'home_hero_btn2_url' ), 'btn--light' ),
			)
		);
		?>
	</div>
</section>
