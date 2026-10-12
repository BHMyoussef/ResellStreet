#!/usr/bin/env node
/**
 * Real contrast of white text over the homepage photos (hero, banner): hides the text, then finds the brightest
 * pixel behind each text line (99th percentile, so a stray highlight doesn't decide) and reports the ratio.
 *   node tools/overlay-contrast.mjs [overlay% …]     e.g. 45 55 65 (temporarily overrides --overlay; default: current)
 * Small text needs ≥ 4.5:1, large titles ≥ 3:1.
 */
import { chromium } from 'playwright';

const HOME = 'http://resellstreet.local/';
const levels = process.argv.slice(2).map(Number);
const lum = (r, g, b) => [r, g, b].map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }).reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0);
const ratio = (L) => 1.05 / (L + 0.05);

const browser = await chromium.launch();
for (const width of [390, 1440]) {
  const page = await browser.newPage({ viewport: { width, height: 900 } });
  await page.goto(HOME, { waitUntil: 'networkidle' });
  for (const level of levels.length ? levels : [null]) {
    for (const section of ['.home-hero', '.home-banner']) {
      const el = await page.$(section);
      await el.scrollIntoViewIfNeeded();
      await page.waitForTimeout(400);
      const boxes = await page.evaluate(({ section, level }) => {
        const s = document.querySelector(section);
        if (level !== null) { s.style.setProperty('--overlay', String(level / 100)); s.style.setProperty('--overlay-mobile', String(level / 100)); }
        const texts = [...s.querySelectorAll('.home-eyebrow, h2, p')];
        // The area the glyphs cover (a Range), not the block: a short line in a wide box.
        const out = texts.map((t) => {
          const range = document.createRange();
          range.selectNodeContents(t);
          const r = range.getBoundingClientRect();
          return { name: t.className || t.tagName, x: r.left, y: r.top, w: r.width, h: r.height, large: parseFloat(getComputedStyle(t).fontSize) >= 24 };
        });
        s.querySelectorAll('.home-eyebrow, h2, p, .home-buttons').forEach((t) => { t.style.visibility = 'hidden'; });
        return out;
      }, { section, level });
      await page.waitForTimeout(150);
      const shot = await page.screenshot();
      const results = await page.evaluate(async ({ png, boxes }) => {
        const img = new Image(); img.src = 'data:image/png;base64,' + png; await img.decode();
        const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const g = c.getContext('2d'); g.drawImage(img, 0, 0);
        return boxes.map((b) => {
          const d = g.getImageData(Math.max(0, b.x), Math.max(0, b.y), Math.max(1, b.w), Math.max(1, b.h)).data;
          const vals = [];
          for (let i = 0; i < d.length; i += 4) vals.push([d[i], d[i + 1], d[i + 2]]);
          return { ...b, px: vals };
        });
      }, { png: shot.toString('base64'), boxes });
      const line = results.map((r) => {
        const ls = r.px.map(([a, b2, c2]) => lum(a, b2, c2)).sort((a, b) => a - b);
        const cr = ratio(ls[Math.floor(ls.length * 0.99)] ?? 0);
        const need = r.large ? 3 : 4.5;
        return `${cr >= need ? '✓' : '✗'} ${r.name.split(' ')[0].replace('home-', '')} ${cr.toFixed(1)}:1`;
      });
      console.log(`${width}px ${section} overlay ${level ?? 'current'}%: ${line.join('  ')}`);
      await page.evaluate((section) => document.querySelectorAll(`${section} *`).forEach((t) => { t.style.visibility = ''; }), section);
    }
  }
  await page.close();
}
await browser.close();
