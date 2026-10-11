// Product page behavior checks on resellstreet.local (needs tools/fixtures.php products).
import { launch } from './lib/browser.mjs';
const BASE = 'http://resellstreet.local/product/';
const b = await launch();
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const p = await ctx.newPage();
const errors = []; p.on('pageerror', (e) => errors.push(e.message)); p.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
let fails = 0;
const check = (label, ok, info = '') => { if (!ok) fails++; console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`); };

const state = () => p.evaluate(() => ({
  checked: [...document.querySelectorAll('.variant-buttons__input:checked')].map((i) => i.value),
  disabled: [...document.querySelectorAll('.variant-buttons__input:disabled')].map((i) => i.value),
  selects: [...document.querySelectorAll('.variations select')].map((s) => s.value),
  variationId: document.querySelector('input.variation_id')?.value,
  price: document.querySelector('[data-product-price]')?.textContent.replace(/\s+/g, ' ').trim(),
  stock: document.querySelector('[data-product-stock]')?.textContent.trim(),
  atcDisabled: document.querySelector('.single_add_to_cart_button')?.classList.contains('disabled'),
}));
const pick = async (value) => { await p.locator(`.variant-buttons__input[value="${value}"] + label`).click(); await p.waitForTimeout(300); };

// ---- ≤ 30 variations (inline data) ----
await p.goto(BASE + 'test-sneaker-mens-casual-leather-sneakers/', { waitUntil: 'networkidle' });
let s = await state();
check('preselects first in-stock variant', s.checked.join() === 'black,6' && s.selects.join() === 'black,6', s.checked.join());
check('WooCommerce found the variation', !!s.variationId && !s.atcDisabled, `variation_id=${s.variationId}`);
check('sold-out Black/10.5 crossed out', s.disabled.includes('10-5'), s.disabled.join());
check('price shows sale + savings', /£92\.99.*£45\.75.*Save 51%/.test(s.price), s.price);
check('selects hidden from keyboard', await p.$$eval('.variations select', (els) => els.every((e) => e.tabIndex === -1)));

await pick('white');
s = await state();
check('White: size 6 sold out → auto-switched to 7', s.checked.join() === 'white,7' && s.selects.join() === 'white,7', s.checked.join());
check('White: 6 and 7.5 crossed out', s.disabled.includes('6') && s.disabled.includes('7-5') && !s.disabled.includes('10-5'), s.disabled.join());
check('White/7 found by WooCommerce', !!s.variationId && !s.atcDisabled, `variation_id=${s.variationId}`);

await pick('khaki'); await pick('8');
s = await state();
check('Khaki keeps a valid size, then 8 selected', s.selects.join() === 'khaki,8', s.selects.join());
check('low stock message for Khaki/8', /Low stock - 3 items left/.test(s.stock), s.stock);

// Keyboard: arrow keys move within a radio group
await p.locator('.variant-buttons__input[value="8"]').focus();
await p.keyboard.press('ArrowRight'); await p.waitForTimeout(300);
s = await state();
check('arrow key selects next size', s.selects[1] === '8-5', s.selects.join());

// Size guide modal
await p.click('.size-guide-link');
check('size guide opens', await p.$eval('dialog[data-modal="size-guide"]', (d) => d.open));
await p.keyboard.press('Escape'); await p.waitForTimeout(200);
check('size guide closes with Esc', !(await p.$eval('dialog[data-modal="size-guide"]', (d) => d.open)));

// Sticky bar
const stickyVisible = () => p.$eval('[data-sticky-atc]', (e) => e.classList.contains('is-visible'));
check('sticky bar hidden at top', !(await stickyVisible()));
await p.evaluate(() => window.scrollTo(0, document.querySelector('.single_add_to_cart_button').getBoundingClientRect().bottom + scrollY + 400));
await p.waitForTimeout(500);
check('sticky bar shows after scrolling past the button', await stickyVisible());
check('sticky price follows variation', (await p.$eval('[data-sticky-price]', (e) => e.textContent)).includes('45.75'));
await p.click('[data-sticky-button]');
await p.waitForFunction(() => document.querySelector('dialog[data-drawer="cart"]')?.open, null, { timeout: 10000 }).catch(() => {});
const added = await p.evaluate(() => document.querySelectorAll('dialog[data-drawer="cart"] .cart-item').length > 0);
check('sticky button adds to cart via the main button (AJAX drawer)', added);
await p.keyboard.press('Escape');
await p.waitForTimeout(600);
check('cart dot visible after add', await p.$eval('[data-cart-dot]', (e) => !e.hidden));

// ---- > 30 variations (AJAX mode) ----
await p.goto(BASE + 'test-sneaker-xl-33-variations/', { waitUntil: 'networkidle' });
check('XL product is in AJAX mode', await p.$eval('form.variations_form', (f) => f.dataset.product_variations === 'false'));
s = await state();
check('AJAX: White crossed out (all sold out)', s.disabled.includes('white'), s.disabled.join());
check('AJAX: Black 5 / 5.5 crossed out, preselected Black/6', s.disabled.includes('5') && s.disabled.includes('5-5') && s.checked.join() === 'black,6', `${s.disabled.join()} | ${s.checked.join()}`);
await pick('khaki'); await pick('9-5'); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
s = await state();
check('AJAX: Khaki/9.5 resolved by WooCommerce', s.selects.join() === 'khaki,9-5' && !!s.variationId && !s.atcDisabled, `${s.selects.join()} id=${s.variationId}`);
check('AJAX: Khaki enables sizes 5/5.5', !s.disabled.includes('5') && !s.disabled.includes('5-5'), s.disabled.join());

check('no console errors', errors.length === 0, errors.join(' | '));
await p.evaluate(async () => {
  const api = window.CustomImpulse?.cart?.api;
  if (!api) return;
  const c = await api('cart');
  for (const item of c.items) await api('cart/remove-item', { method: 'POST', body: { key: item.key } });
});
console.log(fails ? `\n${fails} check(s) failed` : '\nAll product checks passed');
await b.close();
process.exit(fails ? 1 : 0);
