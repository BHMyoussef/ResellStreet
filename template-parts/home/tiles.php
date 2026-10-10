<?php
/**
 * Collection tiles ("Choose Your Vibe"): square images with a white label box.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ! ci_mod( 'home_tiles_enable' ) || ! taxonomy_exists( 'product_cat' ) ) {
	return;
}
$ci_tiles = ci_home_tiles();
if ( ! $ci_tiles ) {
	return;
}
$ci_title   = (string) ci_mod( 'home_tiles_title' );
$ci_per_row = min( 5, max( 2, absint( ci_mod( 'home_tiles_per_row' ) ) ) );
?>
<section class="home-section collection-tiles">
	<div class="page-width">
		<?php if ( $ci_title ) : ?>
			<h2 class="section-title"><?php echo esc_html( $ci_title ); ?></h2>
		<?php endif; ?>
		<ul class="collection-tiles__grid" style="--tiles-per-row:<?php echo esc_attr( $ci_per_row ); ?>">
			<?php foreach ( $ci_tiles as $ci_tile ) : ?>
				<li>
					<a class="collection-tile" href="<?php echo esc_url( $ci_tile['url'] ); ?>">
						<span class="collection-tile__media">
							<?php
							if ( $ci_tile['image'] ) {
								echo wp_get_attachment_image(
									$ci_tile['image'],
									'ci-square-lg',
									false,
									array(
										'class'   => 'collection-tile__img',
										'sizes'   => sprintf( '(min-width: 769px) calc((min(100vw, 1500px) - 80px) / %d), 50vw', $ci_per_row ),
										'loading' => 'lazy',
										'alt'     => '',
									)
								);
							}
							?>
						</span>
						<span class="collection-tile__label"><?php echo esc_html( $ci_tile['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
