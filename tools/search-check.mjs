// Search overlay + results page checks on resellstreet.local (needs tools/fixtures.php products).
import { chromium } from 'playwright';

const SITE = 'http://resellstreet.local';
let fails = 0;
const check = (label, ok, info = '') => {
  if (!ok) fails++;
  console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`);
};
const b = await chromium.launch();

for (const [w, h] of [[1440, 900], [390, 844]]) {
  const p = await b.newPage({ viewport: { width: w, height: h }, isMobile: w < 769, hasTouch: w < 769 });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  console.log(`\n— ${w}px`);
  await p.goto(`${SITE}/`, { waitUntil: 'networkidle' });

  const isOpen = () => p.$eval('[data-search-dialog]', (d) => d.open);
  await p.click('.header-icon--search');
  await p.waitForTimeout(400);
  const bar = await p.$eval('.search-dialog__bar', (e) => Math.round(e.getBoundingClientRect().height));
  check('icon opens the overlay over the header row, input focused', (await isOpen()) && (await p.evaluate(() => document.activeElement?.name === 's')) && bar === (w < 769 ? 59 : 94), `bar=${bar}px`);

  // Typing fast: only the latest query is rendered
  await p.type('.search-dialog__input', 'hood', { delay: 40 });
  await p.waitForSelector('.search-result', { timeout: 10000 });
  await p.waitForTimeout(400);
  const names = await p.$$eval('.search-result__name', (n) => n.map((x) => x.textContent));
  check('predictive results for the latest query only', names.length >= 1 && names.every((n) => /hood/i.test(n)), names.join(', '));
  await p.fill('.search-dialog__input', 'sneaker');
  await p.waitForFunction(() => [...document.querySelectorAll('.search-result__name')].some((n) => /sneaker/i.test(n.textContent)), null, { timeout: 10000 });
  const first = await p.$eval('.search-result', (a) => ({ img: /-150x150\.|placeholder/.test(a.querySelector('img').src), price: a.querySelector('.search-result__price').textContent.replace(/\s+/g, ' ').trim() }));
  check('result shows thumbnail + price with savings', first.img && /£\d+\.\d{2}.*£\d+\.\d{2}.*Save \d+%/.test(first.price), JSON.stringify(first));
  const shown = await p.$$eval('.search-result', (n) => n.length);
  const all = await p.getAttribute('.search-panel__all', 'href');
  check('"View all results" links to the product search', /[?&]s=sneaker/.test(all) && /post_type=product/.test(all), all);
  const announced = await p.textContent('[data-search-status]');
  const total = Number((announced.match(/\d+/) || [0])[0]);
  check('result count announced for screen readers', total >= shown && shown >= 2 && /products? found/.test(announced), announced);
  if (w === 1440) await p.screenshot({ path: 'screens-local/search-open-1440.png' });
  else await p.screenshot({ path: 'screens-local/search-open-390.png' });

  // Keyboard: ArrowDown + Enter opens the first product
  await p.focus('.search-dialog__input');
  await p.keyboard.press('ArrowDown');
  check('ArrowDown highlights the first result', await p.$eval('.search-result', (a) => a.classList.contains('is-active')));
  await Promise.all([p.waitForURL(/\/product\//, { timeout: 10000 }), p.keyboard.press('Enter')]);
  check('Enter on a highlighted result opens the product', /\/product\//.test(p.url()));

  // Close: Esc, ✕, backdrop
  await p.goto(`${SITE}/`, { waitUntil: 'networkidle' });
  await p.click('.header-icon--search');
  await p.waitForTimeout(300);
  await p.keyboard.press('Escape');
  await p.waitForTimeout(400);
  const escClosed = !(await isOpen());
  await p.click('.header-icon--search');
  await p.waitForTimeout(300);
  await p.click('[data-search-close]');
  await p.waitForTimeout(400);
  const xClosed = !(await isOpen());
  await p.click('.header-icon--search');
  await p.waitForTimeout(300);
  await p.mouse.click(w / 2, h - 40); // backdrop
  await p.waitForTimeout(400);
  check('closes with Esc, ✕ and a click outside; page scroll unlocked', escClosed && xClosed && !(await isOpen()) && !(await p.evaluate(() => document.documentElement.classList.contains('has-drawer'))));

  // No results
  await p.click('.header-icon--search');
  await p.fill('.search-dialog__input', 'zzqqxx');
  await p.waitForSelector('.search-panel__empty', { timeout: 10000 });
  check('no results message', /No products found for “zzqqxx”/.test(await p.textContent('.search-panel__empty')));

  // Enter without a highlighted result → results page
  await p.fill('.search-dialog__input', 'sneaker');
  await p.waitForTimeout(500);
  await Promise.all([p.waitForURL(/[?&]s=sneaker/, { timeout: 10000 }), p.press('.search-dialog__input', 'Enter')]);
  await p.waitForLoadState('networkidle');
  const page = await p.evaluate(() => ({
    title: document.querySelector('.woocommerce-products-header__title')?.textContent.trim(),
    titleVisible: getComputedStyle(document.querySelector('.woocommerce-products-header__title')).position !== 'absolute',
    count: document.querySelector('.collection-count')?.textContent.trim(),
    form: document.querySelector('.search-page__form input[name="s"]')?.value,
    cards: document.querySelectorAll('ul.products > li').length,
  }));
  check('results page: visible title, count, search form, product cards', /sneaker/i.test(page.title) && page.titleVisible && page.count === `${page.cards} products` && page.form === 'sneaker' && page.cards >= 2, JSON.stringify(page));
  if (w === 1440) await p.screenshot({ path: 'screens-local/search-results-1440.png', fullPage: true });

  await p.goto(`${SITE}/?s=zzqqxx&post_type=product`, { waitUntil: 'networkidle' });
  check('empty results page: message + browse button', (await p.$$('.search-page__empty .btn')).length === 1);
  await p.goto(`${SITE}/?s=hoodie`, { waitUntil: 'networkidle' });
  check('plain ?s= search redirects to the product search', /post_type=product/.test(p.url()) && (await p.$$('ul.products > li')).length >= 1);

  check('no console errors', errors.length === 0, errors.join(' | '));
  await p.close();
}

console.log(fails ? `\n${fails} check(s) failed` : '\nAll search checks passed');
await b.close();
process.exit(fails ? 1 : 0);
