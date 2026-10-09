<?php
/**
 * Footer column: collapsible on mobile, always open on desktop.
 *
 * Args: id, title, and either `content` (HTML) or `template` (template part path).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_id    = sanitize_key( $args['id'] ?? 'block' );
$ci_panel = "footer-panel-{$ci_id}";
?>
<div class="footer__block footer__block--collapsible footer__block--<?php echo esc_attr( $ci_id ); ?>" data-collapsible>
	<?php if ( ! empty( $args['title'] ) ) : ?>
		<h2 class="footer__title">
			<button type="button" class="footer__toggle" data-collapsible-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $ci_panel ); ?>">
				<?php echo esc_html( $args['title'] ); ?>
				<?php echo ci_icon( 'chevron-down' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
		</h2>
	<?php endif; ?>
	<div class="footer__panel rte" id="<?php echo esc_attr( $ci_panel ); ?>">
		<?php
		if ( ! empty( $args['template'] ) ) {
			get_template_part( $args['template'] );
		} else {
			echo wp_kses_post( wpautop( $args['content'] ?? '' ) );
		}
		?>
	</div>
</div>
