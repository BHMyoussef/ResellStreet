// Behavior checks on resellstreet.local (drawers, announcement, footer, sticky header, console errors).
import { launch } from './lib/browser.mjs';
const b = await launch();
const p = await b.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const errors = []; p.on('pageerror', e => errors.push(e.message)); p.on('console', m => m.type() === 'error' && errors.push(m.text()));
await p.goto('http://resellstreet.local/', { waitUntil: 'networkidle' });
const st = () => p.evaluate(() => { const d = document.querySelector('#drawer-menu'); return { open: d.open, cls: d.className, htmlLock: document.documentElement.classList.contains('has-drawer'), expanded: document.querySelector('[data-drawer-open=menu]').getAttribute('aria-expanded'), focus: document.activeElement.className || document.activeElement.tagName }; });
await p.click('[data-drawer-open=menu]'); await p.waitForTimeout(600); console.log('open      ', JSON.stringify(await st()));
await p.keyboard.press('Escape'); await p.waitForTimeout(700); console.log('after Esc ', JSON.stringify(await st()));
await p.click('[data-drawer-open=menu]'); await p.waitForTimeout(600);
await p.mouse.click(15, 400); await p.waitForTimeout(700); console.log('backdrop  ', JSON.stringify(await st()));
await p.click('[data-drawer-open=menu]'); await p.waitForTimeout(600);
await p.click('[data-drawer-close]'); await p.waitForTimeout(700); console.log('close btn ', JSON.stringify(await st()));
// Tab trap
await p.click('[data-drawer-open=menu]'); await p.waitForTimeout(600);
for (let i = 0; i < 12; i++) await p.keyboard.press('Tab');
console.log('focus inside drawer after 12 tabs:', await p.evaluate(() => !!document.activeElement.closest('#drawer-menu')));
await p.keyboard.press('Escape'); await p.waitForTimeout(700);
// Announcement
const ann = () => p.evaluate(() => document.querySelector('.announcement__msg.is-active')?.textContent.trim());
const a1 = await ann(); await p.waitForTimeout(5600); console.log('announcement rotates:', a1, '->', await ann());
// Footer collapsible
await p.evaluate(() => document.querySelector('.footer__block--contact').scrollIntoView());
const vis = () => p.evaluate(() => getComputedStyle(document.querySelector('#footer-panel-contact')).display);
console.log('contact panel before:', await vis()); await p.click('.footer__block--contact [data-collapsible-toggle]'); console.log('after click:', await vis(), await p.getAttribute('.footer__block--contact [data-collapsible-toggle]', 'aria-expanded'));
// Sticky
await p.evaluate(() => window.scrollTo(0, 600)); await p.waitForTimeout(200);
console.log('header top after scroll:', await p.evaluate(() => document.querySelector('.site-header').getBoundingClientRect().top));
// Desktop: footer panels open, toggle not focusable
await p.setViewportSize({ width: 1440, height: 900 }); await p.waitForTimeout(300);
console.log('desktop panel:', await vis(), 'tabindex', await p.getAttribute('.footer__block--contact [data-collapsible-toggle]', 'tabindex'));
console.log('errors:', errors.length ? errors : 'none');
await b.close();
