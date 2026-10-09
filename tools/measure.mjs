// Geometry of key elements, live vs local (1440 + 390). Extend the selector maps per section.
import { chromium } from 'playwright';
const b = await chromium.launch();
const sites = {
  live: { url: 'https://novarende.com/', s: { header: '.site-header', logo: '.site-header__logo img', nav: '.site-nav__link', icon: '.site-nav__link--icon', announce: '.announcement-bar', footerTitle: '.footer__title', footerLink: '.site-footer .footer__menu a, .site-footer li a', footerLogo: '.footer__logo img, .site-footer img', payment: '.payment-icons' } },
  local: { url: 'http://resellstreet.local/', s: { header: '.site-header', logo: '.site-header__logo img', nav: '.site-nav__list a', icon: '.header-icon', announce: '.announcement', footerTitle: '.footer__title', footerLink: '.footer__menu a', footerLogo: '.footer__logo img', payment: '.payment-icons' } },
};
for (const w of [1440, 390]) for (const [name, cfg] of Object.entries(sites)) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.goto(cfg.url, { waitUntil: 'networkidle' });
  const r = await p.evaluate((s) => Object.fromEntries(Object.entries(s).map(([k, sel]) => {
    const e = [...document.querySelectorAll(sel)].find((e) => e.getBoundingClientRect().width > 0);
    if (!e) return [k, null];
    const b = e.getBoundingClientRect(), c = getComputedStyle(e);
    return [k, `${Math.round(b.x)},${Math.round(b.y)} ${Math.round(b.width)}x${Math.round(b.height)} fs:${c.fontSize}`];
  })), cfg.s);
  console.log(w, name.padEnd(5), JSON.stringify(r));
  await p.close();
}
await b.close();
