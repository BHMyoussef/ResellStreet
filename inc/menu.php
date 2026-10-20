<?php
/**
 * Main menu: mega menu (desktop), drill-down drawer (mobile), brand logos.
 *
 * The menu itself is Appearance → Menus ("Main menu" location). A top-level item with the CSS class `mega`
 * opens a full-width panel: its children are columns (brand logo when the item is a product brand, or a product
 * category whose slug matches one), their children the links under them. The promo tile at the end of the panel
 * comes from Customizer → CustomImpulse theme → Main menu.
 *
 * @package CustomImpulse
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logo (attachment ID) for a menu item: the brand's thumbnail. 0 when none.
 *
 * @param WP_Post $item Menu item.
 */
function ci_menu_item_logo( $item ) {
	if ( 'taxonomy' !== $item->type || ! taxonomy_exists( 'product_brand' ) ) {
		return 0;
	}
	$brand = null;
	if ( 'product_brand' === $item->object ) {
		$brand = get_term( (int) $item->object_id, 'product_brand' );
	} elseif ( 'product_cat' === $item->object ) {
		$cat   = get_term( (int) $item->object_id, 'product_cat' );
		$brand = $cat instanceof WP_Term ? get_term_by( 'slug', $cat->slug, 'product_brand' ) : null;
	}
	return $brand instanceof WP_Term ? absint( get_term_meta( $brand->term_id, 'thumbnail_id', true ) ) : 0;
}

/**
 * Children of the primary menu's mega item(s) (the brands), for the drawer's logo grid.
 *
 * @return WP_Post[]
 */
function ci_menu_mega_children() {
	$locations = get_nav_menu_locations();
	if ( empty( $locations['primary'] ) ) {
		return array();
	}
	$items = wp_get_nav_menu_items( $locations['primary'] );
	if ( ! $items ) {
		return array();
	}
	$mega = array();
	foreach ( $items as $item ) {
		if ( ! $item->menu_item_parent && in_array( 'mega', (array) $item->classes, true ) ) {
			$mega[] = (int) $item->ID;
		}
	}
	return array_values( array_filter( $items, fn( $item ) => in_array( (int) $item->menu_item_parent, $mega, true ) ) );
}

/**
 * Walker for the main menu: brand logos in the mega item's columns, promo tile at the end of the desktop panel.
 */
class CI_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * 'desktop' (header) or 'mobile' (drawer).
	 *
	 * @var string
	 */
	private $context;

	/**
	 * The current top-level item has the `mega` class.
	 *
	 * @var bool
	 */
	public $in_mega = false;

	/**
	 * @param string $context 'desktop' | 'mobile'.
	 */
	public function __construct( $context = 'desktop' ) {
		$this->context = $context;
	}

	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		if ( 0 === $depth ) {
			$this->in_mega = in_array( 'mega', (array) $data_object->classes, true );
		}
		parent::start_el( $output, $data_object, $depth, $args, $current_object_id );
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
		if ( 0 === $depth && $this->in_mega && 'desktop' === $this->context ) {
			$output .= ci_menu_promo();
		}
		parent::end_lvl( $output, $depth, $args );
	}
}

/* Mega columns: logo + name (the name is visually hidden on desktop when there is a logo). */
add_filter(
	'nav_menu_item_title',
	function ( $title, $item, $args, $depth ) {
		if ( 1 !== $depth || ! ( ( $args->walker ?? null ) instanceof CI_Nav_Walker ) || ! $args->walker->in_mega ) {
			return $title;
		}
		$logo = ci_menu_item_logo( $item );
		if ( ! $logo ) {
			return $title;
		}
		$img = wp_get_attachment_image(
			$logo,
			'medium',
			false,
			array(
				'class'   => 'nav-brand__logo',
				'alt'     => '',
				'loading' => 'lazy',
				'sizes'   => '110px',
			)
		);
		return $img . '<span class="nav-brand__name">' . $title . '</span>';
	},
	10,
	4
);

add_filter(
	'nav_menu_css_class',
	function ( $classes, $item, $args, $depth ) {
		if ( 1 === $depth && ( $args->walker ?? null ) instanceof CI_Nav_Walker && $args->walker->in_mega && ci_menu_item_logo( $item ) ) {
			$classes[] = 'has-logo';
		}
		return $classes;
	},
	10,
	4
);

/**
 * Promo tile at the end of the desktop mega panel (Customizer → Main menu). Empty without an image and link.
 */
function ci_menu_promo() {
	$image = absint( ci_mod( 'menu_promo_image' ) );
	$link  = (string) ci_mod( 'menu_promo_link' );
	if ( ! $image || '' === $link ) {
		return '';
	}
	$eyebrow = (string) ci_mod( 'menu_promo_eyebrow' );
	$title   = (string) ci_mod( 'menu_promo_title' );
	$label   = (string) ci_mod( 'menu_promo_label' );
	$html    = '<li class="mega-promo"><a class="mega-promo__link" href="' . esc_url( $link ) . '">';
	$html   .= wp_get_attachment_image(
		$image,
		'medium_large',
		false,
		array(
			'class'   => 'mega-promo__img',
			'alt'     => '',
			'loading' => 'lazy',
			'sizes'   => '340px',
		)
	);
	$html .= '<span class="mega-promo__body">';
	$html .= $eyebrow ? '<span class="mega-promo__eyebrow">' . esc_html( $eyebrow ) . '</span>' : '';
	$html .= $title ? '<span class="mega-promo__title">' . esc_html( $title ) . '</span>' : '';
	$html .= $label ? '<span class="mega-promo__label">' . esc_html( $label ) . '</span>' : '';
	return $html . '</span></a></li>';
}

/**
 * "Free UK shipping over £X" for the drawer (from the Free shipping method), '' when there is none.
 */
function ci_menu_shipping_note() {
	if ( ! function_exists( 'ci_free_shipping_threshold' ) || ! ci_mod( 'menu_shipping_note' ) ) {
		return '';
	}
	$min = ci_free_shipping_threshold();
	if ( null === $min ) {
		return '';
	}
	return $min > 0
		/* translators: %s: minimum order amount */
		? sprintf( __( 'Free UK shipping over %s', 'customimpulse' ), wp_strip_all_tags( wc_price( $min, array( 'decimals' => floor( $min ) === $min ? 0 : 2 ) ) ) )
		: __( 'Free UK shipping', 'customimpulse' );
}
