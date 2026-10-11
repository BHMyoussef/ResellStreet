# CustomImpulse theme — project rules

Custom classic WordPress theme for ResellStreet, a WooCommerce shoe & apparel store (UK market, GBP).
Design reference: the Shopify store https://novarende.com/ (Impulse theme). Its look and feel is the model; its brand
(Novarende) is not used anywhere in the theme or content: the store is ResellStreet.
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
  - Headings: Montserrat 800, uppercase, 0.04em tracking (ResellStreet brand; matches the logo)
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
- Product page: sticky Add to Cart bar on mobile. Hook slots: `ci_product_after_title` (theme star rating + count)
  and `ci_product_after_add_to_cart` (trust badges, static theme component).
- Size guide: theme modal, content set per product category (term meta).
- Search is enabled (header icon + overlay + product search results).
- Shopify apps: trust badges = static theme component; no "Get £5" widget.

## Reviews (replaces the earlier Judge.me plan; Judge.me no longer supports WooCommerce)
- Reviews are native WooCommerce product reviews (comments, type `review`, meta `rating`), collected with the
  "Customer Reviews for WooCommerce" (CusRev) plugin. CusRev's job: review request emails + photo upload on the
  review form. Its frontend widgets, templates and CSS are NOT used; the theme renders everything (Loox-style):
  1. Star rating + review count under the product title (links to #reviews), via `ci_product_after_title`.
  2. Product page review section: rating summary, photo grid, review cards (stars, name, date, text, photos,
     verified-buyer badge), "Load more".
  3. Homepage testimonials carousel = real reviews (Customizer: number of reviews, min rating, photos required).
     No hardcoded testimonials.
- CusRev data: photos = comment meta `ivole_review_image2` (one row per attachment ID; legacy `ivole_review_image`,
  videos `ivole_review_video2` / `ivole_review_video`, count `ivole_media_count`). Verified buyer = WooCommerce
  comment meta `verified` OR CusRev `ivole_order` (order ID).
- Disable CusRev's frontend output wherever the theme replaces it; keep its review emails and the photo upload field
  on the review form working (its upload script stays, its CSS doesn't).
- Schema: WooCommerce already outputs Product JSON-LD with `aggregateRating`; CusRev filters it. The theme adds review
  schema only if it's missing, so it never appears twice. (Verified: exactly once; theme adds none.)
- Implementation (`inc/reviews.php`, `template-parts/product/reviews.php` + `review-card.php`, product.js `initReviews`):
  stars = `ci_stars()` (one element, CSS mask, partial fill, in base.css). Section: summary + breakdown, customer
  photo strip, masonry cards (CSS grid + JS row spans), "Load more" via REST `ci/v1/reviews/<id>?page=N`
  (`ci_reviews_per_page` filter, default 6), photo lightbox dialog, review form = WooCommerce form args + CusRev's
  upload field (`ivole_attach_image` = yes, set by seed).
  CusRev frontend CSS/JS/colcade dequeued site-wide (it loads them + jQuery on every page). On product pages its upload
  script is loaded on demand when "Write a review" is hovered/opened; a file picked before it's ready is replayed via a
  jQuery ready callback (CusRev binds in one, and jQuery 3 runs those async). CusRev's email review form is a
  standalone page with its own assets (unaffected).
- Shopify CSV → WooCommerce CSV (`tools/shopify-to-woo.mjs`, `npm run convert:shopify -- "X.csv"
  [-o out.csv] [--category "A > B"]`; no WP needed). Input: Shopify exports in `reference/Shopify Export/` (a bare file
  name is looked up there). Output = exactly the columns of WooCommerce's sample_products.csv (weight/dimension headers
  in store units kg/cm, so the importer auto-maps them), written to `reference/WooCommerce Import/<name> - WooCommerce.csv`;
  import via Products → Import (downloads the images). User rules: Shopify
  metafields ignored; colours simple ("light-blue-1" → Light Blue, gray → Grey); sizes "-" → "." (nothing else changed,
  e.g. 39/40 stay). Options with one value (the product's only colour) = plain attribute; variations only on the
  varying options (variation rows only carry those). Price → sale, Compare At → regular (decimal comma → dot). Optional hand-added columns `brand` → WC "Brands" column
  (product_brand) and category = `--category` > brand > `collection` (user choice: Shoes > Adidas > Adidas Samba). SKUs generated from the handle
  link variations to parents (re-import with "update existing"). Verified with WC's importer in a rolled-back
  transaction (HK Collection: 10 products, 160 variations, 0 failed).
- `npm run cleanup:local` (tools/cleanup-local.php): wipes products, test/sample images, orders, test user/coupon,
  old size attributes, empty sample categories. Back up first (`tools/wp db export file.sql`; the wrapper puts Local's
  mysqldump on PATH).
- Search (`inc/search.php`, `template-parts/header/search.php`, `search.js`): <dialog> over the header row (Impulse
  look), predictive results from REST `ci/v1/search?q=` (≈1 KB: name, link, 150px thumb, `ci_price_html`) instead of
  the Store API (≈13 KB+ with all gallery srcsets). Debounced, aborts stale requests, caches per query, combobox
  keyboard nav. Results page = WC product archive with visible title + search form; plain `?s=` → redirected to
  `post_type=product`. WordPress adds `search-results` to <body> on search pages: never use that as a component class.
- Homepage testimonials = `ci_home_testimonials()` (inc/customizer-home.php): approved reviews on published products,
  newest first, filtered by Customizer (count, min rating, photos required, verified only); cached in a transient
  keyed by settings + `ci_reviews_cache_version` (bumped on any comment/comment-meta change). No hardcoded slots.
- Test reviews: `npm run fixtures` (8 reviews, tagged `_ci_fixture`). WooCommerce excludes reviews from untyped
  comment queries: always pass `'type' => 'review'`.

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
| 6 | Cart/checkout/account styling; switch Cart & Checkout pages to classic shortcodes; test logged-in cart (X-WP-Nonce) | ✅ done |
| 3b | Reviews on the product page (CusRev data, theme display): stars under title, review section, test reviews | ✅ done |
| 5b | Homepage testimonials from real reviews (Customizer: count, min rating, photos) | ✅ done |
| 7 | Search overlay (header icon already links to product search) + results page | ✅ done |
| 9 | Product import from the Shopify CSV in `/reference` (restarting from scratch; old import tools removed) | ⏭ next |

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
- Modals: `<dialog data-modal="name">` + `[data-modal-open]` (global.js, fires `ci:modal:open` with the opener). Size guide =
  `ci_size_guide` term meta on product_cat (Shoes content: `tools/size-guide.php`, UK/EU/foot cm, run by seed.sh; user:
  no US columns), rendered by `ci_size_guide_modal()`: bottom sheet on phones, the selected size's row highlighted.
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
- Cart/checkout/account (`inc/wc-pages.php`, `wc-pages.css/js`), hooks only:
  cart = Impulse layout, subtotal only (no shipping calc / coupon on cart), "Check out" button, auto-update steppers
  (clicks WC's hidden Update button → WC fires updated_cart_totals).
  Checkout = Shopify-checkout look from checkout_* settings (tokens `--checkout-*`, system font), minimal
  header/footer (`ci_is_minimal_checkout()`), payment moved to the left column, discount code row inside the
  summary table (posts to wc-ajax=apply_coupon, then triggers WC `update_checkout`), mobile summary toggle.
  Order-received/order-pay = single column (`ci-checkout-page--single`).
  Checkout errors (user rule): field errors → highlighted field + message under it (WC `li[data-id]` moved out of
  the notice list; "Billing "/"Shipping " prefix stripped; blur validation gets messages too); general errors →
  `.checkout-errors` banner above the form, page scrolls to top and focuses it. Field-only errors: no banner,
  focus + scroll to the first invalid field. Error text uses `--checkout-error-text` (darkened #ff6d6d for contrast).
  selectWoo/select2 and wc-add-to-cart are dequeued everywhere (native selects; country→state still works).
- Full-width images on phones: add a `<source media="(max-width: 768px)">` with `ci_mobile_srcset()` (files ≤ 1024px),
  otherwise 3x phones pick the 1600px+ original. Gallery thumb srcset capped at 300px (flexslider sets srcset before sizes).
- Add to Cart clicked before WC resolves the default variation (variation_id 0) is held in cart.js until it resolves.
- Global CSS stays as separate cached files: inlining it was measured (Lighthouse) and gave no LCP gain. Both font preloads stay.
- MutationObserver callbacks must only write to the DOM when the value changed (writing textContent inside an
  observed subtree re-triggers the observer → infinite loop; this froze the checkout once).
- My account (user choice 2026-10-11): same Shopify-account look as the checkout (`--checkout-*` tokens, system
  font, cards, flat buttons) inside the normal site header/footer. `inc/account.php`, `template-parts/account/*`,
  `account.css/js` (account pages don't load wc-pages.css/js). Menu: Overview, Orders, Addresses, Account details,
  Log out (Payment methods only if the customer has a saved card; Downloads dropped). Overview replaces the dashboard
  template; Orders = cards (own renderer, still fires `woocommerce_before/after_account_orders` and uses
  `wc_get_account_orders_actions`). Wording via `gettext_woocommerce` on account pages only.
- Brand (rebrand 2026-10-11, user rules): ResellStreet. Navy #1C253B (text, buttons, header icons, footer bg),
  sand #C1A584 (announcement bar with navy text, sale badges, cart dot, selected variation, button hover, footer links
  on navy), background #FAFAFA (cards/fields: `--color-surface` white). Red only for sale prices / "Save X%".
  Never sand text on a light background (2.3:1). Muted text = `--color-text-muted` (#606676), not see-through navy.
  Headings Montserrat 800 uppercase 0.04em (self-hosted). Logo + favicon are bundled (assets/brand, regenerate with
  tools/brand-logo.py / brand-favicon.py); an uploaded logo/site icon overrides them. novarende.com stays only as the
  Shopify design reference (tools, reference captures).
- Contact Us = WPForms form (Name, Email, Message, "Send", like the live store) created by `tools/contact-form.php`
  (seed.sh runs it; idempotent, form id in option `ci_contact_form_id`). WPForms styling = "Base styles only";
  the look is `assets/css/forms.css` (only on pages with a form) + theme `.btn` on the submit button.
- Footer "Sign up and save" = the theme's own form, no plugin (user choice): posts to admin-post `ci_subscribe`
  (`inc/subscribers.php`; honeypot + min fill time, no nonce because pages are cached), global.js submits in place.
  Emails go to `{prefix}ci_subscribers`, no email per sign-up (Users → Subscribers: search, delete, CSV; privacy
  export/erase). A shortcode / external URL in Customizer → Footer replaces it.

### Local site state (not in git: recreate with `tools/seed.sh`)
- Theme activated; WooCommerce "coming soon" mode turned OFF so Playwright sees the site.
- Seeded pages, menus (Main menu, Footer), Customizer content, logo, catalog 3×7, 4:3 thumbnails,
  homepage content + media, categories Shoes / Sets / Bags (Accessories existed) with thumbnails.
- Catalogue = test fixtures only (2026-10-10): the 313 imported Shopify products were dropped (backup of the imported
  state: `resellstreet/backup-before-drop-import-2026-10-10.sql`) and `npm run fixtures` re-run. WooCommerce sample
  data is gone (removed by cleanup:local), so the 2 fixture reviews on "Hoodie" are skipped. Categories: Shoes,
  Accessories (under Clothing), Sets, Bags, Uncategorized.
- The test suites (`npm run check:*`) depend on `npm run fixtures` (test products/reviews/customer); re-running it
  re-adds those test items to the store.
- Dev test products from `npm run fixtures` (tools/fixtures.php): "Test Sneaker" (21 variations, inline mode) and
  "Test Sneaker XL" (33 variations, AJAX mode) in category "Shoes" (has a size guide). Delete them after step 9.
  Fixtures also enable Cash on delivery, create coupon TEST10 (10%) and customer `ci-test-customer`
  (password `ci-test-Passw0rd!`). Test orders exist from `npm run check:pages`.
- Cart & Checkout pages contain the classic shortcodes; My Account registration is enabled (both via seed.sh).
- Plugin "Customer Reviews for WooCommerce" (CusRev) installed + active; photo uploads on (`ivole_attach_image`, seed.sh).

### Resume checklist
1. `npm run shots:local` / `npm run measure -- <profile>` (global|collection|product|home) / `npm run check:all`.
   Product profile/screens point at the imported Flaireve product: re-import it (step 9) to use them.
   Tests select fixture products by exact title and don't assume catalogue size.
2. Compare with `reference/screens/*`, then continue with the next ⏭ row above.

## WooCommerce template overrides
_(none yet. Add every file in `/woocommerce` here with the reason)_

Use the Grep/Glob/Read/Edit tools for searching and editing files, never grep/find/cat/python heredocs in Bash.