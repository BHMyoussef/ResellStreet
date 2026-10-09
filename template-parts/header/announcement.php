<?php
/**
 * Rotating announcement bar (messages from the Customizer).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$messages = ci_announcement_messages();
if ( ! $messages ) {
	return;
}
?>
<div class="announcement" data-announcement data-interval="<?php echo esc_attr( max( 2, absint( ci_mod( 'announcement_interval' ) ) ) * 1000 ); ?>" role="region" aria-label="<?php esc_attr_e( 'Announcements', 'customimpulse' ); ?>">
	<div class="announcement__track page-width">
		<?php foreach ( $messages as $i => $message ) : ?>
			<p class="announcement__msg<?php echo 0 === $i ? ' is-active' : ''; ?>"<?php echo 0 === $i ? '' : ' inert'; ?>>
				<?php if ( $message['link'] ) : ?>
					<a href="<?php echo esc_url( $message['link'] ); ?>"><?php echo esc_html( $message['text'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $message['text'] ); ?>
				<?php endif; ?>
			</p>
		<?php endforeach; ?>
	</div>
</div>
