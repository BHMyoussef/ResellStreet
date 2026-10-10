// Homepage behavior checks on resellstreet.local. Temporarily adds a 2nd hero slide (restored at the end).
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const WP = path.join(path.dirname(fileURLToPath(import.meta.url)), 'wp');
const wp = (...args) => execFileSync(WP, args, { encoding: 'utf8' }).trim();
const HOME = 'http://resellstreet.local/';
let fails = 0;
const check = (label, ok, info = '') => {
  if (!ok) fails++;
  console.log(`${ok ? '✓' : '✗'} ${label}${info ? '  — ' + info : ''}`);
};

const b = await chromium.launch();

/* ---------- Single slide, desktop ---------- */
{
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  await p.goto(HOME, { waitUntil: 'networkidle' });

  check('no jQuery on the homepage', await p.evaluate(() => !window.jQuery));
  const hero = await p.$eval('.hero__img', (img) => ({ fp: img.getAttribute('fetchpriority'), loading: img.loading, sizes: img.sizes, srcset: !!img.srcset }));
  check('hero image: eager + fetchpriority=high + srcset', hero.fp === 'high' && hero.loading === 'eager' && hero.srcset, JSON.stringify(hero));
  check('single slide: no slider controls', (await p.$$('.hero__bar')).length === 0);
  check('4 collection tiles link to categories', (await p.$$eval('.collection-tile', (a) => a.filter((x) => /product-category/.test(x.href)).length)) === 4);

  const selected = () => p.$$eval('[data-carousel-slide]', (s) => s.findIndex((x) => x.classList.contains('is-selected')));
  check('testimonials start on the 2nd card (centred)', (await selected()) === 1);
  await p.click('[data-carousel-go="3"]');
  await p.waitForTimeout(900);
  check('dot click centres that card', (await selected()) === 3);
  await p.focus('[data-carousel]');
  await p.keyboard.press('ArrowLeft');
  await p.waitForTimeout(900);
  check('arrow key moves the carousel', (await selected()) === 2);

  const storyShift = async () => p.$eval('.story__img', (i) => i.style.transform);
  await p.evaluate(() => document.querySelector('.story').scrollIntoView({ block: 'end' }));
  await p.waitForTimeout(200);
  const a = await storyShift();
  await p.mouse.wheel(0, 300);
  await p.waitForTimeout(300);
  const c = await storyShift();
  check('parallax moves the banner image on desktop', a && c && a !== c, `${a} → ${c}`);
  check('no console errors (desktop)', errors.length === 0, errors.join(' | '));
  await p.close();
}

/* ---------- Testimonials = real reviews (Customizer filters) ---------- */
{
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const names = async () => {
    await p.goto(HOME, { waitUntil: 'networkidle' });
    return p.$$eval('.testimonial__name', (n) => n.map((x) => x.textContent).join(', '));
  };
  check('testimonials: 5★ reviews with photos, newest first', (await names()) === 'Liam P., Daniel M., Marc T., James R.', await names());
  check('testimonial cards link to the review on its product page', await p.$$eval('.testimonial__info', (a) => a.every((x) => /\/product\/.+#review-\d+$/.test(x.getAttribute('href')))));
  try {
    wp('theme', 'mod', 'set', 'home_testimonials_photos', '');
    const noPhotos = await names();
    check('"photos required" off → 5★ review without photo appears (Adam K.)', noPhotos.includes('Adam K.') && noPhotos.split(', ').length === 5, noPhotos);
    wp('theme', 'mod', 'set', 'home_testimonials_min', '4');
    const min4 = await names();
    check('minimum rating 4 → the 4★ review appears too (Oliver M.)', min4.includes('Oliver M.') && !min4.includes('Sam T.'), min4);
  } finally {
    wp('theme', 'mod', 'set', 'home_testimonials_photos', '1');
    wp('theme', 'mod', 'set', 'home_testimonials_min', '5');
  }
  await p.close();
}

/* ---------- Mobile: no parallax ---------- */
{
  const p = await b.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await p.goto(HOME, { waitUntil: 'networkidle' });
  await p.evaluate(() => window.scrollTo(0, 1500));
  await p.waitForTimeout(300);
  check('no parallax transform on mobile', (await p.$eval('.story__img', (i) => i.style.transform)) === '');
  const ratio = await p.$eval('.hero__slide', (s) => (s.getBoundingClientRect().height / s.getBoundingClientRect().width).toFixed(2));
  check('mobile hero height follows the image (≈1.25)', ratio === '1.25', ratio);
  await p.close();
}

/* ---------- Two slides: slideshow ---------- */
const story = wp('theme', 'mod', 'get', 'home_story_image', '--field=value');
wp('theme', 'mod', 'set', 'home_hero_2_image', story);
wp('theme', 'mod', 'set', 'home_hero_2_title', 'Second slide');
wp('theme', 'mod', 'set', 'home_hero_autoplay', '2');
try {
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  await p.goto(HOME, { waitUntil: 'networkidle' });
  const active = () => p.$$eval('[data-hero-slide]', (s) => s.findIndex((x) => x.classList.contains('is-active')));
  check('two slides render with progress bars', (await p.$$('.hero__bar')).length === 2);
  check('hidden slide is inert', await p.$eval('[data-hero-slide]:nth-child(2)', (s) => s.inert));
  await p.mouse.move(5, 890); // away from the hero
  await p.waitForTimeout(2600);
  check('autoplay advances', (await active()) === 1);
  await p.click('.hero__bar[data-hero-go="0"]');
  await p.waitForTimeout(300);
  check('progress bar click goes to slide', (await active()) === 0);
  await p.hover('.hero');
  await p.waitForTimeout(2600);
  check('hover pauses autoplay', (await active()) === 0);
  check('no console errors (slideshow)', errors.length === 0, errors.join(' | '));
  await p.close();
} finally {
  wp('theme', 'mod', 'remove', 'home_hero_2_image', 'home_hero_2_title');
  wp('theme', 'mod', 'set', 'home_hero_autoplay', '7');
}

console.log(fails ? `\n${fails} check(s) failed` : '\nAll home checks passed');
await b.close();
process.exit(fails ? 1 : 0);
