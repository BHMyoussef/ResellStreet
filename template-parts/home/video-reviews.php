<?php
/**
 * Homepage video reviews: the shared component (template-parts/components/video-reviews.php).
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

if ( ci_mod( 'home_videos_enable' ) ) {
	get_template_part(
		'template-parts/components/video-reviews',
		null,
		array(
			'id'    => 'home-videos-title',
			'class' => 'home-videos',
		)
	);
}
