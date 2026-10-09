<?php
/**
 * Site footer. Closes <main>.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

$ci_newsletter_title = (string) ci_mod( 'newsletter_title' );
$ci_newsletter_text  = (string) ci_mod( 'newsletter_text' );
$ci_contact_title    = (string) ci_mod( 'contact_title' );
$ci_contact_text     = (string) ci_mod( 'contact_text' );
?>
</main>

<footer class="site-footer">
	<div class="page-width">
		<div class="footer__blocks">
			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="footer__block footer__block--menu" aria-label="<?php esc_attr_e( 'Footer', 'customimpulse' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'menu_class'     => 'footer__menu',
							'menu_id'        => 'footer-menu',
							'depth'          => 1,
						)
					);
					?>
				</nav>
			<?php endif; ?>

			<?php if ( $ci_newsletter_title || $ci_newsletter_text ) : ?>
				<?php
				get_template_part(
					'template-parts/footer/collapsible',
					null,
					array(
						'id'       => 'newsletter',
						'title'    => $ci_newsletter_title,
						'template' => 'template-parts/footer/newsletter',
					)
				);
				?>
			<?php endif; ?>

			<?php if ( $ci_contact_title || $ci_contact_text ) : ?>
				<?php
				get_template_part(
					'template-parts/footer/collapsible',
					null,
					array(
						'id'      => 'contact',
						'title'   => $ci_contact_title,
						'content' => $ci_contact_text,
					)
				);
				?>
			<?php endif; ?>
		</div>

		<div class="footer__logo">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo ci_logo( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ci_logo(). ?>
			</a>
		</div>

		<div class="footer__bottom">
			<?php get_template_part( 'template-parts/footer/payment-icons' ); ?>
			<p class="footer__copyright">
				<?php
				$ci_copyright = (string) ci_mod( 'copyright_text' );
				echo $ci_copyright
					? esc_html( $ci_copyright )
					: esc_html( sprintf( '© %s %s', wp_date( 'Y' ), get_bloginfo( 'name' ) ) );
				?>
			</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
