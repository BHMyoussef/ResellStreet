<?php
/**
 * Image with text: image on one side, text on the light accent band; stacked on phones.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_image = absint( ci_mod( 'home_split_image' ) );
$ci_title = (string) ci_mod( 'home_split_title' );
$ci_text  = (string) ci_mod( 'home_split_text' );
if ( ! ci_mod( 'home_split_enable' ) || ( ! $ci_image && '' === $ci_title && '' === $ci_text ) ) {
	return;
}
?>
<section class="home-split home-split--<?php echo esc_attr( ci_sanitize_side( ci_mod( 'home_split_side' ) ) ); ?> home-section--alt"<?php echo $ci_title ? ' aria-labelledby="home-split-title"' : ''; ?>>
	<?php if ( $ci_image ) : ?>
		<div class="home-split__media">
			<?php echo ci_home_picture( $ci_image, absint( ci_mod( 'home_split_image_mobile' ) ), (string) ci_mod( 'home_split_focal' ), false, '54vw' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	<?php endif; ?>
	<div class="home-split__body">
		<div class="home-split__text">
			<?php if ( ci_mod( 'home_split_eyebrow' ) ) : ?>
				<p class="home-eyebrow"><?php echo esc_html( ci_mod( 'home_split_eyebrow' ) ); ?></p>
			<?php endif; ?>
			<?php if ( $ci_title ) : ?>
				<h2 class="home-split__title" id="home-split-title"><?php echo esc_html( $ci_title ); ?></h2>
			<?php endif; ?>
			<?php if ( $ci_text ) : ?>
				<div class="home-split__copy"><?php echo wp_kses_post( wpautop( $ci_text ) ); ?></div>
			<?php endif; ?>
			<?php echo ci_home_buttons( array( array( ci_mod( 'home_split_btn_label' ), ci_mod( 'home_split_btn_url' ), '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</div>
</section>
