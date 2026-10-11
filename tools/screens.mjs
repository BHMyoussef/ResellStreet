// Visual reference captures.
//   node tools/screens.mjs ref    -> reference/screens   (design reference: the Shopify store novarende.com)
//   node tools/screens.mjs local  -> screens-local       (resellstreet.local)
//   optional 2nd arg: comma list of shot names to limit the run, e.g. "home,cart"
import { launch } from './lib/browser.mjs';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const TARGETS = {
  ref: {
    out: 'reference/screens',
    base: 'https://novarende.com',
    // Impulse's parallax backgrounds don't paint in stitched fullPage mode: grow the viewport instead.
    growViewport: true,
    pages: {
      home: '/',
      collection: '/collections/chaussures',
      product: '/products/mens-casual-leather-sneakers-1',
    },
    addToCart: async (page) => {
      await page.evaluate(() =>
        fetch('/cart/add.js', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: 53918707679557, quantity: 1 }),
        })
      );
    },
    openCart: '.js-drawer-open-cart',
    openMenu: '.js-drawer-open-nav',
    // Search is disabled in the store settings (no header icon), but the markup is rendered:
    // reveal it directly so we still get a reference of the open state.
    openSearch: async (page) => {
      await page.evaluate(() =>
        document.querySelector('.site-header__search-container')?.classList.add('is-active')
      );
    },
  },
  local: {
    out: 'screens-local',
    base: 'http://resellstreet.local',
    pages: {
      home: '/',
      collection: '/product-category/clothing/',
      product: '/product/mens-casual-leather-sneakers-1/',
    },
    addToCart: async (page) => {
      await page.goto(TARGETS.local.base + '/?add-to-cart=35', { waitUntil: 'networkidle' });
    },
    // Our theme's trigger contract
    openCart: '[data-drawer-open="cart"]',
    openMenu: '[data-drawer-open="menu"]',
    openSearch: '[data-search-open]',
  },
};

const VIEWPORTS = { desktop: { width: 1440, height: 900 }, mobile: { width: 390, height: 844 } };

const targetName = process.argv[2] || 'ref';
const only = process.argv[3]?.split(',');
const T = TARGETS[targetName];
if (!T) throw new Error(`Unknown target "${targetName}"`);
const outDir = path.join(ROOT, T.out);
await mkdir(outDir, { recursive: true });

async function settle(page) {
  // Trigger lazy-loaded images, then return to top
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 120));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(800);
}

async function trigger(page, action) {
  if (typeof action === 'function') return action(page);
  await page.locator(action).filter({ visible: true }).first().click();
}

// LIVE_IP=23.227.38.65 pins novarende.com if local DNS fails for Chromium.
const browser = await launch(process.env.LIVE_IP ? { args: [`--host-resolver-rules=MAP novarende.com ${process.env.LIVE_IP}`] } : {});

for (const [vpName, viewport] of Object.entries(VIEWPORTS)) {
  const context = await browser.newContext({
    viewport,
    deviceScaleFactor: vpName === 'mobile' ? 2 : 1,
    isMobile: vpName === 'mobile',
    hasTouch: vpName === 'mobile',
    locale: 'en-GB',
  });
  const page = await context.newPage();
  const shot = async (name, fullPage) => {
    const file = path.join(outDir, `${name}-${viewport.width}.png`);
    if (fullPage && T.growViewport) {
      const height = await page.evaluate(() => document.documentElement.scrollHeight);
      await page.setViewportSize({ width: viewport.width, height });
      await page.waitForTimeout(1200);
    }
    await page.screenshot({ path: file, animations: 'disabled', fullPage: fullPage && !T.growViewport });
    if (fullPage && T.growViewport) await page.setViewportSize(viewport);
    console.log('✓', path.relative(ROOT, file));
  };
  const want = (n) => !only || only.includes(n);

  try {
    for (const [name, url] of Object.entries(T.pages)) {
      if (!want(name)) continue;
      await page.goto(T.base + url, { waitUntil: 'networkidle' });
      await settle(page);
      await shot(name, true);
    }

    if (want('menu') && vpName === 'mobile') {
      await page.goto(T.base + '/', { waitUntil: 'networkidle' });
      await trigger(page, T.openMenu);
      await page.waitForTimeout(700);
      await shot('menu-open', false);
    }

    if (want('search')) {
      await page.goto(T.base + '/', { waitUntil: 'networkidle' });
      await trigger(page, T.openSearch);
      await page.waitForTimeout(700);
      await shot('search-open', false);
    }

    if (want('cart')) {
      await page.goto(T.base + '/', { waitUntil: 'networkidle' });
      await T.addToCart(page);
      await page.goto(T.base + '/', { waitUntil: 'networkidle' });
      await trigger(page, T.openCart);
      await page.waitForTimeout(900);
      await shot('cart-open', false);
    }
  } catch (err) {
    console.error(`✗ ${vpName}:`, err.message.split('\n')[0]);
  }
  await context.close();
}

await browser.close();
