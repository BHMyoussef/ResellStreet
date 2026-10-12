<?php
/**
 * Video reviews ("Hear from our customers"): poster cards (Products → Video reviews; no video = photo card); the video file is
 * only requested when a card is tapped (home.js). Rating summary = all approved product reviews.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_videos_enable' ) || ! function_exists( 'ci_video_reviews' ) ) {
	return;
}
$ci_videos = ci_video_reviews( min( 20, max( 1, absint( ci_mod( 'home_videos_count' ) ) ) ) );
if ( ! $ci_videos ) {
	return;
}

$ci_aside = '';
$ci_store = ci_mod( 'home_videos_summary' ) && function_exists( 'ci_store_rating' ) ? ci_store_rating() : array( 'count' => 0 );
if ( $ci_store['count'] > 0 ) {
	$ci_aside .= sprintf(
		'<div class="rating-summary"><span class="rating-summary__value">%1$s</span><span class="rating-summary__meta">%2$s<span class="rating-summary__count">%3$s</span></span></div>',
		esc_html( number_format_i18n( $ci_store['average'], 1 ) ),
		ci_stars( $ci_store['average'] ),
		/* translators: %s: number of reviews */
		esc_html( sprintf( _n( '%s review', '%s reviews', $ci_store['count'], 'customimpulse' ), number_format_i18n( $ci_store['count'] ) ) )
	);
}
if ( count( $ci_videos ) > 1 ) {
	$ci_aside .= '<div class="carousel-arrows">'
		. '<button type="button" class="carousel-arrows__btn" data-carousel-prev aria-label="' . esc_attr__( 'Previous videos', 'customimpulse' ) . '"><span aria-hidden="true">←</span></button>'
		. '<button type="button" class="carousel-arrows__btn" data-carousel-next aria-label="' . esc_attr__( 'Next videos', 'customimpulse' ) . '"><span aria-hidden="true">→</span></button>'
		. '</div>';
}
?>
<section class="home-section home-section--alt home-videos" aria-labelledby="home-videos-title" data-carousel>
	<div class="home-width">
		<?php
		ci_home_heading(
			'home_videos',
			array(
				'id'    => 'home-videos-title',
				'extra' => $ci_aside ? '<div class="home-videos__aside">' . $ci_aside . '</div>' : '',
			)
		);
		?>
		<ul class="video-reviews" role="list" data-carousel-track>
			<?php foreach ( $ci_videos as $ci_video ) : ?>
				<li class="video-card">
					<div class="video-card__media<?php echo $ci_video['rating'] || '' !== $ci_video['name'] ? ' video-card__media--caption' : ''; ?>" data-video>
						<?php
						echo wp_get_attachment_image(
							$ci_video['poster'],
							'medium_large',
							false,
							array(
								'class'   => 'video-card__poster',
								'loading' => 'lazy',
								'sizes'   => '(min-width: 769px) 221px, 56vw',
								'alt'     => $ci_video['video'] ? '' : __( 'Customer photo', 'customimpulse' ),
							)
						);
						?>
						<?php if ( $ci_video['video'] ) : ?>
							<button type="button" class="video-card__play" data-video-src="<?php echo esc_url( $ci_video['video'] ); ?>">
								<span class="video-card__play-icon" aria-hidden="true"></span>
								<span class="visually-hidden">
									<?php
									echo esc_html(
										'' !== $ci_video['name']
											/* translators: %s: customer name */
											? sprintf( __( 'Play video review by %s', 'customimpulse' ), $ci_video['name'] )
											: __( 'Play customer video review', 'customimpulse' )
									);
									?>
								</span>
							</button>
						<?php endif; ?>
						<?php if ( $ci_video['rating'] || '' !== $ci_video['name'] ) : ?>
							<div class="video-card__caption">
								<?php echo $ci_video['rating'] ? ci_stars( $ci_video['rating'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php if ( '' !== $ci_video['name'] ) : ?>
									<p class="video-card__name"><?php echo esc_html( $ci_video['name'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
					<?php if ( $ci_video['product'] ) : ?>
						<a class="video-card__product" href="<?php echo esc_url( $ci_video['product']->get_permalink() ); ?>">
							<?php
							echo wp_get_attachment_image(
								$ci_video['product']->get_image_id(),
								'thumbnail',
								false,
								array(
									'class'   => 'video-card__thumb',
									'loading' => 'lazy',
									'alt'     => '',
								)
							);
							?>
							<span class="video-card__product-name"><?php echo esc_html( $ci_video['product']->get_name() ); ?></span>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
