/**
 * Cart page quantity steppers, checkout order-summary toggle and discount code. Vanilla JS.
 * WooCommerce's own cart/checkout scripts (jQuery) still do the work: we click its "Update cart" button and
 * trigger its `update_checkout` event, so its events (updated_cart_totals, updated_checkout…) keep firing.
 */
(() => {
  const i18n = window.ciWcPages || {};
  const chevron = '<svg class="icon" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path d="M12 24l20 20 20-20"/></svg>';

  /* ---------- Cart page: steppers + auto update ---------- */

  const cartForm = () => document.querySelector('form.woocommerce-cart-form');
  let updateTimer = null;

  function enhanceQuantities() {
    document.querySelectorAll('.woocommerce-cart-form .quantity input.qty:not([data-ci-stepper])').forEach((input) => {
      input.dataset.ciStepper = '1';
      if (input.type === 'hidden') return; // sold individually
      const wrap = document.createElement('div');
      wrap.className = 'qty-stepper';
      const make = (step, label, text) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'qty-stepper__btn';
        b.dataset.step = step;
        b.setAttribute('aria-label', label);
        b.textContent = text;
        return b;
      };
      input.before(wrap);
      wrap.append(make(-1, i18n.decrease || '−', '−'), input, make(1, i18n.increase || '+', '+'));
    });
  }

  function scheduleUpdate() {
    clearTimeout(updateTimer);
    updateTimer = setTimeout(() => {
      const button = cartForm()?.querySelector('button[name="update_cart"]');
      if (!button) return;
      button.disabled = false;
      button.click(); // WooCommerce's cart.js submits via AJAX and fires updated_cart_totals
    }, 500);
  }

  if (document.body.classList.contains('ci-cart-page')) {
    enhanceQuantities();
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.qty-stepper__btn');
      if (!btn) return;
      const input = btn.parentElement.querySelector('input.qty');
      const min = Number(input.min) || 0;
      const max = Number(input.max) || Infinity;
      const next = Math.min(max, Math.max(min, (Number(input.value) || 0) + Number(btn.dataset.step) * (Number(input.step) || 1)));
      if (next === Number(input.value)) return;
      input.value = next;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    document.addEventListener('change', (e) => {
      if (e.target.matches('.woocommerce-cart-form input.qty')) scheduleUpdate();
    });
    // WooCommerce replaces the cart form after each update: re-add the steppers.
    const container = document.querySelector('.page-content .woocommerce');
    if (container) new MutationObserver(enhanceQuantities).observe(container, { childList: true, subtree: true });
  }

  /* ---------- Checkout: order summary toggle (mobile) ---------- */

  const heading = document.getElementById('order_review_heading');
  const review = document.getElementById('order_review');
  if (heading && review && document.body.classList.contains('ci-checkout-page')) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'summary-toggle';
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-controls', 'order_review');
    button.innerHTML = `<span></span>${chevron}<span class="summary-toggle__total"></span>`;
    button.firstElementChild.textContent = i18n.summary || heading.textContent.trim();
    heading.replaceChildren(button);

    const total = button.querySelector('.summary-toggle__total');
    const syncTotal = () => {
      const amount = review.querySelector('.order-total .woocommerce-Price-amount');
      const text = amount ? amount.textContent : '';
      if (total.textContent !== text) total.textContent = text;
    };
    syncTotal();
    button.addEventListener('click', () => {
      const open = review.classList.toggle('is-open');
      button.setAttribute('aria-expanded', String(open));
    });

    /* ---------- Checkout: discount code in the summary ---------- */

    let lastMessage = null;
    // Only write when something changed: this runs from a MutationObserver on the summary, and writing
    // textContent always counts as a DOM change, which would re-trigger the observer forever.
    const showMessage = () => {
      const node = review.querySelector('[data-coupon-message]');
      if (!node || !lastMessage) return;
      if (node.textContent !== lastMessage.text) node.textContent = lastMessage.text;
      node.classList.toggle('is-error', lastMessage.error);
      node.hidden = false;
    };
    // WooCommerce re-renders the summary table after every checkout update.
    new MutationObserver(() => {
      syncTotal();
      showMessage();
    }).observe(review, { childList: true, subtree: true });

    async function applyCoupon() {
      const params = window.wc_checkout_params;
      const input = document.getElementById('ci-coupon-code');
      const code = input?.value.trim();
      if (!params || !code) return;
      const apply = review.querySelector('[data-coupon-apply]');
      apply?.setAttribute('disabled', '');
      try {
        const res = await fetch(String(params.wc_ajax_url).replace('%%endpoint%%', 'apply_coupon'), {
          method: 'POST',
          credentials: 'same-origin',
          body: new URLSearchParams({
            security: params.apply_coupon_nonce,
            coupon_code: code,
            billing_email: document.getElementById('billing_email')?.value || '',
          }),
        });
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        lastMessage = { text: doc.body.textContent.trim(), error: !!doc.querySelector('.woocommerce-error, [role="alert"].is-error') };
        showMessage();
        if (window.jQuery) {
          const body = window.jQuery(document.body);
          if (!lastMessage.error) body.trigger('applied_coupon_in_checkout', [code]);
          body.trigger('update_checkout', { update_shipping_method: false });
        }
      } finally {
        apply?.removeAttribute('disabled');
      }
    }

    review.addEventListener('click', (e) => {
      if (e.target.closest('[data-coupon-apply]')) applyCoupon();
    });
    // Enter in the coupon field must not submit the whole checkout.
    review.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.id === 'ci-coupon-code') {
        e.preventDefault();
        applyCoupon();
      }
    });
  }
})();
