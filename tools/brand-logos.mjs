#!/usr/bin/env node
/**
 * Brand logos: SVGs in reference/media/brands/src → transparent PNGs (WordPress has no SVG uploads) in
 * reference/media/brands/<slug>.png, trimmed to the logo and HEIGHT px tall (max MAX_W wide).
 *   node tools/brand-logos.mjs [--color]    default: every logo in the brand navy (one consistent grid); --color keeps the originals
 * Sources: Wikimedia/Wikipedia (Hoka, Brooks, ASICS, UGG, On, Adidas, New Balance) and Simple Icons (Nike, Puma,
 * Jordan, Reebok, The North Face). tools/home-v2.php imports the PNGs as the brands' thumbnails.
 */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const DIR = path.resolve(import.meta.dirname, '..', 'reference', 'media', 'brands');
// Tiles show logos ≤ 44px tall / 112px wide: 132px = 3× retina, so no larger file is ever needed.
const HEIGHT = 132, MAX_W = 340;
const mono = !process.argv.includes('--color');
const NAVY = '#1C253B';

const browser = await chromium.launch();
const page = await browser.newPage({ deviceScaleFactor: 1 });
for (const file of fs.readdirSync(path.join(DIR, 'src')).filter((f) => f.endsWith('.svg'))) {
  const slug = path.basename(file, '.svg');
  const svg = fs.readFileSync(path.join(DIR, 'src', file), 'utf8');
  const png = await page.evaluate(async ({ svg, HEIGHT, MAX_W, mono, NAVY }) => {
    const img = new Image();
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
    await img.decode();
    // Draw big, then trim transparent/white margins.
    const big = document.createElement('canvas');
    const scale = 1200 / Math.max(img.naturalWidth || 300, img.naturalHeight || 150);
    big.width = Math.round((img.naturalWidth || 300) * scale);
    big.height = Math.round((img.naturalHeight || 150) * scale);
    const g = big.getContext('2d');
    g.drawImage(img, 0, 0, big.width, big.height);
    const d = g.getImageData(0, 0, big.width, big.height);
    // White/near-white pixels → transparent (some logos ship a white background).
    for (let i = 0; i < d.data.length; i += 4) {
      if (d.data[i] > 245 && d.data[i + 1] > 245 && d.data[i + 2] > 245) d.data[i + 3] = 0;
    }
    g.putImageData(d, 0, 0);
    let x0 = big.width, y0 = big.height, x1 = 0, y1 = 0;
    for (let y = 0; y < big.height; y++) for (let x = 0; x < big.width; x++) {
      if (d.data[(y * big.width + x) * 4 + 3] > 8) { x0 = Math.min(x0, x); x1 = Math.max(x1, x); y0 = Math.min(y0, y); y1 = Math.max(y1, y); }
    }
    const w = x1 - x0 + 1, h = y1 - y0 + 1;
    const s = Math.min(HEIGHT / h, MAX_W / w);
    const out = document.createElement('canvas');
    out.width = Math.round(w * s); out.height = Math.round(h * s);
    const o = out.getContext('2d');
    o.drawImage(big, x0, y0, w, h, 0, 0, out.width, out.height);
    if (mono) { o.globalCompositeOperation = 'source-in'; o.fillStyle = NAVY; o.fillRect(0, 0, out.width, out.height); }
    return out.toDataURL('image/png').split(',')[1];
  }, { svg, HEIGHT, MAX_W, mono, NAVY });
  fs.writeFileSync(path.join(DIR, `${slug}.png`), Buffer.from(png, 'base64'));
  console.log(`${slug}.png`);
}
await browser.close();
