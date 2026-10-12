<?php
/**
 * Front page (design: reference/homepage-v2). Sections and their order: CI_HOME_SECTIONS; content: Customizer → Homepage.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

get_header();

foreach ( CI_HOME_SECTIONS as $ci_section ) {
	get_template_part( 'template-parts/home/' . $ci_section );
}

get_footer();
