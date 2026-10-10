<?php
/**
 * Front page: sections from Customizer → Homepage (order follows the reference index.json).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

get_header();

foreach ( array( 'hero', 'tiles', 'testimonials', 'story' ) as $ci_section ) {
	get_template_part( 'template-parts/home/' . $ci_section );
}

get_footer();
