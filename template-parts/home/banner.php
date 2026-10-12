<?php
/**
 * Full-width banner ("Wear what moves the street."): image + navy overlay, centred text and button.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_image = absint( ci_mod( 'home_banner_image' ) );
$ci_title = (string) ci_mod( 'home_banner_title' );
if ( ! ci_mod( 'home_banner_enable' ) || ( ! $ci_image && '' === $ci_title ) ) {
	return;
}
$ci_style = sprintf(
	'--banner-h:%dpx;--overlay:%s',
	max( 300, absint( ci_mod( 'home_banner_height' ) ) ),
	min( 90, absint( ci_mod( 'home_banner_overlay' ) ) ) / 100
);
?>
<section class="home-banner" style="<?php echo esc_attr( $ci_style ); ?>"<?php echo $ci_title ? ' aria-labelledby="home-banner-title"' : ''; ?>>
	<?php
	if ( $ci_image ) {
		echo ci_home_picture( $ci_image, absint( ci_mod( 'home_banner_image_mobile' ) ), (string) ci_mod( 'home_banner_focal' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
	<div class="home-banner__content home-width">
		<?php if ( ci_mod( 'home_banner_eyebrow' ) ) : ?>
			<p class="home-eyebrow home-eyebrow--light"><?php echo esc_html( ci_mod( 'home_banner_eyebrow' ) ); ?></p>
		<?php endif; ?>
		<?php if ( $ci_title ) : ?>
			<h2 class="home-banner__title" id="home-banner-title"><?php echo esc_html( $ci_title ); ?></h2>
		<?php endif; ?>
		<?php if ( ci_mod( 'home_banner_text' ) ) : ?>
			<p class="home-banner__text"><?php echo esc_html( ci_mod( 'home_banner_text' ) ); ?></p>
		<?php endif; ?>
		<?php echo ci_home_buttons( array( array( ci_mod( 'home_banner_btn_label' ), ci_mod( 'home_banner_btn_url' ), 'btn--light' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
</section>
