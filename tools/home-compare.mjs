#!/usr/bin/env node
/**
 * Homepage v2: design export vs resellstreet.local, one section at a time, side by side.
 *   node tools/home-compare.mjs <section> [width…]      e.g. node tools/home-compare.mjs hero 390 1440
 * Sections: hero new-arrivals split brands banner trending video-reviews trust (design order).
 * Output: reference/screens/homepage-v2/<section>-<width>.png (left = design, right = local)
 * plus the measured box/typography of both sides on stdout.
 */
import path from 'node:path';
import fs from 'node:fs';
import { chromium } from 'playwright';

const ROOT = path.resolve(import.meta.dirname, '..');
const DESIGN = 'file://' + path.join(ROOT, 'reference/homepage-v2/Resell Street Homepage.html');
const LOCAL = process.env.HOME_URL || 'http://resellstreet.local/';
const OUT = path.join(ROOT, 'reference/screens/homepage-v2');
const SECTIONS = ['hero', 'new-arrivals', 'split', 'brands', 'banner', 'trending', 'video-reviews', 'trust'];
const LOCAL_SEL = {
  hero: '.home-hero', 'new-arrivals': '.home-new', split: '.home-split', brands: '.home-brands',
  banner: '.home-banner', trending: '.home-trending', 'video-reviews': '.home-videos', trust: '.home-trust',
};

const [name, ...widths] = process.argv.slice(2);
if (!SECTIONS.includes(name)) {
  console.error(`Usage: node tools/home-compare.mjs <${SECTIONS.join('|')}> [widths]`);
  process.exit(1);
}
fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch();
for (const width of (widths.length ? widths : ['390', '1440']).map(Number)) {
  const shots = [];
  for (const side of ['design', 'local']) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.goto(side === 'design' ? DESIGN : LOCAL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(side === 'design' ? 2500 : 500);
    const el = side === 'design'
      ? (await page.$$('body section'))[SECTIONS.indexOf(name)]
      : await page.$(LOCAL_SEL[name]);
    if (!el) { console.log(`${side} ${width}: section not found`); shots.push(null); await page.close(); continue; }
    await el.scrollIntoViewIfNeeded();
    // Lazy images below the fold: give them a moment after scrolling.
    await page.waitForTimeout(800);
    const info = await el.evaluate((s) => {
      const r = s.getBoundingClientRect();
      const cs = getComputedStyle(s);
      const texts = [...s.querySelectorAll('h1,h2,h3,p,a,button,span')].filter((e) => e.children.length === 0 && e.textContent.trim()).slice(0, 8).map((e) => {
        const c = getComputedStyle(e);
        const b = e.getBoundingClientRect();
        return `${e.textContent.trim().slice(0, 24).padEnd(24)} ${c.fontSize}/${c.lineHeight} w${c.fontWeight} ls${c.letterSpacing} x${Math.round(b.left)} y${Math.round(b.top - r.top)} h${Math.round(b.height)}`;
      });
      return { h: Math.round(r.height), pad: cs.padding, bg: cs.backgroundColor, texts };
    });
    console.log(`\n${side} @${width}: height ${info.h}, padding ${info.pad}, bg ${info.bg}`);
    info.texts.forEach((t) => console.log('  ' + t));
    shots.push(await el.screenshot());
    await page.close();
  }
  // Side by side on one canvas.
  const page = await browser.newPage({ viewport: { width: width * 2 + 30, height: 400 } });
  const img = (b) => (b ? `<img src="data:image/png;base64,${b.toString('base64')}" style="display:block;width:${width}px">` : `<div style="width:${width}px">missing</div>`);
  await page.setContent(`<body style="margin:0;display:flex;gap:30px;align-items:flex-start;background:#e33">${img(shots[0])}${img(shots[1])}</body>`);
  const file = path.join(OUT, `${name}-${width}.png`);
  await page.screenshot({ path: file, fullPage: true });
  await page.close();
  console.log(`→ ${path.relative(ROOT, file)}`);
}
await browser.close();
