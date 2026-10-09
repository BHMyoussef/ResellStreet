<?php
/**
 * Default page template.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'page-width page-width--narrow section' ); ?>>
		<header class="section-header">
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="rte"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
