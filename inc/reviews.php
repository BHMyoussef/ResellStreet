<?php
/**
 * Reviews: native WooCommerce product reviews (collected by the CusRev plugin), displayed by the theme (Loox-style).
 *
 * CusRev data: photos = comment meta `ivole_review_image2` (attachment IDs, one row each; legacy `ivole_review_image`
 * holds remote URLs). Verified buyer = WooCommerce `verified` meta or CusRev `ivole_order`.
 * CusRev's frontend CSS/widgets are not used; its upload script is loaded on demand for the review form.
 * Schema: WooCommerce already outputs aggregateRating in the Product JSON-LD, so nothing is added here.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reviews per page (first render and each "Load more").
 */
function ci_reviews_per_page() {
	return max( 1, (int) apply_filters( 'ci_reviews_per_page', 6 ) );
}

/* ---------------------------------------------------------------------------
 * Data
 * ------------------------------------------------------------------------ */

/**
 * Approved top-level reviews for a product, newest first.
 *
 * @param int $product_id Product ID.
 * @param int $page       1-based page.
 * @param int $per_page   Reviews per page.
 * @return WP_Comment[]
 */
function ci_get_reviews( $product_id, $page = 1, $per_page = 0 ) {
	$per_page = $per_page ? $per_page : ci_reviews_per_page();
	return get_comments(
		array(
			'post_id' => $product_id,
			'type'    => 'review',
			'status'  => 'approve',
			'parent'  => 0,
			'number'  => $per_page,
			'offset'  => max( 0, $page - 1 ) * $per_page,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);
}

/**
 * Photos attached to a review (CusRev local uploads + legacy remote URLs).
 *
 * @param int    $comment_id Review ID.
 * @param string $size       Image size for `thumb`.
 * @return array<int,array{thumb:string,full:string,alt:string}>
 */
function ci_review_photos( $comment_id, $size = 'medium' ) {
	$photos = array();
	foreach ( (array) get_comment_meta( $comment_id, 'ivole_review_image2' ) as $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			continue;
		}
		$photos[] = array(
			'id'    => $attachment_id,
			'thumb' => (string) wp_get_attachment_image_url( $attachment_id, $size ),
			'full'  => (string) wp_get_attachment_image_url( $attachment_id, 'large' ),
			'alt'   => '',
		);
	}
	foreach ( (array) get_comment_meta( $comment_id, 'ivole_review_image' ) as $legacy ) {
		$url = is_array( $legacy ) ? ( $legacy['url'] ?? '' ) : '';
		if ( $url ) {
			$photos[] = array(
				'id'    => 0,
				'thumb' => esc_url_raw( $url ),
				'full'  => esc_url_raw( $url ),
				'alt'   => '',
			);
		}
	}
	return $photos;
}

/**
 * Whether a review comes from a verified buyer (WooCommerce flag or a CusRev review request).
 *
 * @param int $comment_id Review ID.
 */
function ci_review_is_verified( $comment_id ) {
	return (bool) get_comment_meta( $comment_id, 'verified', true ) || '' !== (string) get_comment_meta( $comment_id, 'ivole_order', true );
}

/**
 * Up to $limit photos from a product's reviews (for the "Customer photos" strip).
 *
 * @param int $product_id Product ID.
 * @param int $limit      Max photos.
 * @return array<int,array{thumb:string,full:string,alt:string,review:int}>
 */
function ci_product_review_photos( $product_id, $limit = 12 ) {
	$reviews = get_comments(
		array(
			'post_id'    => $product_id,
			'type'       => 'review',
			'status'     => 'approve',
			'number'     => $limit,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => 'ivole_review_image2', 'compare' => 'EXISTS' ),
				array( 'key' => 'ivole_review_image', 'compare' => 'EXISTS' ),
			),
		)
	);
	$photos = array();
	foreach ( $reviews as $review ) {
		foreach ( ci_review_photos( $review->comment_ID, 'thumbnail' ) as $photo ) {
			$photos[] = $photo + array( 'review' => (int) $review->comment_ID );
			if ( count( $photos ) >= $limit ) {
				return $photos;
			}
		}
	}
	return $photos;
}

/* ---------------------------------------------------------------------------
 * Stars
 * ------------------------------------------------------------------------ */

/**
 * Star rating as a single element (partial fill via CSS), with an accessible label.
 *
 * @param float  $rating Rating 0–5.
 * @param string $class  Extra classes.
 */
function ci_stars( $rating, $class = '' ) {
	$rating = max( 0, min( 5, (float) $rating ) );
	return sprintf(
		'<span class="ci-stars %1$s" style="--rating:%2$s" role="img" aria-label="%3$s"></span>',
		esc_attr( $class ),
		esc_attr( round( $rating, 2 ) ),
		/* translators: %s: rating out of 5 */
		esc_attr( sprintf( __( 'Rated %s out of 5', 'customimpulse' ), wc_format_decimal( $rating, 1 ) ) )
	);
}

/* ---------------------------------------------------------------------------
 * Under the title: stars + count (product page and Quick Shop)
 * ------------------------------------------------------------------------ */

add_action(
	'ci_product_after_title',
	function () {
		global $product;
		if ( ! $product instanceof WC_Product || ! wc_review_ratings_enabled() ) {
			return;
		}
		$count = (int) $product->get_review_count();
		if ( ! $count ) {
			return;
		}
		$href = is_product() ? '#reviews' : get_permalink( $product->get_id() ) . '#reviews';
		printf(
			'<a class="rating-summary" href="%1$s">%2$s<span class="rating-summary__text">%3$s</span></a>',
			esc_url( $href ),
			ci_stars( $product->get_average_rating() ), // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ci_stars().
			esc_html(
				sprintf(
					/* translators: 1: average rating, 2: number of reviews */
					_n( '%1$s · %2$s review', '%1$s · %2$s reviews', $count, 'customimpulse' ),
					wc_format_decimal( $product->get_average_rating(), 1 ),
					number_format_i18n( $count )
				)
			)
		);
	}
);

/* ---------------------------------------------------------------------------
 * Product page review section (replaces WooCommerce's / CusRev's reviews output)
 * ------------------------------------------------------------------------ */

add_action(
	'ci_product_reviews',
	function () {
		global $product;
		if ( ! $product instanceof WC_Product || ! comments_open( $product->get_id() ) ) {
			return;
		}
		get_template_part( 'template-parts/product/reviews', null, array( 'product' => $product ) );
	}
);

/**
 * Review cards HTML (server render + "Load more").
 *
 * @param WP_Comment[] $reviews Reviews.
 */
function ci_review_cards_html( array $reviews ) {
	ob_start();
	foreach ( $reviews as $review ) {
		get_template_part( 'template-parts/product/review-card', null, array( 'review' => $review ) );
	}
	return (string) ob_get_clean();
}

/**
 * The review form: WooCommerce's own form args (rating select, CusRev's photo upload field via
 * `woocommerce_product_review_comment_form_args`), without WooCommerce's review list template.
 *
 * @param WC_Product $product Product.
 */
function ci_review_form( WC_Product $product ) {
	if ( 'yes' === get_option( 'woocommerce_review_rating_verification_required' ) && ! wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) {
		echo '<p class="review-form__notice">' . esc_html__( 'Only logged in customers who have purchased this product may leave a review.', 'customimpulse' ) . '</p>';
		return;
	}

	$commenter    = wp_get_current_commenter();
	$name_email   = (bool) get_option( 'require_name_email', 1 );
	$fields       = array();
	$author_label = __( 'Name', 'customimpulse' );
	$email_label  = __( 'Email', 'customimpulse' );
	$fields['author'] = sprintf(
		'<p class="comment-form-author"><label for="author">%1$s%2$s</label><input id="author" name="author" type="text" autocomplete="name" value="%3$s" size="30"%4$s /></p>',
		esc_html( $author_label ),
		$name_email ? '&nbsp;<span class="required">*</span>' : '',
		esc_attr( $commenter['comment_author'] ),
		$name_email ? ' required' : ''
	);
	$fields['email']  = sprintf(
		'<p class="comment-form-email"><label for="email">%1$s%2$s</label><input id="email" name="email" type="email" autocomplete="email" value="%3$s" size="30"%4$s /></p>',
		esc_html( $email_label ),
		$name_email ? '&nbsp;<span class="required">*</span>' : '',
		esc_attr( $commenter['comment_author_email'] ),
		$name_email ? ' required' : ''
	);

	$comment_field = '';
	if ( wc_review_ratings_enabled() ) {
		$comment_field .= '<div class="comment-form-rating"><label for="rating" id="comment-form-rating-label">' . esc_html__( 'Your rating', 'customimpulse' ) . ( wc_review_ratings_required() ? '&nbsp;<span class="required">*</span>' : '' ) . '</label><select name="rating" id="rating"' . ( wc_review_ratings_required() ? ' required' : '' ) . '>
			<option value="">' . esc_html__( 'Rate&hellip;', 'woocommerce' ) . '</option>
			<option value="5">' . esc_html__( 'Perfect', 'woocommerce' ) . '</option>
			<option value="4">' . esc_html__( 'Good', 'woocommerce' ) . '</option>
			<option value="3">' . esc_html__( 'Average', 'woocommerce' ) . '</option>
			<option value="2">' . esc_html__( 'Not that bad', 'woocommerce' ) . '</option>
			<option value="1">' . esc_html__( 'Very poor', 'woocommerce' ) . '</option>
		</select></div>'; // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- reuse WooCommerce's translated labels.
	}
	$comment_field .= '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your review', 'customimpulse' ) . '&nbsp;<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="5" required></textarea></p>';

	$args = array(
		/* translators: %s: product name */
		'title_reply'         => sprintf( esc_html__( 'Review “%s”', 'customimpulse' ), $product->get_name() ),
		'title_reply_to'      => esc_html__( 'Leave a Reply to %s', 'customimpulse' ),
		'title_reply_before'  => '<h3 id="reply-title" class="comment-reply-title">',
		'title_reply_after'   => '</h3>',
		'comment_notes_after' => '',
		'label_submit'        => esc_html__( 'Submit review', 'customimpulse' ),
		'class_submit'        => 'btn review-form__submit',
		'submit_button'       => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
		'logged_in_as'        => '',
		'fields'              => $fields,
		'comment_field'       => $comment_field,
	);
	$account_page_url = wc_get_page_permalink( 'myaccount' );
	if ( $account_page_url && get_option( 'comment_registration' ) ) {
		/* translators: %s: account page URL */
		$args['must_log_in'] = '<p class="must-log-in">' . sprintf( esc_html__( 'You must be %1$slogged in%2$s to post a review.', 'customimpulse' ), '<a href="' . esc_url( $account_page_url ) . '">', '</a>' ) . '</p>';
	}

	// The same filter WooCommerce uses: CusRev adds its photo upload field here.
	comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $args ), $product->get_id() );
}

/* "Load more" endpoint: GET /wp-json/ci/v1/reviews/<product>?page=2 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ci/v1',
			'/reviews/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'   => array( 'sanitize_callback' => 'absint' ),
					'page' => array(
						'sanitize_callback' => 'absint',
						'default'           => 2,
					),
				),
				'callback'            => function ( WP_REST_Request $request ) {
					$product = wc_get_product( (int) $request['id'] );
					if ( ! $product || 'publish' !== $product->get_status() ) {
						return new WP_Error( 'ci_not_found', __( 'Product not found.', 'customimpulse' ), array( 'status' => 404 ) );
					}
					$page     = max( 2, (int) $request['page'] );
					$reviews  = ci_get_reviews( $product->get_id(), $page );
					$response = rest_ensure_response(
						array(
							'html'    => ci_review_cards_html( $reviews ),
							'hasMore' => $page * ci_reviews_per_page() < (int) $product->get_review_count(),
						)
					);
					$response->header( 'Cache-Control', 'public, max-age=300' );
					return $response;
				},
			)
		);
	}
);

/* ---------------------------------------------------------------------------
 * CusRev: keep collection (emails, upload field), drop its frontend styling/widgets
 * ------------------------------------------------------------------------ */

add_action(
	'wp_enqueue_scripts',
	function () {
		// CusRev enqueues its frontend CSS/JS (+ jQuery) on every page; the theme renders reviews itself.
		// Its email review form is a standalone page that prints its own assets, so it isn't affected.
		wp_dequeue_style( 'cr-frontend-css' );
		wp_dequeue_script( 'cr-colcade' );
		if ( ! is_product() ) {
			wp_dequeue_script( 'cr-frontend-js' );
			return;
		}

		$config = array(
			'moreUrl' => esc_url_raw( rest_url( 'ci/v1/reviews/' ) ),
			'error'   => __( 'Something went wrong. Please try again.', 'customimpulse' ),
		);

		// CusRev's frontend script (≈136 KB) only powers the photo upload on the review form:
		// keep its settings inline, load the file when the shopper opens "Write a review".
		$scripts = wp_scripts();
		$inline  = '';
		if ( isset( $scripts->registered['cr-frontend-js'] ) ) {
			$inline                 = (string) $scripts->get_data( 'cr-frontend-js', 'data' );
			$cr                     = $scripts->registered['cr-frontend-js'];
			$config['uploadScript'] = esc_url_raw( add_query_arg( 'ver', $cr->ver, $cr->src ) );
			wp_dequeue_script( 'cr-frontend-js' );
		}
		wp_add_inline_script( 'ci-product', $inline . ';window.ciReviews=' . wp_json_encode( $config ) . ';', 'before' );
	},
	100
);
