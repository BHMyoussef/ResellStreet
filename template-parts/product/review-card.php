<?php
/**
 * One review card (design v2): stars + date, name + verified tag, text, photo thumbnails, store reply.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/** @var WP_Comment $ci_review */
$ci_review   = $args['review'];
$ci_id       = (int) $ci_review->comment_ID;
$ci_rating   = (int) get_comment_meta( $ci_id, 'rating', true );
$ci_photos   = ci_review_photos( $ci_id, 'thumbnail' );
$ci_verified = ci_review_is_verified( $ci_id );
$ci_replies  = get_comments(
	array(
		'parent' => $ci_id,
		'status' => 'approve',
		'number' => 1,
	)
);
?>
<li class="review-card" id="review-<?php echo esc_attr( $ci_id ); ?>">
	<div class="review-card__body" data-review-body>
		<p class="review-card__meta">
			<?php echo $ci_rating ? ci_stars( $ci_rating ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<time class="review-card__date" datetime="<?php echo esc_attr( get_comment_date( 'c', $ci_review ) ); ?>"><?php echo esc_html( get_comment_date( 'j M Y', $ci_review ) ); ?></time>
		</p>
		<p class="review-card__author">
			<span class="review-card__name"><?php echo esc_html( get_comment_author( $ci_review ) ); ?></span>
			<?php if ( $ci_verified ) : ?>
				<span class="review-card__verified"><?php esc_html_e( 'Verified buyer', 'customimpulse' ); ?></span>
			<?php endif; ?>
		</p>
		<div class="review-card__text"><?php echo wp_kses_post( wpautop( esc_html( $ci_review->comment_content ) ) ); ?></div>
		<?php if ( $ci_photos ) : ?>
			<ul class="review-card__thumbs">
				<?php foreach ( $ci_photos as $ci_photo ) : ?>
					<li>
						<button type="button" data-review-photo="<?php echo esc_url( $ci_photo['full'] ); ?>" data-review-id="<?php echo esc_attr( $ci_id ); ?>">
							<img src="<?php echo esc_url( $ci_photo['thumb'] ); ?>" alt="" width="72" height="72" loading="lazy" decoding="async">
							<span class="visually-hidden"><?php esc_html_e( 'View customer photo', 'customimpulse' ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $ci_replies ) : ?>
			<div class="review-card__reply">
				<p class="review-card__reply-title">
					<?php
					/* translators: %s: store name */
					echo esc_html( sprintf( __( 'Reply from %s', 'customimpulse' ), get_bloginfo( 'name' ) ) );
					?>
				</p>
				<?php echo wp_kses_post( wpautop( esc_html( $ci_replies[0]->comment_content ) ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</li>
