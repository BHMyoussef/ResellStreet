<?php
/**
 * Hero slideshow. The first slide's image is the LCP on mobile: eager + fetchpriority=high.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_hero_enable' ) ) {
	return;
}
$ci_slides = ci_home_slides();
if ( ! $ci_slides ) {
	return;
}
$ci_count = count( $ci_slides );
$ci_style = sprintf(
	'--hero-h:%dpx;--hero-title:%dpx',
	max( 300, absint( ci_mod( 'home_hero_height' ) ) ),
	max( 30, absint( ci_mod( 'home_hero_title_size' ) ) )
);
?>
<section class="hero<?php echo ci_mod( 'home_hero_parallax' ) ? ' hero--parallax' : ''; ?>" data-hero data-autoplay="<?php echo esc_attr( absint( ci_mod( 'home_hero_autoplay' ) ) * 1000 ); ?>" style="<?php echo esc_attr( $ci_style ); ?>" aria-roledescription="<?php echo $ci_count > 1 ? esc_attr__( 'carousel', 'customimpulse' ) : ''; ?>">
	<div class="hero__slides">
		<?php foreach ( $ci_slides as $ci_i => $ci_slide ) : ?>
			<?php
			$ci_first = 0 === $ci_i;
			$ci_img   = array(
				'class'   => 'hero__img',
				'sizes'   => '100vw',
				'loading' => $ci_first ? 'eager' : 'lazy',
				'style'   => 'object-position:' . $ci_slide['focal'],
				'alt'     => '',
			);
			if ( $ci_first ) {
				$ci_img['fetchpriority'] = 'high';
			}
			?>
			<div class="hero__slide hero__slide--<?php echo esc_attr( $ci_slide['align'] ); ?><?php echo $ci_first ? ' is-active' : ''; ?>" data-hero-slide<?php echo $ci_first ? '' : ' inert aria-hidden="true"'; ?><?php echo $ci_count > 1 ? ' role="group" aria-roledescription="' . esc_attr__( 'slide', 'customimpulse' ) . '"' : ''; ?>>
				<div class="hero__media" data-parallax>
					<picture>
						<source media="(max-width: 768px)" srcset="<?php echo esc_attr( ci_mobile_srcset( $ci_slide['image_mobile'] ? $ci_slide['image_mobile'] : $ci_slide['image'] ) ); ?>" sizes="100vw">
						<?php echo wp_get_attachment_image( $ci_slide['image'], 'full', false, $ci_img ); ?>
					</picture>
					<?php if ( $ci_slide['overlay'] ) : ?>
						<span class="hero__overlay" style="opacity:<?php echo esc_attr( $ci_slide['overlay'] / 100 ); ?>"></span>
					<?php endif; ?>
				</div>
				<div class="hero__content page-width">
					<div class="hero__text">
						<?php if ( $ci_slide['eyebrow'] ) : ?>
							<p class="hero__eyebrow"><?php echo esc_html( $ci_slide['eyebrow'] ); ?></p>
						<?php endif; ?>
						<?php if ( $ci_slide['title'] ) : ?>
							<h2 class="hero__title"><?php echo esc_html( $ci_slide['title'] ); ?></h2>
						<?php endif; ?>
						<?php if ( $ci_slide['text'] ) : ?>
							<p class="hero__subtext"><?php echo esc_html( $ci_slide['text'] ); ?></p>
						<?php endif; ?>
						<?php if ( $ci_slide['buttons'] ) : ?>
							<div class="hero__buttons">
								<?php foreach ( $ci_slide['buttons'] as $ci_button ) : ?>
									<a class="btn" href="<?php echo esc_url( $ci_button['url'] ); ?>"><?php echo esc_html( $ci_button['label'] ); ?></a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $ci_count > 1 ) : ?>
		<div class="hero__bars">
			<?php foreach ( $ci_slides as $ci_i => $ci_slide ) : ?>
				<button type="button" class="hero__bar<?php echo 0 === $ci_i ? ' is-active' : ''; ?>" data-hero-go="<?php echo esc_attr( $ci_i ); ?>"<?php echo 0 === $ci_i ? ' aria-current="true"' : ''; ?>>
					<span class="hero__bar-fill"></span>
					<span class="visually-hidden">
						<?php
						/* translators: 1: slide number, 2: slide count */
						echo esc_html( sprintf( __( 'Slide %1$d of %2$d', 'customimpulse' ), $ci_i + 1, $ci_count ) );
						?>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
