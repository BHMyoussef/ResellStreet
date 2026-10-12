<?php
/**
 * Video reviews (homepage "Hear from our customers"): a small private post type under Products.
 * Title = customer name, featured image = poster, meta = video (Media Library file or URL), rating, product.
 * Order = the "Order" field (Page attributes), then newest.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'ci_video_review',
			array(
				'labels'          => array(
					'name'               => __( 'Video reviews', 'customimpulse' ),
					'singular_name'      => __( 'Video review', 'customimpulse' ),
					'add_new_item'       => __( 'Add video review', 'customimpulse' ),
					'edit_item'          => __( 'Edit video review', 'customimpulse' ),
					'featured_image'     => __( 'Poster image', 'customimpulse' ),
					'set_featured_image' => __( 'Set poster image', 'customimpulse' ),
					'not_found'          => __( 'No video reviews yet.', 'customimpulse' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=product',
				'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
				'capability_type' => 'product',
				'map_meta_cap'    => true,
			)
		);
	}
);

/**
 * Published video reviews that have a poster, in display order.
 *
 * @param int $count      Maximum number.
 * @param int $product_id Reviews linked to this product come first (0 = keep the admin order).
 * @return array<int,array{name:string,poster:int,video:string,rating:int,product:?WC_Product}> name '' / rating 0 = not shown.
 */
function ci_video_reviews( $count, $product_id = 0 ) {
	$items = array();
	$posts = get_posts(
		array(
			'post_type'   => 'ci_video_review',
			'post_status' => 'publish',
			'numberposts' => $product_id ? -1 : $count,
			'orderby'     => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		)
	);
	if ( $product_id ) {
		// Stable: this product's reviews first, then the rest in the admin order.
		usort( $posts, fn( $a, $b ) => ( (int) get_post_meta( $b->ID, '_ci_product', true ) === $product_id ) <=> ( (int) get_post_meta( $a->ID, '_ci_product', true ) === $product_id ) );
		$posts = array_slice( $posts, 0, $count );
	}
	foreach ( $posts as $post ) {
		$poster = (int) get_post_thumbnail_id( $post );
		if ( ! $poster ) {
			continue;
		}
		$video_id = (int) get_post_meta( $post->ID, '_ci_video_id', true );
		$video    = $video_id ? (string) wp_get_attachment_url( $video_id ) : (string) get_post_meta( $post->ID, '_ci_video_url', true );
		$product  = function_exists( 'wc_get_product' ) ? wc_get_product( (int) get_post_meta( $post->ID, '_ci_product', true ) ) : null;
		$items[]  = array(
			'name'    => trim( $post->post_title ),
			'poster'  => $poster,
			'video'   => $video,
			'rating'  => min( 5, max( 0, (int) get_post_meta( $post->ID, '_ci_rating', true ) ) ), // 0 = no stars
			'product' => $product && 'publish' === $product->get_status() ? $product : null,
		);
	}
	return $items;
}

/* ---------------------------------------------------------------------------
 * Admin: details box (video, rating, product)
 * ------------------------------------------------------------------------ */

add_action(
	'add_meta_boxes_ci_video_review',
	function () {
		add_meta_box( 'ci-video-review', __( 'Video review', 'customimpulse' ), 'ci_video_review_box', 'ci_video_review', 'normal', 'high' );
	}
);

/**
 * Details box.
 *
 * @param WP_Post $post Post.
 */
function ci_video_review_box( $post ) {
	wp_nonce_field( 'ci_video_review', 'ci_video_review_nonce' );
	$video_id = (int) get_post_meta( $post->ID, '_ci_video_id', true );
	$url      = (string) get_post_meta( $post->ID, '_ci_video_url', true );
	$rating   = (int) get_post_meta( $post->ID, '_ci_rating', true );
	$product  = (int) get_post_meta( $post->ID, '_ci_product', true );
	$products = get_posts(
		array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
			'fields'      => 'ids',
		)
	);
	?>
	<p><?php esc_html_e( 'Title = customer name (e.g. "Liam P."), optional. Poster image = the featured image (portrait 9:16), shown until the visitor taps play. Without a video the card is a photo review.', 'customimpulse' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Video file', 'customimpulse' ); ?></th>
			<td>
				<input type="hidden" name="ci_video_id" id="ci-video-id" value="<?php echo esc_attr( $video_id ); ?>">
				<span id="ci-video-name"><?php echo $video_id ? esc_html( basename( (string) get_attached_file( $video_id ) ) ) : '—'; ?></span>
				<button type="button" class="button" id="ci-video-pick"><?php esc_html_e( 'Choose / upload video', 'customimpulse' ); ?></button>
				<button type="button" class="button-link" id="ci-video-clear"><?php esc_html_e( 'Remove', 'customimpulse' ); ?></button>
				<p class="description"><?php esc_html_e( 'MP4 (H.264), portrait, ideally under 10 MB. It is only downloaded when a visitor taps play.', 'customimpulse' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ci-video-url"><?php esc_html_e( 'Or video URL', 'customimpulse' ); ?></label></th>
			<td><input type="url" class="regular-text" name="ci_video_url" id="ci-video-url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://…/video.mp4">
				<p class="description"><?php esc_html_e( 'Direct link to an MP4 file. Used when no file is chosen above.', 'customimpulse' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="ci-video-rating"><?php esc_html_e( 'Rating', 'customimpulse' ); ?></label></th>
			<td><select name="ci_rating" id="ci-video-rating">
				<option value="0" <?php selected( $rating, 0 ); ?>><?php esc_html_e( '— No rating (no stars shown) —', 'customimpulse' ); ?></option>
				<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $rating, $i ); ?>><?php echo esc_html( str_repeat( '★', $i ) ); ?></option>
				<?php endfor; ?>
			</select></td>
		</tr>
		<tr>
			<th scope="row"><label for="ci-video-product"><?php esc_html_e( 'Product', 'customimpulse' ); ?></label></th>
			<td><select name="ci_product" id="ci-video-product" style="max-width:100%">
				<option value="0"><?php esc_html_e( '— None —', 'customimpulse' ); ?></option>
				<?php foreach ( $products as $id ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $product, $id ); ?>><?php echo esc_html( get_the_title( $id ) ); ?></option>
				<?php endforeach; ?>
			</select></td>
		</tr>
	</table>
	<?php
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'ci_video_review' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		// Media Library picker for the video file (admin only; wp.media is WordPress core).
		wp_add_inline_script(
			'media-editor',
			"document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('ci-video-pick');if(!b)return;var f;b.addEventListener('click',function(){f=f||wp.media({title:b.textContent,library:{type:'video'},multiple:false});f.off('select').on('select',function(){var a=f.state().get('selection').first().toJSON();document.getElementById('ci-video-id').value=a.id;document.getElementById('ci-video-name').textContent=a.filename;});f.open();});document.getElementById('ci-video-clear').addEventListener('click',function(){document.getElementById('ci-video-id').value='';document.getElementById('ci-video-name').textContent='—';});});"
		);
	}
);

add_action(
	'save_post_ci_video_review',
	function ( $post_id ) {
		if ( ! isset( $_POST['ci_video_review_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ci_video_review_nonce'] ), 'ci_video_review' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$video_id = absint( $_POST['ci_video_id'] ?? 0 );
		update_post_meta( $post_id, '_ci_video_id', $video_id && wp_attachment_is( 'video', $video_id ) ? $video_id : 0 );
		update_post_meta( $post_id, '_ci_video_url', esc_url_raw( wp_unslash( $_POST['ci_video_url'] ?? '' ) ) );
		update_post_meta( $post_id, '_ci_rating', min( 5, absint( $_POST['ci_rating'] ?? 0 ) ) );
		update_post_meta( $post_id, '_ci_product', absint( $_POST['ci_product'] ?? 0 ) );
	}
);

/* List table: poster + product columns. */
add_filter(
	'manage_ci_video_review_posts_columns',
	fn( $cols ) => array_slice( $cols, 0, 1, true ) + array( 'ci_poster' => __( 'Poster', 'customimpulse' ) ) + array_slice( $cols, 1, 1, true ) + array( 'ci_product' => __( 'Product', 'customimpulse' ), 'ci_order' => __( 'Order', 'customimpulse' ) ) + array_slice( $cols, 2, null, true )
);
add_action(
	'manage_ci_video_review_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'ci_poster' === $col ) {
			echo get_the_post_thumbnail( $post_id, array( 45, 80 ) );
		} elseif ( 'ci_product' === $col ) {
			$id = (int) get_post_meta( $post_id, '_ci_product', true );
			echo $id ? esc_html( get_the_title( $id ) ) : '—';
		} elseif ( 'ci_order' === $col ) {
			echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
		}
	},
	10,
	2
);
