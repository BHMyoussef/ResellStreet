<?php
/**
 * Image with text: full-width background image with a white text box.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_story_enable' ) ) {
	return;
}
$ci_image    = absint( ci_mod( 'home_story_image' ) );
$ci_subtitle = (string) ci_mod( 'home_story_subtitle' );
$ci_title    = (string) ci_mod( 'home_story_title' );
$ci_text     = (string) ci_mod( 'home_story_text' );
$ci_label    = (string) ci_mod( 'home_story_btn_label' );
$ci_url      = (string) ci_mod( 'home_story_btn_url' );
if ( ! $ci_image && ! $ci_title && ! $ci_text ) {
	return;
}
$ci_layout = ci_sanitize_align( ci_mod( 'home_story_layout' ) );
?>
<section class="story story--<?php echo esc_attr( $ci_layout ); ?><?php echo ci_mod( 'home_story_parallax' ) ? ' story--parallax' : ''; ?>" style="--story-h:<?php echo esc_attr( max( 300, absint( ci_mod( 'home_story_height' ) ) ) ); ?>px">
	<?php if ( $ci_image ) : ?>
		<div class="story__media" data-parallax>
			<?php
			echo wp_get_attachment_image(
				$ci_image,
				'full',
				false,
				array(
					'class'   => 'story__img',
					'sizes'   => '100vw',
					'loading' => 'lazy',
					'alt'     => '',
					'style'   => 'object-position:' . ci_sanitize_focal( ci_mod( 'home_story_focal' ) ),
				)
			);
			?>
		</div>
	<?php endif; ?>
	<div class="story__box">
		<?php if ( $ci_subtitle ) : ?>
			<p class="story__subtitle"><?php echo esc_html( $ci_subtitle ); ?></p>
		<?php endif; ?>
		<?php if ( $ci_title ) : ?>
			<h2 class="story__title"><?php echo esc_html( $ci_title ); ?></h2>
		<?php endif; ?>
		<?php if ( $ci_text ) : ?>
			<div class="story__text rte"><?php echo wp_kses_post( wpautop( $ci_text ) ); ?></div>
		<?php endif; ?>
		<?php if ( $ci_label && $ci_url ) : ?>
			<a class="btn story__btn" href="<?php echo esc_url( $ci_url ); ?>"><?php echo esc_html( $ci_label ); ?></a>
		<?php endif; ?>
	</div>
</section>
