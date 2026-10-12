// Review display checks on resellstreet.local (needs tools/fixtures.php test reviews + CusRev active).
// Submits one real review with a photo (deleted at the end) and temporarily forces 2 reviews per page via a mu-plugin.
import { launch } from './lib/browser.mjs';
import { execFileSync } from 'node:child_process';
import { writeFileSync, rmSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const THEME = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const WP = path.join(THEME, 'tools', 'wp');
const MU_DIR = path.resolve(THEME, '..', '..', 'mu-plugins');
const MU_FILE = path.join(MU_DIR, 'ci-test-reviews-per-page.php');
const wp = (...args) => execFileSync(WP, args, { encoding: 'utf8' }).trim();
const SITE = 'http://resellstreet.local';
const PRODUCT = `${SITE}/product/test-sneaker-mens-casual-leather-sneakers/`;
let fails = 0;
const check = (label, ok, info = '') => {
  if (!ok) fails++;
  console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`);
};
const b = await launch();

/* ---------- Display (desktop) ---------- */
{
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  await p.goto(PRODUCT, { waitUntil: 'networkidle' });

  const summary = await p.$eval('.product-rating', (a) => ({ avg: a.querySelector('.product-rating__avg').textContent, count: a.querySelector('.product-rating__count').textContent, href: a.getAttribute('href'), rating: a.querySelector('.ci-stars').style.getPropertyValue('--rating') }));
  check('stars + average + count under the title, linking to #reviews', summary.href === '#reviews' && summary.avg === '4.3' && summary.count === '4 reviews' && summary.rating === '4.25', JSON.stringify(summary));
  const assets = await p.evaluate(() => ['cr-frontend-css-css', 'cr-colcade-js', 'cr-frontend-js-js'].filter((id) => document.getElementById(id)));
  check('no CusRev CSS / masonry / upload script on load', assets.length === 0, assets.join());
  const schema = await p.evaluate(() => [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => s.textContent).join('').split('aggregateRating').length - 1);
  check('aggregateRating appears exactly once (WooCommerce)', schema === 1, String(schema));

  const head = await p.evaluate(() => ({
    average: document.querySelector('.reviews__average')?.textContent,
    bars: [...document.querySelectorAll('.reviews__bar-count [aria-hidden]')].map((n) => n.textContent).join(','),
  }));
  check('summary: 4.3 average, breakdown 2/1/1/0/0', head.average === '4.3' && head.bars === '2,1,1,0,0', JSON.stringify(head));
  const cards = await p.$$eval('.review-card', (els) => els.map((c) => `${c.querySelector('.review-card__name').textContent}${c.querySelector('.review-card__verified') ? '✓' : ''}${c.querySelector('.review-card__media') ? '📷' : ''}`));
  check('4 cards, newest first, verified badges + photos', cards.join(' ') === 'Liam P.✓📷 James R.✓📷 Oliver M.✓ Sam T.', cards.join(' '));
  check('customer photo strip has 3 photos', (await p.$$('.reviews__photo')).length === 3);

  await p.locator('.product-reviews').screenshot({ path: 'screens-local/reviews-1440.png' });

  await p.click('.reviews__photo >> nth=0');
  await p.waitForTimeout(300);
  const lightbox = await p.evaluate(() => {
    const d = document.querySelector('dialog[data-modal="review-photo"]');
    return { open: d.open, img: d.querySelector('img').src.includes('/uploads/'), review: d.querySelector('.review-card__name')?.textContent };
  });
  check('photo opens the lightbox with its review', lightbox.open && lightbox.img && lightbox.review === 'Liam P.', JSON.stringify(lightbox));
  await p.keyboard.press('Escape');
  await p.waitForTimeout(200);

  // Quick Shop (collection page) shows the same stars
  await p.goto(`${SITE}/?s=Test+Sneaker&post_type=product`, { waitUntil: 'networkidle' }); // contains the test product whatever the catalogue size
  const card = p.locator('ul.products > li', { hasText: /Test Sneaker [–-] Men/ });
  await card.hover();
  await card.locator('.product-card__quick').click();
  await p.waitForSelector('dialog[data-modal="quick-shop"] .product-rating', { timeout: 10000 });
  check('Quick Shop shows stars linking to the product reviews', /#reviews$/.test(await p.getAttribute('dialog[data-modal="quick-shop"] .product-rating', 'href')));
  check('no console errors (display)', errors.length === 0, errors.join(' | '));
  await p.close();
}

/* ---------- Write a review with a photo (mobile) ---------- */
{
  const p = await b.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  await p.goto(PRODUCT, { waitUntil: 'networkidle' });
  check('review form hidden until asked for', await p.$eval('#review-form', (f) => f.hidden));
  await p.click('[data-review-form-toggle]');
  await p.waitForFunction(() => !!document.getElementById('cr-frontend-js') || [...document.scripts].some((s) => s.src.includes('customer-reviews-woocommerce/js/frontend.js')), null, { timeout: 5000 });
  check('"Write a review" opens the form and loads CusRev upload script', !(await p.$eval('#review-form', (f) => f.hidden)));

  await p.setInputFiles('#cr_review_image', path.join(THEME, 'reference/media/avatar-4.jpg'));
  // CusRev uploads via admin-ajax and marks the preview .cr-upload-ok when the upload has finished.
  await p.waitForSelector('.cr-upload-images-preview .cr-upload-ok', { timeout: 20000 }).catch(() => {});
  const preview = await p.$$eval('.cr-upload-images-preview .cr-upload-ok', (n) => n.length);
  check('photo upload (CusRev) finished with a preview', preview === 1, `uploaded=${preview}`);
  await p.screenshot({ path: 'screens-local/review-form-390.png', fullPage: false });

  await p.click('p.stars a.star-5');
  await p.fill('#comment', 'CI test review — automated check, delete me.');
  await p.fill('#author', 'CI Test');
  await p.fill('#email', 'ci-test-review@example.com');
  await Promise.all([p.waitForLoadState('networkidle'), p.click('.review-form__submit')]);
  await p.waitForTimeout(500);
  check('submitted review → "awaiting approval" notice', (await p.textContent('.reviews')).includes('awaiting approval') || (await p.$$('.review-card')).length === 5);
  const saved = wp('eval', `$c = get_comments( array( 'type' => 'review', 'author_email' => 'ci-test-review@example.com', 'status' => 'all', 'number' => 1 ) ); echo $c ? count( get_comment_meta( $c[0]->comment_ID, 'ivole_review_image2' ) ) . '/' . get_comment_meta( $c[0]->comment_ID, 'rating', true ) : 'none';`);
  check('stored natively: rating 5 + 1 CusRev photo (ivole_review_image2)', saved === '1/5', saved);
  wp('eval', `foreach ( get_comments( array( 'type' => 'review', 'author_email' => 'ci-test-review@example.com', 'status' => 'all' ) ) as $c ) { wp_delete_comment( $c->comment_ID, true ); } WC_Comments::clear_transients( wc_get_product_id_by_sku( 'CI-TEST-SNEAKER' ) );`);
  check('no console errors (form)', errors.length === 0, errors.join(' | '));
  await p.close();
}

/* ---------- Load more (2 per page via a temporary mu-plugin) ---------- */
mkdirSync(MU_DIR, { recursive: true });
writeFileSync(MU_FILE, "<?php\n// Temporary: tools/reviews-check.mjs\nadd_filter( 'ci_reviews_per_page', fn() => 2 );\n");
try {
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto(PRODUCT, { waitUntil: 'networkidle' });
  check('2 cards + "Load more"', (await p.$$('.review-card')).length === 2 && (await p.$$('[data-reviews-more]')).length === 1);
  await p.click('[data-reviews-more]');
  await p.waitForFunction(() => document.querySelectorAll('.review-card').length === 4, null, { timeout: 10000 });
  check('"Load more" adds the next page and disappears at the end', (await p.$$('[data-reviews-more]')).length === 0);
  await p.close();
} finally {
  rmSync(MU_FILE, { force: true });
}

console.log(fails ? `\n${fails} check(s) failed` : '\nAll review checks passed');
await b.close();
process.exit(fails ? 1 : 0);
