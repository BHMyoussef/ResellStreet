#!/usr/bin/env node
/**
 * Colour swatches from product photos: for every product without a colour, take the colourway from the title
 * ("New Balance 530 – White/Indigo"), measure the shoe's main colour(s) in its photo and store them as the global
 * Colour attribute (pa_color term + hex). Two-tone names ("White/Indigo", "Black White") get two colours.
 *
 *   node tools/product-colours.mjs [--dry]      --dry: measure and write the contact sheet only, no import
 *
 * Measuring: background = median of the photo's border; shoe pixels = central area pixels far enough from it;
 * k-means (k=5) on those. The colourway name guides the choice (photos with a hand or a box would otherwise win):
 * each colour word in the name ("navy", "ember", "glacier"…) picks the photo cluster closest to its reference tone
 * (the real shade); with no close cluster the reference tone itself is used. Names without colour words: largest
 * cluster that isn't skin-coloured. Up to 2 colours (two-tone names).
 * Check sheet: reference/screens/colour-swatches.png (photo + swatch + name). Swatches stay editable per colour
 * term (Products → Attributes → Color → Configure terms).
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { chromium } from 'playwright';

const ROOT = path.resolve(import.meta.dirname, '..');
const WP = path.join(ROOT, 'tools/wp');
const dry = process.argv.includes('--dry');

const list = JSON.parse(execFileSync(WP, ['eval-file', 'tools/product-colours.php', 'export'], { cwd: ROOT, encoding: 'utf8', maxBuffer: 1 << 26 }));
console.log(`${list.length} products without a colour`);
if (!list.length) process.exit(0);

// Colour words → reference tone (name hints; the measured shade wins when the photo has one close by).
const REF = {
  black: '#1c1c1e', carbon: '#2b2b2d', stealth: '#202022', eclipse: '#1a1a1c', phantom: '#2a2a2e', shadow: '#38393d', oreo: '#1c1c1e',
  white: '#f3f3f1', ivory: '#f1ece0', pearl: '#ecebe6', cream: '#ede3cf', creme: '#ede3cf', blanc: '#f3f3f1', 'sea salt': '#e9e6df',
  grey: '#9b9ca0', gray: '#9b9ca0', silver: '#c3c5c8', steel: '#7f8a96', ash: '#8c8c8c', quartz: '#c9c4c0', graphite: '#4a4b4f', magnet: '#5b5d63', 'grand shark': '#6b6e74',
  navy: '#1f2a44', midnight: '#1d2340', indigo: '#2e3a7a', blue: '#2f62b6', sky: '#8cc4ee', ice: '#d4e9f4', glacier: '#cfe2ec', frost: '#dde7ee', aqua: '#55c8cf', storm: '#5a6878', ocean: '#3d7fa6', university: '#7fb2e5',
  green: '#3a8f5a', mint: '#a8e0cc', moss: '#6b8a4a', jade: '#2f8a6a', olive: '#6b6b3a', forest: '#2f5a3a', teal: '#1f8a8a', lime: '#b9e23a', volt: '#d4f03a', neon: '#c8f03c',
  red: '#c62828', crimson: '#b0203a', burgundy: '#6e1f33', berry: '#9a2a5a', ember: '#e0602a', blaze: '#f0703a', orange: '#f07a2a', sunset: '#f08a4a', copper: '#b8734a', spice: '#b8734a', latte: '#b89a7a',
  yellow: '#f2d33b', gold: '#c9a23f', solar: '#f5c23a', sand: '#d9c7a3', desert: '#d2bf9e', beige: '#d6c6a8', khaki: '#a99a6b', mesa: '#c47a52', brown: '#7a5236', mojave: '#c9a77a',
  pink: '#e8a0b4', blush: '#efc1c8', peach: '#f3b99a', violet: '#8a63c8', purple: '#7a4fb0', lilac: '#c8a8e6', twilight: '#6a5a8a', cosmic: '#6a4fb0',
};
const WORDS = Object.keys(REF).sort((a, b) => b.length - a.length);
const colourWords = (name) => {
  let rest = ` ${name.toLowerCase().replace(/[/,]/g, ' ')} `;
  const found = [];
  for (const w of WORDS) {
    const i = rest.indexOf(` ${w} `);
    if (i >= 0) { found.push([i, w]); rest = rest.slice(0, i) + ' '.repeat(w.length + 1) + rest.slice(i + w.length + 1); }
  }
  const words = found.sort((a, b) => a[0] - b[0]).map(([, w]) => w);
  return name.toLowerCase().includes('oreo') ? ['black', 'white'] : words;
};

const browser = await chromium.launch();
const page = await browser.newPage();
// Same origin as the photos, so their pixels can be read from a canvas.
await page.goto(new URL('/robots.txt', list[0].image).href);
const results = await page.evaluate(async ({ list }) => {
  const hex = ([r, g, b]) => '#' + [r, g, b].map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
  const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]);
  const median = (arr, i) => arr.map((p) => p[i]).sort((a, b) => a - b)[arr.length >> 1];
  const out = [];
  for (const item of list) {
    try {
      const img = new Image();
      img.src = item.image;
      await img.decode();
      const w = 160, h = Math.round((img.naturalHeight / img.naturalWidth) * 160);
      const c = document.createElement('canvas'); c.width = w; c.height = h;
      const g = c.getContext('2d'); g.drawImage(img, 0, 0, w, h);
      const d = g.getImageData(0, 0, w, h).data;
      const px = (x, y) => { const i = (y * w + x) * 4; return [d[i], d[i + 1], d[i + 2]]; };
      const border = [];
      for (let x = 0; x < w; x++) border.push(px(x, 0), px(x, h - 1));
      for (let y = 0; y < h; y++) border.push(px(0, y), px(w - 1, y));
      const bg = [median(border, 0), median(border, 1), median(border, 2)];
      let shoe = [];
      for (let y = Math.round(h * 0.12); y < h * 0.88; y++) for (let x = Math.round(w * 0.08); x < w * 0.92; x++) {
        const p = px(x, y);
        if (dist(p, bg) > 28) shoe.push(p);
      }
      // A white shoe on a light background barely differs from it: fall back to the lighter central pixels.
      if (shoe.length < w * h * 0.04) {
        shoe = [];
        for (let y = Math.round(h * 0.3); y < h * 0.7; y++) for (let x = Math.round(w * 0.2); x < w * 0.8; x++) shoe.push(px(x, y));
      }
      // k-means, k = 5
      const sample = shoe.filter((_, i) => i % Math.max(1, Math.floor(shoe.length / 3000)) === 0);
      let centers = [0, 0.25, 0.5, 0.75, 0.99].map((f) => sample[Math.floor(f * (sample.length - 1))].slice());
      let groups = [];
      for (let it = 0; it < 12; it++) {
        groups = centers.map(() => []);
        for (const p of sample) {
          let best = 0;
          for (let k = 1; k < centers.length; k++) if (dist(p, centers[k]) < dist(p, centers[best])) best = k;
          groups[best].push(p);
        }
        centers = groups.map((gr, k) => (gr.length ? [0, 1, 2].map((i) => gr.reduce((s, p) => s + p[i], 0) / gr.length) : centers[k]));
      }
      const ranked = centers.map((cc, k) => ({ c: cc.map(Math.round), share: groups[k].length / sample.length })).filter((x) => x.share > 0.03).sort((a, b) => b.share - a.share);
      out.push({ ...item, clusters: ranked, bg: hex(bg) });
    } catch (e) {
      out.push({ ...item, error: String(e) });
    }
  }
  return out;
}, { list });
await page.close();

const rgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const toHex = (c) => '#' + c.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]);
const isSkin = ([r, g, b]) => r > 95 && g > 40 && b > 20 && r > g && g > b && r - b > 30 && r - g > 12 && r - b < 140;
for (const r of results) {
  if (r.error) { console.warn(`! ${r.title}: ${r.error}`); continue; }
  const words = colourWords(r.colour);
  const picked = [];
  for (const w of words.slice(0, 2)) {
    const ref = rgb(REF[w]);
    // A hand in the photo is skin-coloured: only tan/beige-type words may pick such a cluster.
    const tanWord = /brown|beige|sand|desert|khaki|mesa|mojave|latte|peach|blush|copper|cream|creme|spice/.test(w);
    const near = r.clusters.filter((c) => !picked.includes(c) && (tanWord || !isSkin(c.c))).map((c) => ({ c, d: dist(c.c, ref) })).sort((a, b) => a.d - b.d)[0];
    picked.push(near && near.d < 95 ? near.c : { c: ref });
  }
  if (!picked.length) picked.push(r.clusters.find((c) => !isSkin(c.c)) || r.clusters[0]);
  const hexes = [...new Set(picked.map((p) => toHex(p.c)))];
  r.swatch = hexes.slice(0, 2).join(',');
  r.source = words.length ? `name: ${words.join('+')}` : 'photo';
}

// Contact sheet to check the result by eye.
const sheet = await browser.newPage({ viewport: { width: 1400, height: 400 } });
const cells = results.filter((r) => r.swatch).map((r) => {
  const [a, b] = r.swatch.split(',');
  const bgcss = b ? `linear-gradient(135deg, ${a} 50%, ${b} 50%)` : a;
  return `<div style="width:130px;font:10px sans-serif"><img src="${r.image}" style="width:130px;height:130px;object-fit:cover;display:block"><div style="display:flex;gap:6px;align-items:center;margin-top:4px"><span style="flex:none;width:22px;height:22px;border:1px solid #ccc;background:${bgcss}"></span>${r.colour}</div></div>`;
}).join('');
await sheet.setContent(`<body style="margin:10px;display:flex;flex-wrap:wrap;gap:10px;background:#fafafa">${cells}</body>`, { waitUntil: 'networkidle' });
fs.mkdirSync(path.join(ROOT, 'reference/screens'), { recursive: true });
await sheet.screenshot({ path: path.join(ROOT, 'reference/screens/colour-swatches.png'), fullPage: true });
await browser.close();
console.log('Check sheet: reference/screens/colour-swatches.png');

if (dry) {
  console.log(JSON.stringify(results.slice(0, 5).map(({ title, colour, swatch }) => ({ title, colour, swatch })), null, 1));
} else {
  const file = path.join(os.tmpdir(), `ci-colours-${process.pid}.json`);
  fs.writeFileSync(file, JSON.stringify(results));
  console.log(execFileSync(WP, ['eval-file', 'tools/product-colours.php', 'import', file], { cwd: ROOT, encoding: 'utf8' }).trim());
  fs.unlinkSync(file);
}
