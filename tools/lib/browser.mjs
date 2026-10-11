// Shared Chromium launcher for the tools.
// Stripe's Express Checkout (product, cart, checkout) beacons telemetry to r.stripe.com / m.stripe.com and
// retries failed beacons, so 'networkidle' never settles. Every context answers those locally with an empty 200.
import { chromium } from 'playwright';

const STRIPE_TELEMETRY = /^https:\/\/[rm]\.stripe\.com\//;

export async function launch(options) {
  const browser = await chromium.launch(options);
  const newContext = browser.newContext.bind(browser);
  browser.newContext = async (contextOptions) => {
    const ctx = await newContext(contextOptions);
    await ctx.route(STRIPE_TELEMETRY, (route) => route.fulfill({ status: 200, body: '' }));
    // Pages with Stripe's Payment / Express Checkout Elements (product, cart, checkout) load several third-party
    // iframes (Stripe, Amazon Pay, hCaptcha) and sometimes never reach 'networkidle'. goto(…, networkidle) = page
    // loaded, then up to 15s for the network to settle (our own scripts are done long before).
    const newPage = ctx.newPage.bind(ctx);
    ctx.newPage = async () => {
      const page = await newPage();
      const goto = page.goto.bind(page);
      page.goto = async (url, opts = {}) => {
        if (opts.waitUntil !== 'networkidle') return goto(url, opts);
        const response = await goto(url, { ...opts, waitUntil: 'load' });
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        return response;
      };
      return page;
    };
    return ctx;
  };
  // browser.newPage() creates its own context internally: route it through the patched newContext.
  browser.newPage = async (contextOptions) => (await browser.newContext(contextOptions)).newPage();
  return browser;
}

// jQuery scripts the theme dequeues outside product/cart/checkout/account pages (theme JS never needs them).
// Plugins may still load jQuery itself (PixelYourSite does on every page), so tests check these, not window.jQuery.
export const wcJqueryScripts = (page) =>
  page.$$eval('script[src]', (els) =>
    els.map((s) => s.src).filter((src) => /\/woocommerce\/assets\/js\/(frontend\/(woocommerce|add-to-cart|cart-fragments)|jquery-blockui|js-cookie)/.test(src))
  );
