<?php
/**
 * Footer newsletter: text + form (plugin shortcode, or a plain form posting to a configured URL).
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
} elseif ( $ci_action ) {
	?>
	<form class="footer-newsletter__form" action="<?php echo esc_url( $ci_action ); ?>" method="post">
		<label class="visually-hidden" for="footer-newsletter-email"><?php esc_html_e( 'Email address', 'customimpulse' ); ?></label>
		<input type="email" id="footer-newsletter-email" name="<?php echo esc_attr( $ci_field ? $ci_field : 'email' ); ?>" placeholder="<?php esc_attr_e( 'Enter your email', 'customimpulse' ); ?>" autocomplete="email" autocapitalize="off" required>
		<button type="submit" class="footer-newsletter__submit">
			<?php echo ci_icon( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="visually-hidden"><?php esc_html_e( 'Subscribe', 'customimpulse' ); ?></span>
		</button>
	</form>
	<?php
}
