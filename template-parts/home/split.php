<?php
/**
 * Image with text ("It's a mindset."): image on one side, text on light sand; stacked on phones.
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
			<?php
			echo wp_get_attachment_image(
				$ci_image,
				'full',
				false,
				array(
					'class'   => 'home-split__img',
					'sizes'   => '(min-width: 769px) 54vw, 100vw',
					'loading' => 'lazy',
					'alt'     => '',
					'style'   => 'object-position:' . ci_sanitize_focal( ci_mod( 'home_split_focal' ) ),
				)
			);
			?>
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
