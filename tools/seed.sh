#!/usr/bin/env bash
# Seed the local site with the store's real content (from the Shopify export) via WP-CLI.
# Idempotent: safe to re-run. Content lives in the DB, never in theme files.
#   tools/seed.sh
set -euo pipefail
WP="$(cd "$(dirname "$0")" && pwd)/wp"

"$WP" theme activate CustomImpulse-theme

# ---------- Pages ----------
page_id() { # slug title [content]
  # Any status: WordPress ships a *draft* "privacy-policy"; looking for published pages only created a new
  # privacy-policy-2, -3, … on every run. A draft found here is published instead.
  local id
  id=$("$WP" post list --post_type=page --name="$1" --post_status=publish,draft,pending,private --field=ID --posts_per_page=1)
  if [ -z "$id" ]; then
    id=$("$WP" post create --post_type=page --post_status=publish --post_name="$1" --post_title="$2" --post_content="${3:-}" --porcelain)
  else
    "$WP" post update "$id" --post_status=publish >/dev/null
  fi
  echo "$id"
}

ABOUT=$(page_id about-us "About Us")
CONTACT=$(page_id contact-us "Contact Us")
TRACK=$(page_id track-my-order "Track my order" "[woocommerce_order_tracking]")
SHIPPING=$(page_id shipping-policy "Shipping Policy")
REFUND=$(page_id refund-policy "Refund Policy")
PAYMENT=$(page_id payment-policy "Payment Policy")
PRIVACY=$(page_id privacy-policy "Privacy Policy")
"$WP" option update wp_page_for_privacy_policy "$PRIVACY" >/dev/null # checkout/account privacy links
TERMS=$(page_id terms-of-service "Terms of Service")
SHOP=$("$WP" option get woocommerce_shop_page_id)

# ---------- Menus ----------
recreate_menu() { # name -> id
  "$WP" menu delete "$1" >/dev/null 2>&1 || true
  "$WP" menu create "$1" --porcelain
}

MAIN=$(recreate_menu "Main menu")
"$WP" menu item add-custom "$MAIN" "Home" "$("$WP" option get home)/" >/dev/null
"$WP" menu item add-post "$MAIN" "$SHOP" --title="Catalogue" >/dev/null
"$WP" menu item add-post "$MAIN" "$ABOUT" >/dev/null
"$WP" menu item add-post "$MAIN" "$CONTACT" >/dev/null
"$WP" menu item add-post "$MAIN" "$TRACK" --title="Track my order" >/dev/null
"$WP" menu location assign "$MAIN" primary

FOOTER=$(recreate_menu "Footer")
for id in "$SHIPPING" "$REFUND" "$PAYMENT" "$PRIVACY" "$TERMS" "$CONTACT"; do
  "$WP" menu item add-post "$FOOTER" "$id" >/dev/null
done
"$WP" menu location assign "$FOOTER" footer

# ---------- Customizer content (from config/settings_data.json) ----------
"$WP" theme mod set announcement_enable 1
"$WP" theme mod set announcement_1_text "🍂 Autumn Sales Are Live – Limited Time"
"$WP" theme mod set announcement_2_text "🔥 Free UK Shipping + 30% OFF"

"$WP" theme mod set newsletter_title "Sign up and save"
"$WP" theme mod set newsletter_text "<p>Subscribe to get special offers, free giveaways, and once-in-a-lifetime deals.</p>"

"$WP" theme mod set contact_title "contact informations"
# Footer contact details: theme defaults are placeholders; enter ResellStreet's real details in Customizer →
# Footer (not seeded, so re-running this script never overwrites them).

# Collection page promo banner (templates/collection.json → promo-grid "banner" block)
"$WP" theme mod set banner_heading "Don’t Miss Out! 🔥"
"$WP" theme mod set banner_text "Limited pairs remaining!"

# Product page (templates/product.json → main-product blocks)
# Product page v2: trust lines under add to cart (check marks), quantity stepper, authenticity box, no payment icons
"$WP" theme mod set sales_point_1_icon truck
"$WP" theme mod set sales_point_1_text "Free UK shipping"
"$WP" theme mod set sales_point_2_icon lock
"$WP" theme mod set sales_point_2_text "Secure checkout"
"$WP" theme mod set sales_point_3_icon return
"$WP" theme mod set sales_point_3_text "Easy 30-day returns"
"$WP" theme mod set product_qty_enable 1
"$WP" theme mod set trust_badges_enable 0
"$WP" theme mod set product_auth_badge "100%"
"$WP" theme mod set product_auth_title "100% authentic"
"$WP" theme mod set product_auth_text "Verified by our team before shipping."
"$WP" theme mod set product_auth_link_label "How we check"
"$WP" theme mod set product_tab_1_title "Shipping information"
"$WP" theme mod set product_tab_1_content "<p>We offer FREE shipping across the UK!</p><p>The team carefully packs each order on the same day and ships it straight to your door. You’ll receive a tracking number as soon as your order has been dispatched. Delivery typically takes 4 to 10 business</p>"
"$WP" theme mod set product_tab_2_title "SATISFACTION GUARANTEE"
"$WP" theme mod set product_tab_2_content "<p>We stand by our quality and your satisfaction is our priority. If you're not completely happy with your purchase, let us know, and we’ll make it right!</p>"
# Tab 3 (customer service: hours + email) comes from the theme defaults (placeholders) / Customizer.

# ---------- WooCommerce catalog settings ----------
# Grid: 3 columns × 7 rows (main-collection per_row / rows_per_page). Card images are square (homepage v2 design).
"$WP" option update woocommerce_catalog_columns 3 >/dev/null
"$WP" option update woocommerce_catalog_rows 7 >/dev/null
if [ "$("$WP" option get woocommerce_thumbnail_cropping)" != "1:1" ]; then
  "$WP" option update woocommerce_thumbnail_cropping 1:1 >/dev/null
  "$WP" media regenerate --image_size=woocommerce_thumbnail --yes | tail -1
  "$WP" media regenerate --image_size=ci-card-sm --yes | tail -1
fi

"$WP" media regenerate --image_size=ci-card-sm --only-missing --yes | tail -1

# Size attributes sort numerically (imported terms have no custom order: "4, 4.5 … 11.5", not "10, 11, 4").
"$WP" eval 'foreach ( wc_get_attribute_taxonomies() as $a ) { if ( 0 === strpos( $a->attribute_name, "size" ) && "name_num" !== $a->attribute_orderby ) { wc_update_attribute( $a->attribute_id, array( "name" => $a->attribute_label, "slug" => $a->attribute_name, "type" => $a->attribute_type, "order_by" => "name_num", "has_archives" => $a->attribute_public ) ); } }'

# Cart & Checkout use the classic shortcodes (not blocks); customers can register on My Account.
"$WP" post update "$("$WP" option get woocommerce_cart_page_id)" --post_content='<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->' >/dev/null
"$WP" post update "$("$WP" option get woocommerce_checkout_page_id)" --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' >/dev/null
"$WP" option update woocommerce_enable_myaccount_registration yes >/dev/null

# Reviews: CusRev collects them (emails + photo upload on the review form); the theme displays them.
"$WP" option update ivole_attach_image yes >/dev/null

# ---------- Homepage (design: reference/homepage-v2) ----------
# Media files come from reference/media (downloaded once from the live store; reference/ is git-ignored).
MEDIA_DIR="$(cd "$(dirname "$0")/.." && pwd)/reference/media"
media_id() { # file title -> attachment id (imported once, tagged with _ci_seed)
  local id
  id=$("$WP" post list --post_type=attachment --meta_key=_ci_seed --meta_value="$1" --field=ID --posts_per_page=1)
  if [ -z "$id" ] && [ -f "$MEDIA_DIR/$1" ]; then
    id=$("$WP" media import "$MEDIA_DIR/$1" --title="$2" --porcelain)
    "$WP" post meta update "$id" _ci_seed "$1" >/dev/null
  fi
  echo "$id"
}
cat_id() { # slug name [parent slug] -> term id
  local id parent=""
  id=$("$WP" term list product_cat --slug="$1" --field=term_id)
  if [ -z "$id" ]; then
    [ -n "${3:-}" ] && parent="--parent=$("$WP" term list product_cat --slug="$3" --field=term_id)"
    id=$("$WP" term create product_cat "$2" --slug="$1" $parent --porcelain)
  fi
  echo "$id"
}
set_thumb() { [ -n "$2" ] && "$WP" term meta update "$1" thumbnail_id "$2" >/dev/null; }

SHOP_URL=$("$WP" eval 'echo get_permalink( wc_get_page_id( "shop" ) );')
NEW_URL="${SHOP_URL}?orderby=date"
mod() { "$WP" theme mod set "$1" "$2" >/dev/null; }

# Old homepage (v1) settings
for k in home_hero_autoplay home_hero_parallax home_hero_title_size home_tiles_enable home_tiles_title home_tiles_per_row \
  home_testimonials_enable home_testimonials_title home_testimonials_count home_testimonials_min home_testimonials_photos \
  home_testimonials_verified home_story_enable home_story_subtitle home_story_title home_story_text home_story_btn_label \
  home_story_btn_url home_story_image home_story_focal home_story_height home_story_layout home_story_parallax; do
  "$WP" theme mod remove "$k" >/dev/null 2>&1 || true
done
for i in 1 2 3; do for k in image image_mobile eyebrow title text btn1_label btn1_url btn2_label btn2_url focal align overlay; do
  "$WP" theme mod remove "home_hero_${i}_${k}" >/dev/null 2>&1 || true; done; done
for i in 1 2 3 4 5 6 7 8; do for k in cat title image; do "$WP" theme mod remove "home_tile_${i}_${k}" >/dev/null 2>&1 || true; done; done

# Brands (logos), Sneakers category, "trending" tag
"$WP" eval-file "$(dirname "$0")/home-data.php" --user="$("$WP" user list --role=administrator --field=ID --number=1)"

# Hero (homepage media prepared by the owner: reference/homepage-v2/assets → reference/media/home-v2)
mod home_hero_image "$(media_id home-v2/hero-desktop.jpg 'Hero - Step into the drip')"
mod home_hero_image_mobile "$(media_id home-v2/hero-mobile.jpg 'Hero - Step into the drip (mobile)')"
mod home_hero_focal "center center"
mod home_hero_overlay 35
mod home_hero_overlay_mobile 70
mod home_hero_eyebrow "New season drop"
mod home_hero_title "Step into the drip."
mod home_hero_text "Authentic sneakers, apparel and accessories from the brands that run the street. Shipped free across the UK."
mod home_hero_btn1_label "Shop new arrivals"
mod home_hero_btn1_url "$NEW_URL"
mod home_hero_btn2_label "Shop by brand"
mod home_hero_btn2_url "$("$WP" option get home)/#shop-by-brand"

# New arrivals
mod home_new_eyebrow "Just landed"
mod home_new_title "New arrivals"
mod home_new_link_label "View all"
mod home_new_link_url "$NEW_URL"

# Image with text
mod home_split_image "$(media_id home-v2/split-desktop.jpg "It's a mindset")"
mod home_split_image_mobile "$(media_id home-v2/split-mobile.jpg "It's a mindset (mobile)")"
mod home_split_focal "top center"
mod home_split_eyebrow "Resell Street is not just a shoe"
mod home_split_title "It's a mindset."
mod home_split_text "Designed for the streets, engineered for the bold. Every piece we stock blends clean design with undeniable presence — checked, authenticated and ready to wear. <strong>Street-luxury lives here.</strong>"
mod home_split_btn_label "Shop the drop"
mod home_split_btn_url "$SHOP_URL"

# Shop by brand: the 12 brands in this order
# "All brands" page: every brand as tiles ([ci_brands])
BRANDS_PAGE=$("$WP" post list --post_type=page --name=brands --field=ID --posts_per_page=1)
[ -z "$BRANDS_PAGE" ] && BRANDS_PAGE=$("$WP" post create --post_type=page --post_status=publish --post_title="Brands" --post_name=brands --post_content='<!-- wp:shortcode -->[ci_brands]<!-- /wp:shortcode -->' --porcelain)
mod home_brands_eyebrow "The labels"
mod home_brands_title "Shop by brand"
mod home_brands_link_label "All brands"
mod home_brands_link_url "$("$WP" post url "$BRANDS_PAGE")"
i=1; for b in nike adidas new-balance asics hoka on jordan puma brooks ugg reebok the-north-face; do
  mod "home_brand_${i}" "$("$WP" term list product_brand --slug="$b" --field=term_id)"; i=$((i+1))
done

# Banner
# The prepared "Drip banner mobile.png" has visible seams (stitched panels): phones use the desktop image, centred.
mod home_banner_image "$(media_id home-v2/banner-desktop.jpg 'Wear what moves the street')"
mod home_banner_image_mobile 0
mod home_banner_focal "center center"
mod home_banner_overlay 45
mod home_banner_eyebrow "Authenticated. Every pair."
mod home_banner_title "Wear what moves the street."
mod home_banner_text "The most-wanted silhouettes, sourced and checked so you don't have to."
mod home_banner_btn_label "Explore collection"
mod home_banner_btn_url "$SHOP_URL"

# Trending: one tab for now (more tabs = more categories in Customizer → Homepage → Trending styles)
mod home_trending_eyebrow "Most wanted right now"
mod home_trending_title "Trending styles"
mod home_trending_1_label "Sneakers"
mod home_trending_1_cat "$(cat_id sneakers "Sneakers")"
mod home_trending_btn_label "Shop trending"

# Video reviews: the owner's customer photos/clips (names, ratings, products: Products → Video reviews)
"$WP" eval-file "$(dirname "$0")/home-reviews.php" --user="$("$WP" user list --role=administrator --field=ID --number=1)"
mod home_videos_eyebrow "Real customers, real fits"
mod home_videos_title "Hear from our customers"

# Trust row
mod home_trust_1_icon truck;  mod home_trust_1_title "Free UK shipping";     mod home_trust_1_text "On every order, tracked to your door."
mod home_trust_2_icon lock;   mod home_trust_2_title "Secure checkout";      mod home_trust_2_text "Encrypted payments with all major cards."
mod home_trust_3_icon return; mod home_trust_3_title "Easy returns";         mod home_trust_3_text "Changed your mind? Send it back hassle-free."
mod home_trust_4_icon shield; mod home_trust_4_title "Authentic guaranteed"; mod home_trust_4_text "Every item checked before it ships."

# ---------- Logo ----------
# The ResellStreet logo ships with the theme (assets/brand, header + light footer version): no upload needed.
# An old uploaded logo would override it, so clear it.
"$WP" theme mod remove custom_logo >/dev/null 2>&1 || true

# ---------- Colour swatches (product page): colour per product from its title, hex from its photo ----------
# UK wording for the colour attribute label (slug pa_color unchanged).
"$WP" eval '$id = wc_attribute_taxonomy_id_by_name( "pa_color" ); $a = $id ? wc_get_attribute( $id ) : null; if ( $a && "Colour" !== $a->name ) { wc_update_attribute( $id, array( "name" => "Colour", "slug" => "color", "type" => $a->type, "order_by" => $a->order_by, "has_archives" => $a->has_archives ) ); }'
# Needs the site running (Playwright reads the photos); only products without a colour are touched.
node "$(dirname "$0")/product-colours.mjs" || echo "Colour swatches skipped (run: node tools/product-colours.mjs)"

# ---------- Shoe size guide (Shoes category) ----------
"$WP" eval-file "$(dirname "$0")/size-guide.php"

# ---------- WPForms: Contact Us ----------
if "$WP" plugin is-active wpforms-lite >/dev/null 2>&1 || "$WP" plugin is-active wpforms >/dev/null 2>&1; then
  "$WP" eval-file "$(dirname "$0")/contact-form.php" --user="$("$WP" user list --role=administrator --field=ID --number=1)"
fi
# Footer "Sign up and save" is the theme's built-in form (inc/subscribers.php): no shortcode.
"$WP" theme mod remove newsletter_shortcode >/dev/null 2>&1 || true

echo "Seed complete."
