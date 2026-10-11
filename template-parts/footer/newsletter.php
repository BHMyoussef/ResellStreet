<?php
/**
 * Footer newsletter: text + form. Default = the theme's built-in sign-up (inc/subscribers.php); a plugin shortcode or an
 * external form URL from the Customizer replaces it.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_text      = (string) ci_mod( 'newsletter_text' );
$ci_shortcode = trim( (string) ci_mod( 'newsletter_shortcode' ) );
$ci_action    = (string) ci_mod( 'newsletter_action' );
$ci_field     = (string) ci_mod( 'newsletter_field' );

if ( $ci_text ) {
	echo '<div class="footer-newsletter__text">' . wp_kses_post( wpautop( $ci_text ) ) . '</div>';
}

if ( $ci_shortcode ) {
	echo '<div class="footer-newsletter__plugin">' . do_shortcode( $ci_shortcode ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- plugin output.
} else {
	// Own form (no shortcode): the theme's built-in sign-up (saved under Users → Subscribers, inc/subscribers.php),
	// or a plain form posting to a configured external URL.
	$ci_builtin = ! $ci_action;
	$ci_status  = isset( $_GET['subscribed'] ) ? sanitize_key( wp_unslash( $_GET['subscribed'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only (no-JS result).
	?>
	<form class="footer-newsletter__form" action="<?php echo esc_url( $ci_builtin ? admin_url( 'admin-post.php' ) : $ci_action ); ?>" method="post"<?php echo $ci_builtin ? ' data-newsletter' : ''; ?>>
		<?php if ( $ci_builtin ) : ?>
			<input type="hidden" name="action" value="ci_subscribe">
			<input type="hidden" name="ci_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<div class="footer-newsletter__trap" aria-hidden="true">
				<label for="footer-newsletter-website"><?php esc_html_e( 'Leave this field empty', 'customimpulse' ); ?></label>
				<input type="text" id="footer-newsletter-website" name="ci_website" tabindex="-1" autocomplete="off">
			</div>
		<?php endif; ?>
		<label class="visually-hidden" for="footer-newsletter-email"><?php esc_html_e( 'Enter your email', 'customimpulse' ); ?></label>
		<input type="email" id="footer-newsletter-email" name="<?php echo esc_attr( $ci_builtin ? 'email' : ( $ci_field ? $ci_field : 'email' ) ); ?>" placeholder="<?php esc_attr_e( 'Enter your email', 'customimpulse' ); ?>" autocomplete="email" autocapitalize="off" required>
		<button type="submit" class="footer-newsletter__submit">
			<?php echo ci_icon( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="visually-hidden"><?php esc_html_e( 'Subscribe', 'customimpulse' ); ?></span>
		</button>
		<?php if ( $ci_builtin ) : ?>
			<p class="footer-newsletter__message<?php echo 'error' === $ci_status ? ' is-error' : ''; ?>" role="status" data-newsletter-message<?php echo $ci_status ? '' : ' hidden'; ?>>
				<?php
				if ( 'yes' === $ci_status ) {
					esc_html_e( 'Thanks for subscribing!', 'customimpulse' );
				} elseif ( 'error' === $ci_status ) {
					esc_html_e( 'Please enter a valid email address.', 'customimpulse' );
				}
				?>
			</p>
		<?php endif; ?>
	</form>
	<?php
}
