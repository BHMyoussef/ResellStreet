// Accessibility (axe-core, WCAG 2 A/AA) on resellstreet.local; colour contrast reported on its own.
//   node tools/a11y-check.mjs [url…]      default: homepage (390 + 1440px) and the Brands page
// Below-the-fold content is scrolled into view first so lazy images and posters are evaluated too.
import AxeBuilder from '@axe-core/playwright';
import { launch } from './lib/browser.mjs';

const SITE = 'http://resellstreet.local';
const urls = process.argv.slice(2).length ? process.argv.slice(2) : [`${SITE}/`, `${SITE}/brands/`];
let fails = 0;

const b = await launch();
for (const url of urls) {
  for (const width of [390, 1440]) {
    const p = await b.newPage({ viewport: { width, height: 900 } });
    await p.goto(url, { waitUntil: 'networkidle' });
    for (let y = 0; y < 12000; y += 700) {
      await p.evaluate((top) => window.scrollTo(0, top), y);
      await p.waitForTimeout(60);
    }
    await p.evaluate(() => window.scrollTo(0, 0));
    await p.waitForTimeout(300);
    // Third-party iframes (Stripe, payment badges) are not ours to fix.
    const res = await new AxeBuilder({ page: p }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).exclude('iframe').analyze();
    const contrast = res.violations.filter((v) => v.id === 'color-contrast');
    const other = res.violations.filter((v) => v.id !== 'color-contrast');
    const nodes = (v) => v.nodes.map((n) => `      ${n.target.join(' ')}  ${(n.any[0]?.message || '').split('. ')[0]}`).join('\n');
    console.log(`\n${url} @${width}px`);
    console.log(`  colour contrast: ${contrast.length ? '✗' : '✓'} ${contrast.reduce((s, v) => s + v.nodes.length, 0)} issue(s); ${res.passes.find((x) => x.id === 'color-contrast')?.nodes.length || 0} elements pass`);
    contrast.forEach((v) => console.log(nodes(v)));
    // Text over images/gradients: axe can't compute a ratio and asks for a manual check.
    const review = res.incomplete.find((v) => v.id === 'color-contrast');
    if (review) console.log(`  contrast to review by eye (text over images): ${review.nodes.length} — ${[...new Set(review.nodes.map((n) => n.target[0].split(' ')[0]))].slice(0, 6).join(', ')}`);
    console.log(`  other WCAG A/AA rules: ${other.length ? '✗' : '✓'} ${other.length} violation(s)`);
    other.forEach((v) => console.log(`    ${v.id} (${v.impact}): ${v.help}\n${nodes(v)}`));
    if (res.violations.length) fails++;
    await p.close();
  }
}
await b.close();
console.log(fails ? `\n${fails} page(s) with violations` : '\nNo axe violations');
process.exitCode = fails ? 1 : 0;
