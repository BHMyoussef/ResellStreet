# CustomImpulse theme — project rules

Custom classic WordPress theme for a WooCommerce shoe & apparel store (UK market, GBP).
Goal: recreate the look and feel of the owner's Shopify store https://novarende.com/ (Impulse theme).
Local site: http://resellstreet.local

## Architecture
- Standalone classic theme. No parent theme, no page builder, no block theme / FSE.
- `add_theme_support( 'woocommerce' )` plus `wc-product-gallery-zoom`, `wc-product-gallery-lightbox`, `wc-product-gallery-slider`.
- WooCommerce hooks first (`remove_action` / `add_action` in `inc/woocommerce.php`).
  Template overrides in `/woocommerce` only when hooks can't do it, and every override must be listed below.
- Design tokens are CSS custom properties in ONE file: `assets/css/tokens.css`, values taken from
  `reference/.../config/settings_data.json`. Components never hard-code colors, fonts or sizes. They use tokens.
- Mobile-first CSS (`min-width` media queries only; breakpoint 769px matches the reference).
- Vanilla JS (ES modules or IIFE, `defer`). No jQuery dependency in theme code, no frameworks.
  (WooCommerce core may still load jQuery on its own pages. Our code must not depend on it.)
- Fonts: same as the Shopify store when it is a Google Font, otherwise the closest Google equivalent.
  Always self-hosted as woff2 in `/assets/fonts`, never loaded from Google's CDN.
  - Headings: Tenor Sans 400 (same as live store)
  - Body: Outfit 300, bold 600 (same as live store; one variable woff2 file covers 300–600).
    NOTE: the export's settings_data.json says "Twentieth Century", but the live theme renders Outfit.
    The live store wins: match what customers see.
- When the export and the live site disagree, measure the live site (computed styles via Playwright) and follow it.

## Content & editability
- No hardcoded store content in templates. Editable via the Customizer (`inc/customizer.php`, defaults in
  `ci_defaults()`): announcement messages, hero slides (image/heading/text/buttons), collection tiles,
  image-with-text block, footer contact info, newsletter text, payment icons, copyright.
- Navigation via WP Menus (`primary`, `footer`). Real store content is seeded into the DB with
  `tools/seed.sh` (WP-CLI), never written into theme files.
- `tools/wp` = WP-CLI wrapper for the Local site (uses Local's PHP and MySQL socket).

## Commerce rules
- AJAX cart uses the WooCommerce Store API with its nonce (`Nonce` header, refreshed from responses).
  After every successful add/update, also trigger jQuery `added_to_cart` / `updated_cart_totals` on
  `document.body` *if jQuery is present*, so Meta Pixel plugins (PixelYourSite, Facebook for WooCommerce) still fire.
- Cart and Checkout pages use the classic shortcodes (`[woocommerce_cart]`, `[woocommerce_checkout]`), not blocks.
- Variation buttons: sold-out options crossed out and disabled; must work in AJAX variation mode
  (> 30 variations, `woocommerce_ajax_variation_threshold`). The native `<select>` stays in the DOM (visually hidden)
  for plugin compatibility. Buttons drive the select and fire `change`.
- Product page: sticky Add to Cart bar on mobile. Hook slots: `ci_product_after_title` (Judge.me stars)
  and `ci_product_after_add_to_cart` (trust badges, static theme component).
- Size guide: theme modal, content set per product category (term meta).
- Search is enabled (header icon + overlay + product search results).
- Shopify apps: reviews = Judge.me slot only; trust badges = static theme component; no "Get £5" widget.

## Performance (paid Meta traffic, mostly mobile)
- Keep CSS small. One critical global stylesheet plus per-template CSS enqueued conditionally.
- JS `defer`, loaded per template. No render-blocking third-party scripts.
- `font-display: swap`. Preload only the 1–2 above-the-fold font files.
- Responsive images (`srcset`/`sizes`), lazy-load below the fold, explicit width/height. The LCP image is never lazy and gets `fetchpriority="high"`.
- Dequeue WooCommerce/WP assets that aren't needed (block library CSS, wc-blocks, cart-fragments where replaced, etc.).

## Reference export (`/reference`, git- and dist-ignored)
- Read `config/settings_data.json` for design tokens and `templates/*.json` for section order and content.
- You may read `sections/`, `snippets/`, `assets/` to understand layout and behavior.
  NEVER copy Impulse Liquid, CSS or JS. All code is written from scratch.
- `reference/screens/` holds Playwright captures of novarende.com (1440px and 390px) for visual comparison.

## Workflow
- Build order: foundation → collection page → product page → AJAX cart drawer → homepage sections
  → cart/checkout/account styling → search overlay.
- After each section: screenshot http://resellstreet.local with `npm run shots:local` and compare
  against `reference/screens`. Then commit (one commit per completed section).

## Build status (update after every completed section)
| # | Section | Status |
|---|---------|--------|
| 0 | Setup: CLAUDE.md, ignores, Playwright captures in `reference/screens` | ✅ done |
| 1 | Foundation: tokens, fonts, buttons, announcement bar, sticky header, mobile menu drawer, footer | ✅ done |
| 2 | Collection page: promo banner, count, grid, product card, pagination | ✅ done |
| 3 | Product page: gallery, variation buttons (sold-out crossed, AJAX >30 variations, hidden native select), sticky mobile ATC, hook slots `ci_product_after_title` / `ci_product_after_add_to_cart`, trust badges, size guide modal (per category), tabs | ✅ done |
| 4 | AJAX cart drawer (Store API + nonce, jQuery `added_to_cart`/`updated_cart_totals` for Meta Pixel) + Quick Shop "Shop Now" on cards; dequeue WC jQuery scripts where unused | ✅ done |
| 5 | Homepage `front-page.php`: slideshow, collection tiles, testimonials, image-with-text (all Customizer) | ✅ done |
| 6 | Cart/checkout/account styling; switch Cart & Checkout pages to classic shortcodes; test logged-in cart (X-WP-Nonce) | ⏭ next |
| 7 | Search overlay (header icon already links to product search) + results page | ⬜ |
| 9 | WP-CLI script: import 5 products from Shopify CSV in `/reference` as variable (Size/Color) | ⬜ waiting for CSV |

### Decisions made so far
- Body font is Outfit (live store), not Twentieth Century (export). Desktop/mobile sizes measured from live computed styles.
- Drawers are native `<dialog data-drawer="name">`, opened by `[data-drawer-open="name"]`; API `window.CustomImpulse.openDrawer/closeDrawer`.
- Product card is built on WC loop hooks in `inc/woocommerce.php`; `ci_price_html()` is shared with the product page.
- Card images: WC thumbnail cropped 4:3 (600×450) + `ci-card-sm` 360×270 for srcset.
- Newsletter form only renders with a provider shortcode or action URL (Customizer → Footer). Provider not chosen yet.
- Product page lives in `inc/product.php` (hooks only). Variation buttons: radios drive WC's hidden selects
  (set in attribute order + native `change`), availability/price/stock from our own JSON map
  (`.ci-variant-data`, works in AJAX mode). Option availability only considers attributes *before* it;
  later attributes auto-switch to their first available option. WC's variation JS (jQuery) still runs.
- Sticky ATC clicks the main `.single_add_to_cart_button` (so step 4's AJAX handler only needs to hook the main form).
- Gallery = WC flexslider/zoom/photoswipe; first image shown before JS (opacity override) for LCP.
  Mobile "partial" peek = 75%-wide flex-viewport with overflow visible.
- Modals: `<dialog data-modal="name">` + `[data-modal-open]` (global.js). Size guide = `ci_size_guide` term meta on product_cat.
- Full-page Playwright captures of the product page on mobile show flexslider/sticky-bar artifacts; trust viewport shots + `npm run check:product`.
- Cart (`inc/cart.php`, `assets/js/cart.js`): drawer rendered client-side from Store API JSON (cache-safe).
  `Nonce` header on writes, refreshed from every response; invalid/missing nonce → GET /cart + one retry.
  Logged-in users also send `X-WP-Nonce`. Writes are queued (one at a time).
  Add-to-cart forms with unknown fields (plugin add-ons) fall back to a normal POST.
  After writes: refresh `wc-ajax=get_refreshed_fragments` + jQuery `added_to_cart` / `updated_cart_totals` /
  `removed_from_cart` / `wc_fragments_refreshed` (only when jQuery exists), plus native `ci:cart:*` events.
  Clicks during WC's ~300ms "disabled while re-checking" window are held and submitted when it re-enables.
- Quick Shop: REST `ci/v1/quick-shop/<id>` renders `template-parts/product/quick-shop.php` (forces WC AJAX
  variation mode to keep payload small); product.css/js are loaded on demand on first use. product.js runs
  "standalone" (sets variation_id/image itself) when WC's variation script isn't on the page.
- WC jQuery scripts (woocommerce, add-to-cart, blockUI, js-cookie) are dequeued except on product/cart/checkout/account
  → home and collection pages ship no jQuery (plugins may still add it).
- Homepage: `front-page.php` + `template-parts/home/*`, settings in `inc/customizer-home.php` (Customizer → Homepage;
  modules add defaults via the `ci_defaults` filter). Hero = up to 3 slides (+ optional mobile image via <picture>),
  first image eager + fetchpriority=high; mobile hero height = image ratio. Tiles use category thumbnails
  (`ci-square` 480 / `ci-square-lg` 720 crops). Testimonials = scroll-snap carousel. Parallax desktop-only (home.js).
- Homepage media for seeding lives in `reference/media/` (downloaded from the live store).
- If Chromium can't resolve novarende.com, run Playwright tools with `LIVE_IP=23.227.38.65`.

### Local site state (not in git: recreate with `tools/seed.sh`)
- Theme activated; WooCommerce "coming soon" mode turned OFF so Playwright sees the site.
- Seeded pages, menus (Main menu, Footer), Customizer content, logo, catalog 3×7, 4:3 thumbnails,
  homepage content + media, categories Shoes / Sets / Bags (Accessories existed) with thumbnails.
- Product data is still WooCommerce sample data until the Shopify CSV import (step 9).
- Dev test products from `npm run fixtures` (tools/fixtures.php): "Test Sneaker" (21 variations, inline mode) and
  "Test Sneaker XL" (33 variations, AJAX mode) in category "Shoes" (has a size guide). Delete them after step 9.

### Resume checklist
1. `npm run shots:local` / `npm run measure -- <profile>` (global|collection|product) / `npm run check:all`.
2. Compare with `reference/screens/*`, then continue with the next ⏭ row above.

## WooCommerce template overrides
_(none yet. Add every file in `/woocommerce` here with the reason)_
