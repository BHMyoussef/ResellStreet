// Checkout error UX checks: field errors under the field (no top banner), general errors as a top banner.
import { chromium } from 'playwright';

const SITE = 'http://resellstreet.local';
let fails = 0;
const check = (label, ok, info = '') => {
  if (!ok) fails++;
  console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`);
};
const b = await chromium.launch();

for (const [w, h] of [[1440, 900], [390, 844]]) {
  const ctx = await b.newContext({ viewport: { width: w, height: h }, isMobile: w < 769, hasTouch: w < 769 });
  const p = await ctx.newPage();
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  console.log(`\n— ${w}px`);
  await p.goto(`${SITE}/product/test-sneaker-mens-casual-leather-sneakers/`, { waitUntil: 'networkidle' });
  await p.click('.single_add_to_cart_button');
  await p.waitForFunction(() => document.querySelector('dialog[data-drawer="cart"]')?.open, null, { timeout: 10000 });
  await p.goto(`${SITE}/checkout/`, { waitUntil: 'networkidle' });

  const inline = (id) => p.evaluate((id) => {
    const row = document.getElementById(id)?.closest('.form-row');
    return { invalid: row?.classList.contains('woocommerce-invalid'), msg: row?.querySelector('.checkout-inline-error-message')?.textContent || '' };
  }, id);
  const banner = () => p.evaluate(() => document.querySelector('.checkout-errors')?.textContent.trim() || '');

  // 1. Empty submit → field errors only
  await p.click('#place_order');
  await p.waitForSelector('#billing_first_name_field .checkout-inline-error-message', { timeout: 15000 });
  await p.waitForTimeout(600);
  const fn = await inline('billing_first_name');
  check('empty submit: first name highlighted + message under it', fn.invalid && /First name/i.test(fn.msg), fn.msg);
  check('empty submit: no field errors in a top banner', (await banner()) === '' && !(await p.$('form.checkout > .woocommerce-NoticeGroup')));
  check('focus moved to the first invalid field', await p.evaluate(() => document.activeElement?.id === 'billing_first_name'));
  if (w === 1440) await p.screenshot({ path: 'screens-local/checkout-field-errors-1440.png' });

  // 2. Fixing a field clears its error on blur
  await p.fill('#billing_first_name', 'Test');
  await p.press('#billing_first_name', 'Tab');
  await p.waitForTimeout(300);
  const fixed = await inline('billing_first_name');
  check('fixed field: highlight + message removed', !fixed.invalid && !fixed.msg);

  // 3. Client-side blur validation shows a message
  await p.fill('#billing_email', 'not-an-email');
  await p.press('#billing_email', 'Tab');
  await p.waitForTimeout(300);
  const em = await inline('billing_email');
  check('invalid email on blur: message under the field', em.invalid && /valid email/i.test(em.msg), em.msg);

  // 4. Server-side field error (UK postcode) → under the field
  await p.fill('#billing_last_name', 'Buyer');
  await p.fill('#billing_address_1', '1 Test Street');
  await p.fill('#billing_city', 'London');
  await p.fill('#billing_postcode', 'NOT A POSTCODE');
  await p.fill('#billing_email', 'buyer@example.com');
  await p.waitForTimeout(800);
  await p.click('#place_order');
  await p.waitForSelector('#billing_postcode_field .checkout-inline-error-message', { timeout: 15000 });
  await p.waitForTimeout(500);
  const pc = await inline('billing_postcode');
  check('server postcode error: under the postcode field', pc.invalid && /postcode/i.test(pc.msg), pc.msg);
  check('server field error: no top banner', (await banner()) === '');

  // 5. General error → banner above the checkout
  await p.fill('#billing_postcode', 'WC2H 9JQ');
  await p.waitForTimeout(800);
  await p.route('**/?wc-ajax=checkout', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ result: 'failure', messages: '<ul class="woocommerce-error" role="alert"><li>Your card was declined. Please try another payment method.</li></ul>', refresh: false, reload: false }),
    })
  );
  await p.click('#place_order');
  await p.waitForSelector('.checkout-errors .woocommerce-error', { timeout: 15000 });
  await p.waitForTimeout(700);
  const general = await p.evaluate(() => {
    const slot = document.querySelector('.checkout-errors');
    const r = slot.getBoundingClientRect();
    return { text: slot.textContent.trim(), beforeForm: slot.nextElementSibling?.matches('form.checkout'), inView: r.top >= -5 && r.top < innerHeight, focused: document.activeElement?.closest('.checkout-errors') !== null };
  });
  check('general error: banner above the checkout form', general.beforeForm && /declined/.test(general.text), general.text);
  check('general error: scrolled into view and focused', general.inView && general.focused, JSON.stringify(general));
  check('general error: no inline field messages left', (await p.$$('.checkout-inline-error-message')).length === 0);
  if (w === 1440) await p.screenshot({ path: 'screens-local/checkout-general-error-1440.png' });
  await p.unroute('**/?wc-ajax=checkout');

  check('no console errors', errors.length === 0, errors.join(' | '));
  await p.evaluate(async () => {
    const api = window.CustomImpulse?.cart?.api;
    if (!api) return;
  });
  await ctx.close();
}

console.log(fails ? `\n${fails} check(s) failed` : '\nAll checkout error checks passed');
await b.close();
process.exit(fails ? 1 : 0);
