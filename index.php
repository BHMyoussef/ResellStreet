<?php
/**
 * Fallback template: archives, blog, search for non-product content.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="page-width section">
	<?php if ( is_home() || is_archive() || is_search() ) : ?>
		<header class="section-header">
			<h1>
				<?php
				if ( is_search() ) {
					/* translators: %s: search terms */
					printf( esc_html__( 'Search: %s', 'customimpulse' ), esc_html( get_search_query() ) );
				} elseif ( is_home() ) {
					single_post_title();
				} else {
					the_archive_title();
				}
				?>
			</h1>
		</header>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'entry' ); ?>>
				<?php if ( is_singular() ) : ?>
					<h1 class="entry__title"><?php the_title(); ?></h1>
					<div class="entry__content rte"><?php the_content(); ?></div>
				<?php else : ?>
					<h2 class="entry__title h3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry__excerpt rte"><?php the_excerpt(); ?></div>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'customimpulse' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
