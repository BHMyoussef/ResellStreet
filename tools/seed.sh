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
"$WP" theme mod set sales_point_1_icon truck
"$WP" theme mod set sales_point_1_text "Free UK shipping 🇬🇧"
"$WP" theme mod set sales_point_2_icon lock
"$WP" theme mod set sales_point_2_text "Secure Checkout"
"$WP" theme mod set product_tab_1_title "Shipping information"
"$WP" theme mod set product_tab_1_content "<p>We offer FREE shipping across the UK!</p><p>The team carefully packs each order on the same day and ships it straight to your door. You’ll receive a tracking number as soon as your order has been dispatched. Delivery typically takes 4 to 10 business</p>"
"$WP" theme mod set product_tab_2_title "SATISFACTION GUARANTEE"
"$WP" theme mod set product_tab_2_content "<p>We stand by our quality and your satisfaction is our priority. If you're not completely happy with your purchase, let us know, and we’ll make it right!</p>"
# Tab 3 (customer service: hours + email) comes from the theme defaults (placeholders) / Customizer.

# ---------- WooCommerce catalog settings ----------
# Grid: 3 columns × 7 rows (main-collection per_row / rows_per_page). Card images are 4:3 (landscape grid).
"$WP" option update woocommerce_catalog_columns 3 >/dev/null
"$WP" option update woocommerce_catalog_rows 7 >/dev/null
if [ "$("$WP" option get woocommerce_thumbnail_cropping)" != "custom" ] || [ "$("$WP" option get woocommerce_thumbnail_cropping_custom_height)" != "3" ]; then
  "$WP" option update woocommerce_thumbnail_cropping custom >/dev/null
  "$WP" option update woocommerce_thumbnail_cropping_custom_width 4 >/dev/null
  "$WP" option update woocommerce_thumbnail_cropping_custom_height 3 >/dev/null
  "$WP" media regenerate --image_size=woocommerce_thumbnail --yes
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

# ---------- Homepage (templates/index.json) ----------
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

HERO=$(media_id hero-1.jpg "Hero - Step Into Power")
"$WP" theme mod set home_hero_1_image "$HERO"
"$WP" theme mod set home_hero_1_eyebrow "Urban footwear built for those who move different."
"$WP" theme mod set home_hero_1_title "Step Into Power."
"$WP" theme mod set home_hero_1_btn1_label "Shop the Drop"
"$WP" theme mod set home_hero_1_btn1_url "$SHOP_URL"
"$WP" theme mod set home_hero_1_btn2_label "Explore Collection"
"$WP" theme mod set home_hero_1_btn2_url "$SHOP_URL"
"$WP" theme mod set home_hero_1_focal "top center"

T_SHOES=$(cat_id shoes "Shoes"); T_ACC=$(cat_id accessories "Accessories"); T_SETS=$(cat_id sets "Sets"); T_BAGS=$(cat_id bags "Bags")
set_thumb "$T_SHOES" "$(media_id tile-shoes.jpg 'Shoes')"
set_thumb "$T_ACC" "$(media_id tile-accessories.jpg 'Accessories')"
set_thumb "$T_SETS" "$(media_id tile-sets.jpg 'Sets')"
set_thumb "$T_BAGS" "$(media_id tile-bags.jpg 'Bags')"
for f in tile-shoes.jpg tile-accessories.jpg tile-sets.jpg tile-bags.jpg; do
  for size in ci-square ci-square-lg; do
    "$WP" media regenerate "$(media_id "$f" '')" --image_size=$size --only-missing --yes >/dev/null
  done
done
"$WP" theme mod set home_tiles_title "Choose Your Vibe"
i=1; for t in "$T_SHOES" "$T_ACC" "$T_SETS" "$T_BAGS"; do "$WP" theme mod set "home_tile_${i}_cat" "$t" >/dev/null; i=$((i+1)); done

"$WP" theme mod set home_testimonials_title "Hear From Our Customers"
# Testimonials come from real product reviews (Customizer → Homepage → Testimonials); remove the old slot mods.
for n in 1 2 3 4 5 6 7 8; do
  "$WP" theme mod remove "home_testimonial_${n}_text" "home_testimonial_${n}_author" "home_testimonial_${n}_image" "home_testimonial_${n}_stars" "home_testimonial_${n}_info" >/dev/null 2>&1 || true
done

"$WP" theme mod set home_story_image "$(media_id banner-1.jpg 'Street-luxury')"
"$WP" theme mod set home_story_subtitle "ResellStreet is not just a shoe"
"$WP" theme mod set home_story_title "— it’s a mindset."
"$WP" theme mod set home_story_text "<p>Designed for the streets, engineered for the bold.<br/>Every pair blends clean design with undeniable presence.<br/><strong>Street-luxury lives here.</strong></p>"
"$WP" theme mod set home_story_focal "bottom center"

# ---------- Logo ----------
# The ResellStreet logo ships with the theme (assets/brand, header + light footer version): no upload needed.
# An old uploaded logo would override it, so clear it.
"$WP" theme mod remove custom_logo >/dev/null 2>&1 || true

# ---------- Contact Us form (WPForms) ----------
if "$WP" plugin is-active wpforms-lite >/dev/null 2>&1 || "$WP" plugin is-active wpforms >/dev/null 2>&1; then
  "$WP" eval-file "$(dirname "$0")/contact-form.php" --user="$("$WP" user list --role=administrator --field=ID --number=1)"
fi

echo "Seed complete."
