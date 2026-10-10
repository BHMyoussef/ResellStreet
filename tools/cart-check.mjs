// Cart drawer + Quick Shop behavior checks on resellstreet.local (needs tools/fixtures.php products).
import { chromium } from 'playwright';

const SITE = 'http://resellstreet.local';
const PRODUCT = `${SITE}/product/test-sneaker-mens-casual-leather-sneakers/`;
const COLLECTION = `${SITE}/product-category/shoes/`;
const b = await chromium.launch();
let fails = 0;
const check = (label, ok, info = '') => {
  if (!ok) fails++;
  console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`);
};
const watchErrors = (p, list) => {
  p.on('pageerror', (e) => list.push(e.message));
  // The deliberate 403 (stale nonce) and 400 (stock) responses are logged by the browser: expected.
  p.on('console', (m) => m.type() === 'error' && !/favicon|status of (400|403)/.test(m.text()) && list.push(m.text()));
};
const drawerState = (p) =>
  p.evaluate(() => {
    const d = document.querySelector('dialog[data-drawer="cart"]');
    return {
      open: d.open,
      items: [...d.querySelectorAll('.cart-item')].map((li) => ({
        name: li.querySelector('.cart-item__name').textContent,
        meta: li.querySelector('.cart-item__meta')?.textContent.replace(/\s+/g, ' ').trim(),
        qty: li.querySelector('.qty__input')?.value,
        price: li.querySelector('.cart-item__price').textContent.replace(/\s+/g, ' ').trim(),
      })),
      subtotal: d.querySelector('[data-cart-subtotal]').textContent,
      empty: !d.querySelector('[data-cart-empty]').hidden,
      error: d.querySelector('[data-cart-error]').hidden ? '' : d.querySelector('[data-cart-error]').textContent,
      dot: !document.querySelector('[data-cart-dot]').hidden,
    };
  });

/* ---------- Desktop: collection → Quick Shop → drawer ---------- */
{
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  const errors = [];
  watchErrors(p, errors);
  await p.goto(COLLECTION, { waitUntil: 'networkidle' });
  check('collection page loads without jQuery', await p.evaluate(() => !window.jQuery));

  const card = p.locator('ul.products > li', { hasText: /Test Sneaker [–-] Men/ });
  await card.hover();
  await p.waitForTimeout(300);
  check('"Shop Now" appears on hover', await card.locator('.product-card__quick').isVisible());
  await card.locator('.product-card__quick').click();
  await p.waitForSelector('dialog[data-modal="quick-shop"] .variant-buttons', { timeout: 10000 });
  await p.waitForTimeout(300);
  await p.screenshot({ path: 'screens-local/quick-shop-1440.png' });
  const qs = p.locator('dialog[data-modal="quick-shop"]');
  check('Quick Shop shows buttons + preselected variant', (await qs.locator('.variant-buttons__input:checked').count()) === 2);

  await qs.locator('.variant-buttons__input[value="khaki"] + label').click();
  await qs.locator('.variant-buttons__input[value="8"] + label').click();
  await p.waitForTimeout(200);
  check('Quick Shop: low stock for Khaki/8', /Low stock - 3/.test(await qs.locator('[data-product-stock]').textContent()));
  check('Quick Shop: variation_id set without WooCommerce JS', !!(await qs.locator('input[name="variation_id"]').inputValue()));

  await qs.locator('.single_add_to_cart_button').click();
  await p.waitForFunction(() => document.querySelector('dialog[data-drawer="cart"]').open, null, { timeout: 10000 });
  await p.waitForTimeout(600);
  let s = await drawerState(p);
  check('Quick Shop closed, drawer opened', !(await qs.evaluate((d) => d.open)) && s.open);
  check('drawer shows the item with options', s.items.length === 1 && /Color:\s?Khaki/.test(s.items[0].meta) && /Size \(USA\):\s?8/.test(s.items[0].meta), JSON.stringify(s.items[0]));
  check('line price with compare-at', /£92\.99.*£45\.75/.test(s.items[0]?.price || ''), s.items[0]?.price);
  check('subtotal', s.subtotal === '£45.75', s.subtotal);
  check('cart dot on', s.dot);
  await p.screenshot({ path: 'screens-local/cart-open-1440.png' });

  await p.locator('.cart-item [data-qty-step="1"]').click();
  await p.waitForTimeout(1200);
  s = await drawerState(p);
  check('+ → quantity 2, subtotal doubles', s.items[0]?.qty === '2' && s.subtotal === '£91.50', `${s.items[0]?.qty} ${s.subtotal}`);

  await p.locator('.cart-item [data-qty-step="1"]').click();
  await p.waitForTimeout(1500);
  s = await drawerState(p);
  const plusDisabled = await p.locator('.cart-item [data-qty-step="1"]').isDisabled();
  check('stock limit: qty 3 of 3, "+" disabled', s.items[0]?.qty === '3' && plusDisabled, `${s.items[0]?.qty} disabled=${plusDisabled}`);

  for (let i = 0; i < 3; i++) {
    await p.locator('.cart-item [data-qty-step="-1"]').click();
    await p.waitForTimeout(900);
  }
  await p.waitForTimeout(800);
  s = await drawerState(p);
  check('− to zero removes the item → empty state', s.items.length === 0 && s.empty && !s.dot, JSON.stringify(s.items));
  check('no console errors (desktop)', errors.length === 0, errors.join(' | '));
  await ctx.close();
}

/* ---------- Mobile: product page AJAX add + pixel events ---------- */
{
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const p = await ctx.newPage();
  const errors = [];
  watchErrors(p, errors);
  let fragmentsRequests = 0;
  p.on('request', (r) => r.url().includes('get_refreshed_fragments') && fragmentsRequests++);

  await p.goto(PRODUCT, { waitUntil: 'networkidle' });
  await p.evaluate(() => {
    window.__events = [];
    jQuery(document.body).on('added_to_cart updated_cart_totals removed_from_cart', (e, fragments) =>
      window.__events.push(`${e.type}:${typeof fragments}`)
    );
  });
  const url = p.url();
  await p.locator('.single_add_to_cart_button').click();
  await p.waitForFunction(() => document.querySelector('dialog[data-drawer="cart"]').open, null, { timeout: 10000 });
  await p.waitForTimeout(1200);
  let s = await drawerState(p);
  check('product page: added without reload', p.url() === url && s.items.length === 1, s.items.map((i) => i.meta).join());
  check('jQuery added_to_cart fired with fragments', (await p.evaluate(() => window.__events)).includes('added_to_cart:object'), (await p.evaluate(() => window.__events)).join());
  check('fragments refreshed for plugins', fragmentsRequests > 0);
  await p.screenshot({ path: 'screens-local/cart-open-390.png' });

  await p.locator('.cart-item [data-qty-step="1"]').click();
  await p.waitForTimeout(1500);
  check('jQuery updated_cart_totals fired', (await p.evaluate(() => window.__events)).includes('updated_cart_totals:undefined'));
  await p.locator('.cart-item .qty__input').fill('0');
  await p.locator('.cart-item .qty__input').press('Enter');
  await p.locator('.cart-drawer__title').click();
  await p.waitForTimeout(1500);
  check('jQuery removed_from_cart fired', (await p.evaluate(() => window.__events)).includes('removed_from_cart:object'));

  // Stale nonce (e.g. page served from a cache): the first write gets WooCommerce's "invalid nonce" 403.
  // cart.js must fetch the cart (fresh Nonce header) and retry with the new nonce.
  await p.goto(PRODUCT, { waitUntil: 'networkidle' });
  const sentNonces = [];
  let rejected = false;
  await ctx.route('**/wc/store/v1/cart/add-item*', async (route) => {
    sentNonces.push(route.request().headers()['nonce']);
    if (!rejected) {
      rejected = true;
      return route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ code: 'woocommerce_rest_invalid_nonce', message: 'Nonce is invalid.', data: { status: 403 } }) });
    }
    return route.continue();
  });
  await p.locator('.single_add_to_cart_button').click();
  await p.waitForFunction(() => document.querySelector('dialog[data-drawer="cart"]').open, null, { timeout: 10000 });
  await p.waitForTimeout(800);
  s = await drawerState(p);
  check('invalid nonce → fresh nonce fetched, retried once, item added', sentNonces.length === 2 && s.items.length === 1 && !s.error, `attempts=${sentNonces.length} items=${s.items.length} ${s.error}`);
  await ctx.unroute('**/wc/store/v1/cart/add-item*');
  await p.keyboard.press('Escape');

  // Store API error surfaces next to the button: all 3 Khaki/8 are already in the cart, add one more.
  await p.goto(PRODUCT, { waitUntil: 'networkidle' });
  await p.locator('.variant-buttons__input[value="khaki"] + label').click();
  await p.locator('.variant-buttons__input[value="8"] + label').click();
  await p.evaluate(async () => {
    const id = Number(document.querySelector('input[name="variation_id"]').value);
    await window.CustomImpulse.cart.api('cart/add-item', { method: 'POST', body: { id, quantity: 3 } });
  });
  await p.locator('.single_add_to_cart_button').click(); // also exercises the "held click" during WC's re-check
  await p.waitForSelector('form.cart .form-error', { timeout: 10000 });
  check('stock error shown under the button', /stock|cannot add|already/i.test(await p.locator('form.cart .form-error').textContent()), await p.locator('form.cart .form-error').textContent());

  // Plugin field → normal POST fallback
  await p.evaluate(() => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'gift_message';
    input.value = 'hi';
    document.querySelector('form.cart').append(input);
  });
  await p.locator('.variant-buttons__input[value="black"] + label').click();
  await p.waitForTimeout(500);
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle' }), p.locator('.single_add_to_cart_button').click()]);
  check('unknown plugin field → normal form POST', await p.evaluate(() => document.body.innerText.includes('added to your cart')));

  check('no console errors (mobile)', errors.length === 0, errors.join(' | '));

  // Clean up the cart for the next run
  await p.evaluate(async () => {
    const c = await window.CustomImpulse.cart.api('cart');
    for (const item of c.items) await window.CustomImpulse.cart.api('cart/remove-item', { method: 'POST', body: { key: item.key } });
  });
  await ctx.close();
}

console.log(fails ? `\n${fails} check(s) failed` : '\nAll cart checks passed');
await b.close();
process.exit(fails ? 1 : 0);
