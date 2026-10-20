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
 * Logo image with an optically even size: logos are trimmed to their ink, so a fixed box makes wide logos look big
 * and compact ones small. Height = $base / sqrt(width/height) (same area for every logo), capped by $max_h / $max_w.
 *
 * @param int    $id    Attachment ID.
 * @param int    $base  Height of a square logo (px).
 * @param int    $max_h Max height (px).
 * @param int    $max_w Max width (px).
 * @param string $alt   Alt text.
 * @param string $class Class.
 */
function ci_menu_logo_img( $id, $base, $max_h, $max_w, $alt = '', $class = 'nav-brand__logo' ) {
	$meta  = wp_get_attachment_metadata( $id );
	$ratio = ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ? $meta['width'] / $meta['height'] : 1;
	$h     = min( $base / sqrt( $ratio ), $max_h, $max_w / $ratio );
	return wp_get_attachment_image(
		$id,
		'medium',
		false,
		array(
			'class'   => $class,
			'alt'     => $alt,
			'loading' => 'lazy',
			'sizes'   => (int) ceil( $h * $ratio ) . 'px',
			'style'   => sprintf( 'width:%.1fpx;height:%.1fpx', $h * $ratio, $h ),
		)
	);
}

/**
 * The primary menu's first mega item (the Brands item), or null.
 */
function ci_menu_mega_item() {
	$locations = get_nav_menu_locations();
	$items     = empty( $locations['primary'] ) ? array() : wp_get_nav_menu_items( $locations['primary'] );
	foreach ( (array) $items as $item ) {
		if ( ! $item->menu_item_parent && in_array( 'mega', (array) $item->classes, true ) ) {
			return $item;
		}
	}
	return null;
}

/**
 * Children of the primary menu's mega item (the brands), for the drawer's logo grid.
 *
 * @return WP_Post[]
 */
function ci_menu_mega_children() {
	$mega = ci_menu_mega_item();
	if ( ! $mega ) {
		return array();
	}
	$items = wp_get_nav_menu_items( get_nav_menu_locations()['primary'] );
	return array_values( array_filter( (array) $items, fn( $item ) => (int) $item->menu_item_parent === (int) $mega->ID ) );
}

/**
 * "View all brands" link for a mega item ('' when it links nowhere).
 *
 * @param WP_Post $item  Mega menu item.
 * @param string  $class Link class.
 */
function ci_menu_view_all( $item, $class ) {
	if ( ! $item || '' === $item->url || '#' === $item->url ) {
		return '';
	}
	/* translators: %s: menu item title, lowercased (e.g. "brands") */
	$label = sprintf( __( 'View all %s', 'customimpulse' ), mb_strtolower( wp_strip_all_tags( $item->title ) ) );
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $item->url ) . '">' . esc_html( $label ) . '</a>';
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
	public $context;

	/**
	 * The current top-level item when it has the `mega` class, else null.
	 *
	 * @var WP_Post|null
	 */
	private $mega_item = null;

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
			$this->in_mega   = in_array( 'mega', (array) $data_object->classes, true );
			$this->mega_item = $this->in_mega ? $data_object : null;
		}
		parent::start_el( $output, $data_object, $depth, $args, $current_object_id );
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
		if ( 0 === $depth && $this->in_mega && 'desktop' === $this->context ) {
			$all     = ci_menu_view_all( $this->mega_item, 'btn mega-all__btn' );
			$output .= $all ? '<li class="mega-all">' . $all . '</li>' : '';
			$output .= ci_menu_promo();
		}
		parent::end_lvl( $output, $depth, $args );
	}
}

/* Mega columns: logo (in a fixed-width slot, sized by ci_menu_logo_img()) + name (visually hidden on desktop). */
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
		$img = 'desktop' === $args->walker->context
			? ci_menu_logo_img( $logo, 42, 32, 112 )
			: ci_menu_logo_img( $logo, 26, 22, 60 );
		return '<span class="nav-brand__mark">' . $img . '</span><span class="nav-brand__name">' . $title . '</span>';
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
