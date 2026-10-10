<?php
/**
 * Testimonials carousel: real product reviews (ci_home_testimonials(), Customizer → Homepage → Testimonials).
 * Scroll-snap; home.js marks the centred card and wires the dots.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_testimonials_enable' ) ) {
	return;
}
$ci_items = ci_home_testimonials();
if ( ! $ci_items ) {
	return;
}
$ci_title = (string) ci_mod( 'home_testimonials_title' );
?>
<section class="testimonials" aria-label="<?php echo esc_attr( $ci_title ? $ci_title : __( 'Testimonials', 'customimpulse' ) ); ?>">
	<?php if ( $ci_title ) : ?>
		<div class="page-width"><h2 class="section-title"><?php echo esc_html( $ci_title ); ?></h2></div>
	<?php endif; ?>
	<div class="testimonials__viewport" data-carousel tabindex="0">
		<ul class="testimonials__track">
			<?php foreach ( $ci_items as $ci_item ) : ?>
				<li class="testimonial" data-carousel-slide>
					<figure class="testimonial__card">
						<?php if ( $ci_item['stars'] ) : ?>
							<p class="testimonial__stars"><?php echo ci_stars( $ci_item['stars'], 'ci-stars--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
						<?php endif; ?>
						<blockquote class="testimonial__text rte"><?php echo wp_kses_post( wpautop( esc_html( $ci_item['text'] ) ) ); ?></blockquote>
						<figcaption class="testimonial__author">
							<?php if ( $ci_item['image'] ) : ?>
								<?php
								echo wp_get_attachment_image(
									$ci_item['image'],
									'thumbnail',
									false,
									array(
										'class'   => 'testimonial__photo',
										'sizes'   => '72px',
										'loading' => 'lazy',
										'alt'     => '',
									)
								);
								?>
							<?php elseif ( $ci_item['image_url'] ) : ?>
								<img class="testimonial__photo" src="<?php echo esc_url( $ci_item['image_url'] ); ?>" alt="" width="72" height="72" loading="lazy">
							<?php endif; ?>
							<?php if ( $ci_item['author'] ) : ?>
								<cite class="testimonial__name"><?php echo esc_html( $ci_item['author'] ); ?></cite>
							<?php endif; ?>
							<?php if ( $ci_item['info'] ) : ?>
								<a class="testimonial__info" href="<?php echo esc_url( $ci_item['url'] ); ?>"><?php echo esc_html( $ci_item['info'] ); ?></a>
							<?php endif; ?>
						</figcaption>
					</figure>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php if ( count( $ci_items ) > 1 ) : ?>
		<div class="carousel-dots" data-carousel-dots>
			<?php foreach ( $ci_items as $ci_i => $ci_item ) : ?>
				<button type="button" class="carousel-dots__dot" data-carousel-go="<?php echo esc_attr( $ci_i ); ?>">
					<span class="visually-hidden">
						<?php
						/* translators: %d: testimonial number */
						echo esc_html( sprintf( __( 'Show testimonial %d', 'customimpulse' ), $ci_i + 1 ) );
						?>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
