// Homepage (v2, reference/homepage-v2) behavior checks on resellstreet.local.
// Needs `npm run fixtures` (test video reviews). Temporarily adds a 2nd trending tab (restored at the end).
import { launch, wcJqueryScripts } from './lib/browser.mjs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const WP = path.join(path.dirname(fileURLToPath(import.meta.url)), 'wp');
const wp = (...args) => execFileSync(WP, args, { encoding: 'utf8' }).trim();
const HOME = 'http://resellstreet.local/';
const SECTIONS = ['home-hero', 'home-new', 'home-split', 'home-brands', 'home-banner', 'home-trending', 'home-videos', 'home-trust'];
let fails = 0;
const check = (label, ok, info = '') => {
  if (!ok) fails++;
  console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`);
};

const b = await launch();

/* ---------- Desktop ---------- */
{
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  const media = [];
  p.on('request', (r) => /\.(mp4|webm|mov)(\?|$)/.test(r.url()) && media.push(r.url()));
  await p.goto(HOME, { waitUntil: 'networkidle' });

  check('no WooCommerce jQuery scripts', !(await wcJqueryScripts(p)).length, (await wcJqueryScripts(p)).join());
  const order = await p.$$eval('section[class*="home-"]', (s) => s.map((x) => [...x.classList].find((c) => /^home-(hero|new|split|brands|banner|trending|videos|trust)$/.test(c))));
  check('8 sections in design order', JSON.stringify(order) === JSON.stringify(SECTIONS), order.join(' → '));

  const hero = await p.$eval('.home-hero .home-media__img', (img) => ({ fp: img.getAttribute('fetchpriority'), loading: img.loading, srcset: !!img.srcset }));
  check('hero image: eager + fetchpriority=high + srcset', hero.fp === 'high' && hero.loading === 'eager' && hero.srcset, JSON.stringify(hero));
  const preloads = await p.$$eval('link[rel="preload"][as="image"]', (l) => l.map((x) => x.media));
  check('hero preloaded for phones and desktop', preloads.includes('(max-width: 768px)') && preloads.includes('(min-width: 769px)'), preloads.join(' | '));
  const lazy = await p.$$eval('main img, .home-split img, .home-banner img, .home-products img, .brand-tiles img, .video-card img', (imgs) =>
    imgs.filter((i) => !i.closest('.home-hero')).every((i) => i.loading === 'lazy'));
  check('every image below the hero is lazy', lazy);

  check('new arrivals: 8 cards with New badge', (await p.$$eval('.home-new li.product', (l) => l.filter((x) => x.querySelector('.product-card__tag--new, .product-card__tag--sold-out')).length)) === 8);
  check('cards show the brand line', (await p.$$eval('.home-new .product-card__brand', (l) => l.length)) === 8);
  const brands = await p.$$eval('.home-brands .brand-tiles__tile', (a) => a.map((x) => x.getAttribute('href')));
  check('12 brand tiles link to brand pages', brands.length === 12 && brands.every((h) => /\/brand\//.test(h)), brands.length);
  check('brand tiles have logos with alt text', (await p.$$eval('.home-brands .brand-tiles__logo', (i) => i.filter((x) => x.alt).length)) === 12);
  check('trending: one tab → no tab bar, cards shown', !(await p.$('.home-trending [role="tablist"]')) && (await p.$$('.home-trending li.product')).length > 0);
  check('trending cards: no sale badge', !(await p.$('.home-trending .product-card__tag:not(.product-card__tag--sold-out)')));

  // Video reviews: nothing loads until a tap.
  await p.locator('.home-videos').scrollIntoViewIfNeeded();
  await p.waitForTimeout(600);
  check('video reviews: posters only, no <video>, no video request', (await p.$$('.home-videos video')).length === 0 && media.length === 0, media.join());
  check('rating summary from real reviews', /^\d\.\d$/.test(await p.$eval('.rating-summary__value', (e) => e.textContent)));
  const prevAtStart = await p.$eval('[data-carousel-prev]', (x) => x.disabled);
  await p.click('[data-carousel-next]');
  await p.waitForTimeout(700);
  check('carousel arrows scroll (prev disabled at start)', prevAtStart && (await p.$eval('[data-carousel-track]', (t) => t.scrollLeft)) > 0);
  await p.click('[data-carousel-prev]');
  await p.waitForTimeout(700);
  await p.locator('.video-card__play').first().click();
  await p.waitForTimeout(1200);
  const v = await p.$eval('.video-card video', (x) => ({ paused: x.paused, controls: x.controls, inline: x.playsInline }));
  check('tap plays the video inline with controls (file requested now)', !v.paused && v.controls && v.inline && media.length === 1, JSON.stringify(v));
  check('no console errors (desktop)', errors.length === 0, errors.join(' | '));
  await p.close();
}

/* ---------- Trending tabs (2nd tab added for the test) ---------- */
wp('theme', 'mod', 'set', 'home_trending_2_label', 'Shoes');
wp('theme', 'mod', 'set', 'home_trending_2_cat', wp('term', 'list', 'product_cat', '--slug=shoes', '--field=term_id'));
try {
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  const rest = [];
  p.on('request', (r) => r.url().includes('/ci/v1/trending/') && rest.push(r.url()));
  await p.goto(HOME, { waitUntil: 'networkidle' });
  check('2 tabs → tab bar', (await p.$$('.home-trending [role="tab"]')).length === 2);
  await p.click('.home-trending [role="tab"]:nth-child(2)');
  await p.waitForFunction(() => !document.querySelector('[data-trending-panel]').hasAttribute('aria-busy'));
  check('tab click loads that category without reload (REST)', rest.length === 1 && (await p.$$('.home-trending li.product')).length > 0);
  check('selected tab state', (await p.$eval('.home-trending [role="tab"]:nth-child(2)', (t) => t.getAttribute('aria-selected'))) === 'true');
  await p.click('.home-trending [role="tab"]:nth-child(1)');
  await p.waitForTimeout(200);
  check('switching back uses the cache (no 2nd request)', rest.length === 1);
  await p.focus('.home-trending [role="tab"]:nth-child(1)');
  await p.keyboard.press('ArrowRight');
  check('arrow key moves to the next tab', (await p.evaluate(() => document.activeElement.textContent)) === 'Shoes');
  check('unknown tab id → 404', (await p.evaluate(async () => (await fetch('/wp-json/ci/v1/trending/999999')).status)) === 404);
  check('no console errors (tabs)', errors.length === 0, errors.join(' | '));
  await p.close();
} finally {
  wp('theme', 'mod', 'remove', 'home_trending_2_label', 'home_trending_2_cat');
}

/* ---------- Mobile ---------- */
{
  const p = await b.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 3 });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  const media = [];
  p.on('request', (r) => /\.(mp4|webm|mov)(\?|$)/.test(r.url()) && media.push(r.url()));
  await p.goto(HOME, { waitUntil: 'networkidle' });
  const heroSrc = await p.$eval('.home-hero .home-media__img', (i) => i.currentSrc);
  const heroW = Number((heroSrc.match(/-(\d+)x\d+\.\w+$/) || [])[1] || 99999);
  check('hero on a 3× phone uses a ≤1024px file', heroW <= 1024, heroSrc.split('/').pop());
  check('no horizontal page scroll', !(await p.evaluate(() => document.documentElement.scrollWidth > innerWidth)));
  const swipe = await p.$eval('.home-new .home-products', (u) => getComputedStyle(u).overflowX === 'auto' && u.scrollWidth > u.clientWidth);
  check('new arrivals: swipe row on phones', swipe);
  check('carousel arrows hidden on phones', await p.$eval('.carousel-arrows', (a) => getComputedStyle(a).display === 'none'));
  for (let y = 0; y < 9000; y += 700) {
    await p.evaluate((top) => window.scrollTo(0, top), y);
    await p.waitForTimeout(80);
  }
  check('no autoplay / no video request on mobile while scrolling', media.length === 0 && (await p.$$('video')).length === 0);
  check('no console errors (mobile)', errors.length === 0, errors.join(' | '));
  await p.close();
}

/* ---------- Brands page ---------- */
{
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto(HOME + 'brands/', { waitUntil: 'networkidle' });
  check('"All brands" page lists every brand', (await p.$$('.brand-tiles__tile')).length === Number(wp('term', 'list', 'product_brand', '--format=count')));
  await p.close();
}

console.log(fails ? `\n${fails} check(s) failed` : '\nAll home checks passed');
await b.close();
process.exitCode = fails ? 1 : 0;
