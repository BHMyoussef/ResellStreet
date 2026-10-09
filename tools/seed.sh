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

# ---------- Logo (once) ----------
if [ -z "$("$WP" theme mod get custom_logo --field=value 2>/dev/null)" ]; then
  LOGO=$("$WP" media import "https://novarende.com/cdn/shop/files/high-resolution-color-logo_1_600x.png" --title="Logo" --porcelain)
  "$WP" theme mod set custom_logo "$LOGO"
fi

echo "Seed complete."
