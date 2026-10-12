#!/usr/bin/env node
/**
 * Product page v2: design export (reference/product-v2) vs resellstreet.local, one area at a time, side by side.
 *   node tools/product-compare.mjs <area> [width…]          e.g. node tools/product-compare.mjs main 390 1440
 * Areas (design data-module → local selector): main, accordions, reviews, video-reviews, related, recently-viewed,
 * sticky (390 only, scrolled), drawer (390, after add to cart).
 * PRODUCT_URL env = local product (default: the "Test Runner - Mint Blue" fixture: UK 3–13, sold-out sizes, sale).
 * Output: reference/screens/product-v2/<area>-<width>.png (left = design, right = local) + measurements on stdout.
 */
import path from 'node:path';
import fs from 'node:fs';
import { chromium } from 'playwright';

const ROOT = path.resolve(import.meta.dirname, '..');
const DESIGN = 'file://' + path.join(ROOT, 'reference/product-v2/Resell Street Product Page - Mobile + Desktop.html');
const PRODUCT = process.env.PRODUCT_URL || 'http://resellstreet.local/product/test-runner-mint-blue/';
const OUT = path.join(ROOT, 'reference/screens/product-v2');
// area: [design module, local selector]
const AREAS = {
  main: ['product-main', '.product-main'],
  gallery: ['gallery', '.woocommerce-product-gallery'],
  buybox: ['buy-box', '.summary'],
  accordions: ['product-accordions', '.product-accordions'],
  reviews: ['product-reviews', '#reviews'],
  'video-reviews': ['video-reviews', '.product-videos'],
  related: ['related-products', '.product-related'],
  'recently-viewed': ['recently-viewed', '.product-recent'],
  sticky: ['sticky-atc', '[data-sticky-atc]'],
  drawer: ['cart-drawer', 'dialog[data-drawer="cart"]'],
};
// Design canvas frames: 0 mobile page, 1 mobile sticky state, 2 mobile drawer state, 3 desktop.
const FRAME = (area, width) => (width >= 769 ? 3 : area === 'sticky' ? 1 : area === 'drawer' ? 2 : 0);

const [area, ...widths] = process.argv.slice(2);
if (!AREAS[area]) {
  console.error(`Usage: node tools/product-compare.mjs <${Object.keys(AREAS).join('|')}> [widths]`);
  process.exit(1);
}
fs.mkdirSync(OUT, { recursive: true });

const describe = (root) => {
  const r = root.getBoundingClientRect();
  const texts = [...root.querySelectorAll('h1,h2,h3,p,a,button,span,label,summary,s,strong')]
    .filter((e) => e.children.length === 0 && e.textContent.trim() && e.getBoundingClientRect().width)
    .slice(0, 14)
    .map((e) => {
      const c = getComputedStyle(e);
      const b = e.getBoundingClientRect();
      return `${e.textContent.trim().replace(/\s+/g, ' ').slice(0, 22).padEnd(22)} ${c.fontSize}/${c.lineHeight} w${c.fontWeight} ls${c.letterSpacing} x${Math.round(b.left - r.left)} y${Math.round(b.top - r.top)} h${Math.round(b.height)} w${Math.round(b.width)}`;
    });
  return { h: Math.round(r.height), w: Math.round(r.width), texts };
};

const browser = await chromium.launch();
for (const width of (widths.length ? widths : ['390', '1440']).map(Number)) {
  if ((area === 'sticky' || area === 'drawer') && width > 768) continue;
  const shots = [];

  // Design: the tall canvas viewport makes every frame render its lazy sections.
  {
    const page = await browser.newPage({ viewport: { width: 2460, height: 8300 } });
    await page.goto(DESIGN, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    const frame = await (await page.$$('iframe'))[FRAME(area, width)].contentFrame();
    let el = await frame.$(`[data-module="${AREAS[area][0]}"]`);
    if (area === 'drawer' || area === 'sticky') el = el || (await frame.$('body'));
    if (!el) {
      console.log(`design @${width}: module ${AREAS[area][0]} not found`);
      shots.push(null);
    } else {
      const info = await el.evaluate(describe);
      console.log(`\ndesign @${width}: ${info.w}×${info.h}`);
      info.texts.forEach((t) => console.log('  ' + t));
      shots.push(await el.screenshot());
    }
    await page.close();
  }

  // Local
  {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    if (area === 'recently-viewed') {
      // Browse 6 products first (from the homepage's new arrivals) so the section has history to show.
      await page.goto(new URL('/', PRODUCT).href, { waitUntil: 'domcontentloaded' });
      const links = await page.$$eval('.home-new a.woocommerce-loop-product__link', (a) => [...new Set(a.map((x) => x.href))].slice(0, 6));
      for (const href of links) await page.goto(href, { waitUntil: 'domcontentloaded' });
    }
    await page.goto(PRODUCT, { waitUntil: 'networkidle' });
    if (area === 'recently-viewed') {
      await page.locator('.product-recent').scrollIntoViewIfNeeded();
      await page.waitForSelector('.product-recent .products', { timeout: 10000 });
    }
    if (area === 'drawer') {
      await page.locator('.variant-buttons__input:not(:disabled) + label').nth(5).click();
      await page.click('.single_add_to_cart_button');
      await page.waitForFunction(() => document.querySelector('dialog[data-drawer="cart"]')?.open, null, { timeout: 10000 });
      await page.waitForTimeout(600);
    }
    if (area === 'sticky') {
      await page.evaluate(() => window.scrollTo(0, 1600));
      await page.waitForTimeout(600);
    }
    const el = await page.$(AREAS[area][1]);
    if (!el) {
      console.log(`local @${width}: ${AREAS[area][1]} not found`);
      shots.push(null);
    } else {
      if (area !== 'sticky' && area !== 'drawer') {
        await el.scrollIntoViewIfNeeded();
        await page.waitForTimeout(700);
      }
      const info = await el.evaluate(describe);
      console.log(`\nlocal @${width}: ${info.w}×${info.h}`);
      info.texts.forEach((t) => console.log('  ' + t));
      shots.push(await el.screenshot());
    }
    await page.close();
  }

  const page = await browser.newPage({ viewport: { width: width * 2 + 30, height: 400 } });
  const img = (b) => (b ? `<img src="data:image/png;base64,${b.toString('base64')}" style="display:block;width:${width}px">` : `<div style="width:${width}px">missing</div>`);
  await page.setContent(`<body style="margin:0;display:flex;gap:30px;align-items:flex-start;background:#e33">${img(shots[0])}${img(shots[1])}</body>`);
  const file = path.join(OUT, `${area}-${width}.png`);
  await page.screenshot({ path: file, fullPage: true });
  await page.close();
  console.log(`→ ${path.relative(ROOT, file)}`);
}
await browser.close();
