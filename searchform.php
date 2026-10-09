<?php
/**
 * Search form (product search).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_uid = wp_unique_id( 'search-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="visually-hidden" for="<?php echo esc_attr( $ci_uid ); ?>"><?php esc_html_e( 'Search our store', 'customimpulse' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $ci_uid ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search our store', 'customimpulse' ); ?>">
	<input type="hidden" name="post_type" value="product">
	<button type="submit" class="search-form__submit">
		<?php echo ci_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<span class="visually-hidden"><?php esc_html_e( 'Search', 'customimpulse' ); ?></span>
	</button>
</form>
