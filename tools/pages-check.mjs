// Cart page, checkout and account checks on resellstreet.local (needs tools/fixtures.php: products, COD, TEST10, customer).
import { launch } from './lib/browser.mjs';

const SITE = 'http://resellstreet.local';
const PRODUCT = `${SITE}/product/test-sneaker-mens-casual-leather-sneakers/`;
let fails = 0;
const check = (label, ok, info = '') => {
  if (!ok) fails++;
  console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`);
};
const watch = (p) => {
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => {
    const where = m.location()?.url || '';
    // Third-party payment widgets (Stripe express checkout: Amazon Pay sandbox, …) log their own failures.
    if (m.type() !== 'error' || /status of 4\d\d/.test(m.text()) || (where && !where.startsWith(SITE))) return;
    errors.push(`${m.text()} ${where}`.trim());
  });
  return errors;
};
const addSneaker = async (p) => {
  await p.goto(PRODUCT, { waitUntil: 'networkidle' });
  await p.click('.single_add_to_cart_button');
  await p.waitForFunction(() => document.querySelector('dialog[data-drawer="cart"]')?.open, null, { timeout: 10000 });
  await p.waitForTimeout(500);
};
const emptyCart = (p) =>
  p.evaluate(async () => {
    const api = window.CustomImpulse?.cart?.api;
    if (!api) return;
    const c = await api('cart');
    for (const item of c.items) await api('cart/remove-item', { method: 'POST', body: { key: item.key } });
  });

const b = await launch();

/* ---------- Cart page ---------- */
{
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  const errors = watch(p);
  await addSneaker(p);
  await p.goto(`${SITE}/cart/`, { waitUntil: 'networkidle' });
  check('cart page uses the classic shortcode', (await p.$$('form.woocommerce-cart-form')).length === 1 && (await p.$$('.wp-block-woocommerce-cart')).length === 0);
  check('no selectWoo / select2 assets', await p.evaluate(() => !document.getElementById('selectWoo-js') && !document.getElementById('select2-css')));
  await p.evaluate(() => {
    window.__events = [];
    jQuery(document.body).on('updated_cart_totals', () => window.__events.push('updated_cart_totals'));
  });
  await p.click('.qty-stepper__btn[data-step="1"]');
  await p.waitForFunction(() => window.__events.includes('updated_cart_totals'), null, { timeout: 10000 });
  await p.waitForTimeout(300);
  check('stepper "+" updates the cart (qty 2, £91.50)', (await p.inputValue('input.qty')) === '2' && (await p.textContent('.cart-subtotal td')).includes('91.50'), await p.textContent('.cart-subtotal td'));
  check('steppers re-added after WooCommerce re-renders the form', (await p.$$('.qty-stepper')).length === 1);
  check('only the subtotal row is shown', await p.evaluate(() => getComputedStyle(document.querySelector('.cart_totals .order-total')).display === 'none'));
  // Wait for the result, not a load state: the cart page (Stripe express checkout) may never go idle before the reload.
  await p.click('.cart-item-remove');
  await p.waitForFunction(() => /empty/i.test(document.querySelector('.page-content')?.textContent || ''), null, { timeout: 30000 }).catch(() => {});
  check('"Remove" empties the cart', (await p.textContent('.page-content')).includes('empty'));
  check('no console errors (cart page)', errors.length === 0, errors.join(' | '));
  await ctx.close();
}

/* ---------- Guest checkout (mobile) ---------- */
{
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const p = await ctx.newPage();
  const errors = watch(p);
  await addSneaker(p);
  await p.goto(`${SITE}/checkout/`, { waitUntil: 'networkidle' });
  check('checkout uses the classic shortcode + minimal header', (await p.$$('form.checkout')).length === 1 && (await p.$$('.checkout-header')).length === 1 && (await p.$$('.site-nav')).length === 0);
  check('payment sits in the left column (after customer details)', await p.evaluate(() => document.querySelector('#customer_details').nextElementSibling?.matches('.woocommerce-checkout-payment')));

  const toggle = p.locator('.summary-toggle');
  check('mobile: order summary collapsed, total in the bar', !(await p.isVisible('#order_review')) && (await toggle.textContent()).includes('45.75'), await toggle.textContent());
  await toggle.click();
  check('summary toggle opens the order summary', await p.isVisible('#order_review'));

  await p.fill('#ci-coupon-code', 'NOPE');
  await p.press('#ci-coupon-code', 'Enter');
  await p.waitForSelector('[data-coupon-message]:not([hidden])', { timeout: 10000 });
  check('Enter in the coupon field does not submit the checkout', p.url().includes('/checkout/') && !p.url().includes('order-received'));
  check('invalid coupon → error message', /does not exist|not exist|invalid/i.test(await p.textContent('[data-coupon-message]')), await p.textContent('[data-coupon-message]'));

  await p.fill('#ci-coupon-code', 'TEST10');
  await p.click('[data-coupon-apply]');
  await p.waitForFunction(() => document.querySelector('.order-total')?.textContent.includes('41.18'), null, { timeout: 10000 });
  check('TEST10 applied: total £41.18, bar total updated', (await toggle.textContent()).includes('41.18'), await toggle.textContent());

  // Country change still switches the state/county field without selectWoo.
  await p.selectOption('#billing_country', 'US');
  await p.waitForTimeout(800);
  check('country → state field switches (native select)', await p.evaluate(() => document.querySelector('#billing_state')?.tagName === 'SELECT'));
  await p.selectOption('#billing_country', 'GB');
  await p.waitForTimeout(800);

  await p.fill('#billing_first_name', 'Test');
  await p.fill('#billing_last_name', 'Buyer');
  await p.fill('#billing_address_1', '1 Test Street');
  await p.fill('#billing_city', 'London');
  await p.fill('#billing_postcode', 'WC2H 9JQ');
  await p.fill('#billing_email', 'buyer@example.com');
  await p.waitForTimeout(800);
  await p.check('#payment_method_cod').catch(() => {});
  await Promise.all([p.waitForURL(/order-received/, { timeout: 20000 }), p.click('#place_order')]);
  await p.waitForLoadState('networkidle');
  check('order placed → order received page (single column)', await p.evaluate(() => document.body.classList.contains('ci-checkout-page--single') && !!document.querySelector('.woocommerce-order-overview')));
  await p.screenshot({ path: 'screens-local/order-received-390.png', fullPage: true });
  check('no console errors (checkout)', errors.length === 0, errors.join(' | '));
  await ctx.close();
}

/* ---------- Logged-in customer: AJAX cart keeps the session (X-WP-Nonce) ---------- */
{
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  const errors = watch(p);
  await p.goto(`${SITE}/my-account/`, { waitUntil: 'networkidle' });
  await p.fill('#username', 'ci-test-customer');
  await p.fill('#password', 'ci-test-Passw0rd!');
  await p.click('.woocommerce-form-login__submit');
  // Same URL after login: wait for the account page itself (a load-state wait can resolve on the login page).
  await p.waitForSelector('.woocommerce-MyAccount-navigation', { timeout: 30000 }).catch(() => {});
  check('customer logs in → account navigation', (await p.$$('.woocommerce-MyAccount-navigation')).length === 1);
  await p.screenshot({ path: 'screens-local/account-1440.png', fullPage: true });

  await addSneaker(p);
  check('logged-in pages send the REST nonce', await p.evaluate(() => !!window.ciCart.restNonce));
  const drawerItems = await p.$$eval('dialog[data-drawer="cart"] .cart-item', (n) => n.length);
  await p.goto(`${SITE}/cart/`, { waitUntil: 'networkidle' });
  const pageItems = await p.$$eval('.woocommerce-cart-form .cart_item', (n) => n.length);
  check('AJAX-added item is in the logged-in cart session', drawerItems === 1 && pageItems === 1, `drawer=${drawerItems} cart page=${pageItems}`);
  await emptyCart(p);
  check('no console errors (logged in)', errors.length === 0, errors.join(' | '));
  await ctx.close();
}

console.log(fails ? `\n${fails} check(s) failed` : '\nAll page checks passed');
await b.close();
process.exit(fails ? 1 : 0);
