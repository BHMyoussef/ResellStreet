#!/usr/bin/env bash
# Seed the local site with the store's real content (from the Shopify export) via WP-CLI.
# Idempotent: safe to re-run. Content lives in the DB, never in theme files.
#   tools/seed.sh
set -euo pipefail
WP="$(cd "$(dirname "$0")" && pwd)/wp"

"$WP" theme activate CustomImpulse-theme

# ---------- Pages ----------
page_id() { # slug title [content]
  local id
  id=$("$WP" post list --post_type=page --name="$1" --post_status=publish --field=ID --posts_per_page=1)
  if [ -z "$id" ]; then
    id=$("$WP" post create --post_type=page --post_status=publish --post_name="$1" --post_title="$2" --post_content="${3:-}" --porcelain)
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
"$WP" theme mod set contact_text '<p><strong>Novarende</strong></p><p>71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, United Kingdom.</p><p><strong>E-mail</strong>: support@novarende.com</p><p><strong>Customer service hours:</strong><br/>Monday to Saturday: 09:00 am to 5:00 pm</p><p><strong>Shop hours:</strong></p><p>Monday to Friday: 10:00 am to 3:00 pm</p>'

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
"$WP" theme mod set product_tab_3_title "24/7 Customer Service"
"$WP" theme mod set product_tab_3_content "<p><strong>We’re Here for You — 24/7</strong></p><p>At <strong>Novareve</strong>, we’re always ready to help. Our customer service team is available <strong>24 hours a day, 7 days a week</strong> to assist you with any questions or support you need.</p><p>Feel free to reach out anytime via our <strong>contact form</strong> or email us at <a href=\"/contact-us/\" title=\"Contact Us\"><strong>support@novareve.com</strong></a>.</p>"

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
testimonial() { # n text author image
  "$WP" theme mod set "home_testimonial_$1_text" "$2" >/dev/null
  "$WP" theme mod set "home_testimonial_$1_author" "$3" >/dev/null
  "$WP" theme mod set "home_testimonial_$1_image" "$(media_id "$4" "$3")" >/dev/null
}
testimonial 1 "<p>The product looks exactly like the photos, maybe even better. Clean stitching, no issues, and arrived quicker than I expected.</p>" "Liam P." avatar-1.jpg
testimonial 2 "<p>Honestly wasn’t expecting this level of quality for the price. The fit is perfect and the material feels premium. Will definitely order again.</p>" "James R." avatar-2.jpg
testimonial 3 "<p>Really comfortable and stylish. I’ve worn it a few times now and keep getting compliments. Great value for money.</p>" "Daniel M." avatar-3.jpg
testimonial 4 "<p>Sizing was accurate and the shape holds well. The fabric feels durable but still soft. Honestly impressed.</p>" "Adam K." avatar-4.jpg
testimonial 5 "<p>Super happy with this purchase. Lightweight, stylish, and feels way more expensive than it is. Will be buying more colours.</p>" "Marc T." avatar-5.jpg

"$WP" theme mod set home_story_image "$(media_id banner-1.jpg 'Street-luxury')"
"$WP" theme mod set home_story_subtitle "Novareve is not just a shoe"
"$WP" theme mod set home_story_title "— it’s a mindset."
"$WP" theme mod set home_story_text "<p>Designed for the streets, engineered for the bold.<br/>Every pair blends clean design with undeniable presence.<br/><strong>Street-luxury lives here.</strong></p>"
"$WP" theme mod set home_story_focal "bottom center"

# ---------- Logo (once) ----------
if [ -z "$("$WP" theme mod get custom_logo --field=value 2>/dev/null)" ]; then
  LOGO=$("$WP" media import "https://novarende.com/cdn/shop/files/high-resolution-color-logo_1_600x.png" --title="Logo" --porcelain)
  "$WP" theme mod set custom_logo "$LOGO"
fi

echo "Seed complete."
