<?php
/**
 * Product page review section (Loox-style): summary, customer photos, review cards, "Load more", review form.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/** @var WC_Product $ci_product */
$ci_product = $args['product'];
$ci_id      = $ci_product->get_id();
$ci_count   = (int) $ci_product->get_review_count();
$ci_average = (float) $ci_product->get_average_rating();
$ci_ratings = (array) $ci_product->get_rating_counts();
$ci_photos  = $ci_count ? ci_product_review_photos( $ci_id, 12 ) : array();
$ci_reviews = $ci_count ? ci_get_reviews( $ci_id, 1 ) : array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$ci_pending = isset( $_GET['unapproved'] );
?>
<div class="reviews" data-reviews data-product="<?php echo esc_attr( $ci_id ); ?>" data-page="1">
	<div class="reviews__head">
		<h2 class="reviews__title"><?php esc_html_e( 'Customer Reviews', 'customimpulse' ); ?></h2>

		<?php if ( $ci_pending ) : ?>
			<p class="reviews__notice" role="status"><?php esc_html_e( 'Thank you! Your review is awaiting approval.', 'customimpulse' ); ?></p>
		<?php endif; ?>

		<?php if ( $ci_count ) : ?>
			<div class="reviews__summary">
				<div class="reviews__score">
					<span class="reviews__average"><?php echo esc_html( wc_format_decimal( $ci_average, 1 ) ); ?></span>
					<?php echo ci_stars( $ci_average, 'ci-stars--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="reviews__based">
						<?php
						/* translators: %s: number of reviews */
						echo esc_html( sprintf( _n( 'Based on %s review', 'Based on %s reviews', $ci_count, 'customimpulse' ), number_format_i18n( $ci_count ) ) );
						?>
					</span>
				</div>
				<ul class="reviews__bars" aria-label="<?php esc_attr_e( 'Rating breakdown', 'customimpulse' ); ?>">
					<?php for ( $ci_star = 5; $ci_star >= 1; $ci_star-- ) : ?>
						<?php
						$ci_n   = (int) ( $ci_ratings[ $ci_star ] ?? 0 );
						$ci_pct = $ci_count ? round( $ci_n / $ci_count * 100 ) : 0;
						?>
						<li class="reviews__bar-row">
							<span class="reviews__bar-label"><?php echo esc_html( $ci_star ); ?>&nbsp;★</span>
							<span class="reviews__bar" aria-hidden="true"><span style="width:<?php echo esc_attr( $ci_pct ); ?>%"></span></span>
							<span class="reviews__bar-count">
								<span aria-hidden="true"><?php echo esc_html( $ci_n ); ?></span>
								<span class="visually-hidden">
									<?php
									/* translators: 1: number of reviews, 2: star rating */
									echo esc_html( sprintf( _n( '%1$d review with %2$d stars', '%1$d reviews with %2$d stars', $ci_n, 'customimpulse' ), $ci_n, $ci_star ) );
									?>
								</span>
							</span>
						</li>
					<?php endfor; ?>
				</ul>
				<div class="reviews__actions">
					<button type="button" class="btn btn--secondary" data-review-form-toggle aria-expanded="false" aria-controls="review-form"><?php esc_html_e( 'Write a review', 'customimpulse' ); ?></button>
				</div>
			</div>
		<?php else : ?>
			<div class="reviews__empty">
				<?php echo ci_stars( 0, 'ci-stars--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p><?php esc_html_e( 'Be the first to write a review', 'customimpulse' ); ?></p>
				<button type="button" class="btn btn--secondary" data-review-form-toggle aria-expanded="false" aria-controls="review-form"><?php esc_html_e( 'Write a review', 'customimpulse' ); ?></button>
			</div>
		<?php endif; ?>
	</div>

	<div class="review-form" id="review-form" hidden>
		<?php ci_review_form( $ci_product ); ?>
	</div>

	<?php if ( $ci_photos ) : ?>
		<div class="reviews__photos">
			<h3 class="reviews__photos-title"><?php esc_html_e( 'Customer photos', 'customimpulse' ); ?></h3>
			<ul class="reviews__photo-strip">
				<?php foreach ( $ci_photos as $ci_photo ) : ?>
					<li>
						<button type="button" class="reviews__photo" data-review-photo="<?php echo esc_url( $ci_photo['full'] ); ?>" data-review-id="<?php echo esc_attr( $ci_photo['review'] ); ?>">
							<img src="<?php echo esc_url( $ci_photo['thumb'] ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
							<span class="visually-hidden"><?php esc_html_e( 'View customer photo', 'customimpulse' ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $ci_reviews ) : ?>
		<ul class="reviews__grid" data-reviews-list>
			<?php echo ci_review_cards_html( $ci_reviews ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the card template. ?>
		</ul>
		<?php if ( $ci_count > ci_reviews_per_page() ) : ?>
			<div class="reviews__more">
				<button type="button" class="btn btn--secondary" data-reviews-more><?php esc_html_e( 'Load more', 'customimpulse' ); ?></button>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<dialog class="modal modal--review-photo" data-modal="review-photo" aria-label="<?php esc_attr_e( 'Customer photo', 'customimpulse' ); ?>">
	<div class="modal__inner">
		<button type="button" class="modal__close" data-modal-close>
			<?php echo ci_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="visually-hidden"><?php esc_html_e( 'Close', 'customimpulse' ); ?></span>
		</button>
		<div class="review-photo">
			<img class="review-photo__img" data-review-photo-img src="data:," alt="">
			<div class="review-photo__review" data-review-photo-review></div>
		</div>
	</div>
</dialog>
