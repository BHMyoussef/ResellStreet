<?php
/**
 * Collection promo banner (Customizer → Collection page).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'banner_enable' ) ) {
	return;
}

$ci_heading = (string) ci_mod( 'banner_heading' );
$ci_text    = (string) ci_mod( 'banner_text' );
$ci_link    = (string) ci_mod( 'banner_link' );
$ci_label   = (string) ci_mod( 'banner_label' );
if ( '' === $ci_heading && '' === $ci_text ) {
	return;
}

$ci_style = sprintf(
	'--banner-tint:%s;--banner-opacity:%s',
	sanitize_hex_color( ci_mod( 'banner_tint' ) ) ?: 'var(--brand-primary)',
	min( 100, absint( ci_mod( 'banner_tint_opacity' ) ) ) / 100
);
// Whole banner is the link when there is no separate label.
$ci_tag = ( $ci_link && '' === $ci_label ) ? 'a' : 'div';
?>
<<?php echo esc_html( $ci_tag ); ?> class="collection-banner"<?php echo 'a' === $ci_tag ? ' href="' . esc_url( $ci_link ) . '"' : ''; ?> style="<?php echo esc_attr( $ci_style ); ?>">
	<?php if ( $ci_heading ) : ?>
		<p class="collection-banner__heading h3"><?php echo esc_html( $ci_heading ); ?></p>
	<?php endif; ?>
	<?php if ( $ci_text ) : ?>
		<p class="collection-banner__text"><?php echo esc_html( $ci_text ); ?></p>
	<?php endif; ?>
	<?php if ( $ci_link && $ci_label ) : ?>
		<a class="collection-banner__link text-link" href="<?php echo esc_url( $ci_link ); ?>"><?php echo esc_html( $ci_label ); ?></a>
	<?php endif; ?>
</<?php echo esc_html( $ci_tag ); ?>>
