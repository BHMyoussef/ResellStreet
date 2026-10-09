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

## WooCommerce template overrides
_(none yet. Add every file in `/woocommerce` here with the reason)_
