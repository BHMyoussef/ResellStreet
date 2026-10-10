// Geometry of key elements, live vs local, at 1440 and 390.
//   node tools/measure.mjs [profile]     profiles: global (default), collection
// Each profile maps a label to [live selector, local selector]. Add a profile per section.
import { chromium } from 'playwright';

const PROFILES = {
  global: {
    live: 'https://novarende.com/',
    local: 'http://resellstreet.local/',
    els: {
      header: ['.site-header', '.site-header'],
      logo: ['.site-header__logo img', '.site-header__logo img'],
      nav: ['.site-nav__link', '.site-nav__list a'],
      announce: ['.announcement-bar', '.announcement'],
      footerTitle: ['.footer__title', '.footer__title'],
      footerLink: ['.site-footer li a', '.footer__menu a'],
      footerLogo: ['.site-footer img', '.footer__logo img'],
      payment: ['.payment-icons', '.payment-icons'],
    },
  },
  collection: {
    live: 'https://novarende.com/collections/chaussures',
    local: 'http://resellstreet.local/product-category/clothing/',
    els: {
      banner: ['.type-banner__text', '.collection-banner'],
      bannerHeading: ['.type-banner__text .h3', '.collection-banner__heading'],
      count: ['.collection-filter', '.collection-toolbar'],
      card1: ['.grid-product', 'ul.products > li'],
      image: ['.grid-product__image-mask', '.product-card__media'],
      tag: ['.grid-product__tag', '.product-card__tag'],
      meta: ['.grid-product__meta', '.product-card__meta'],
      title: ['.grid-product__title', '.product-card__title'],
      price: ['.grid-product__price', '.product-card__price'],
      footer: ['.site-footer', '.site-footer'],
    },
    // second card in the 2nd row tells us the row gap
    extra: [['.grid-product', 'ul.products > li', 3]],
  },
};

const name = process.argv[2] || 'global';
const P = PROFILES[name];
if (!P) throw new Error(`Unknown profile "${name}". Known: ${Object.keys(PROFILES).join(', ')}`);

const b = await chromium.launch();
for (const w of [1440, 390]) {
  for (const [site, idx] of [['live', 0], ['local', 1]]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 } });
    await p.goto(P[site], { waitUntil: 'networkidle' });
    const out = await p.evaluate(
      ({ els, idx, extra }) => {
        const fmt = (e) => {
          if (!e) return null;
          const r = e.getBoundingClientRect(), c = getComputedStyle(e);
          return `${Math.round(r.x)},${Math.round(r.y + scrollY)} ${Math.round(r.width)}x${Math.round(r.height)} ${c.fontSize}`;
        };
        const first = (sel) => [...document.querySelectorAll(sel)].find((e) => e.getBoundingClientRect().width > 0);
        const res = Object.fromEntries(Object.entries(els).map(([k, sels]) => [k, fmt(first(sels[idx]))]));
        (extra || []).forEach((x) => (res[`nth${x[2]}`] = fmt(document.querySelectorAll(x[idx])[x[2]])));
        return res;
      },
      { els: P.els, idx, extra: P.extra }
    );
    console.log(`\n${w} ${site}`);
    for (const [k, v] of Object.entries(out)) console.log('  ' + k.padEnd(14), v);
    await p.close();
  }
}
await b.close();
