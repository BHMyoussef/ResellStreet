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

  /* ---------- Checkout errors: field errors under the field, general errors at the top ---------- */

  const checkoutForm = document.querySelector('form.checkout');
  if (checkoutForm) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const behavior = () => (reducedMotion.matches ? 'auto' : 'smooth');

    const fieldOf = (row) => row.querySelector('input:not([type="hidden"]), select, textarea');
    const labelOf = (row) => (row.querySelector('label')?.textContent || '').replace(/\*|\(optional\)/gi, '').trim();

    // Message under the field (reuses WooCommerce's element/ids when it already added one).
    const setInline = (row, text) => {
      if (!text) return;
      const field = fieldOf(row);
      let msg = row.querySelector('.checkout-inline-error-message');
      if (!msg) {
        msg = document.createElement('p');
        msg.className = 'checkout-inline-error-message';
        msg.id = `${field?.id || 'field'}_description`;
        row.append(msg);
      }
      if (msg.textContent !== text) msg.textContent = text;
      if (field) {
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', msg.id);
      }
    };

    // "Billing First name is a required field." → "First name is a required field."
    const withoutPrefix = (text) => {
      for (const prefix of i18n.prefixes || []) {
        if (prefix && text.startsWith(`${prefix} `)) {
          const rest = text.slice(prefix.length + 1);
          return rest.charAt(0).toUpperCase() + rest.slice(1);
        }
      }
      return text;
    };

    // Client-side validation (on blur) only toggles classes; give those rows a message too.
    const clientMessage = (row) => {
      if (row.classList.contains('woocommerce-invalid-email')) return i18n.email;
      if (row.classList.contains('woocommerce-invalid-phone')) return i18n.phone;
      if (row.classList.contains('woocommerce-invalid-required-field')) return (i18n.required || '%s').replace('%s', labelOf(row));
      return '';
    };
    new MutationObserver((mutations) => {
      mutations.forEach(({ target }) => {
        if (!target.classList?.contains('form-row')) return;
        if (target.classList.contains('woocommerce-invalid')) {
          if (!target.querySelector('.checkout-inline-error-message')) setInline(target, clientMessage(target));
        } else {
          target.querySelector('.checkout-inline-error-message')?.remove();
          const field = fieldOf(target);
          field?.removeAttribute('aria-invalid');
        }
      });
      // Only class attributes are observed, so adding a message can't re-trigger this.
    }).observe(checkoutForm, { attributes: true, attributeFilter: ['class'], subtree: true });

    // Server errors: WooCommerce prepends a notice group to the form after "Place order" (and on order review updates).
    const handleGroup = (group) => {
      const rows = [];
      group.querySelectorAll('li[data-id]').forEach((li) => {
        const row = document.getElementById(li.dataset.id)?.closest('.form-row');
        if (!row) return; // Unknown field: keep it as a general error
        row.classList.add('woocommerce-invalid');
        setInline(row, withoutPrefix(li.textContent.trim()));
        rows.push(row);
        li.remove();
      });

      window.jQuery?.('html, body').stop(true); // cancel WooCommerce's scroll to the (top) notice list

      if (group.textContent.trim()) {
        // General error(s): banner above the whole checkout.
        let slot = document.querySelector('.checkout-errors');
        if (!slot) {
          slot = document.createElement('div');
          slot.className = 'checkout-errors';
          checkoutForm.before(slot);
        }
        slot.replaceChildren(group);
        const notice = group.querySelector('.woocommerce-error') || group;
        notice.tabIndex = -1;
        // The banner sits right under the header: scroll to the top so both are visible.
        window.scrollTo({ top: 0, behavior: behavior() });
        notice.focus({ preventScroll: true });
      } else {
        group.remove();
        const first = rows.find((r) => r.offsetParent !== null) || rows[0];
        if (first) {
          first.scrollIntoView({ behavior: behavior(), block: 'center' });
          fieldOf(first)?.focus({ preventScroll: true });
        }
      }
    };

    new MutationObserver((mutations) => {
      mutations.forEach((m) =>
        m.addedNodes.forEach((node) => {
          if (node.nodeType === 1 && node.classList.contains('woocommerce-NoticeGroup')) handleGroup(node);
        })
      );
    }).observe(checkoutForm, { childList: true }); // direct children only

    // Some gateways handle the checkout response themselves (Stripe's Payment Element) and prepend a bare
    // <ul class="woocommerce-error"> (same li[data-id] items) to the notices wrapper above the form instead.
    // Wrap it like WooCommerce's notice group and handle it the same way.
    const noticesObserver = new MutationObserver((mutations) => {
      mutations.forEach((m) =>
        m.addedNodes.forEach((node) => {
          if (node.nodeType !== 1 || !node.matches('ul.woocommerce-error')) return;
          const group = document.createElement('div');
          group.className = 'woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout';
          node.replaceWith(group);
          group.append(node);
          handleGroup(group);
        })
      );
    });
    document.querySelectorAll('.woocommerce-notices-wrapper').forEach((wrapper) => {
      if (!checkoutForm.contains(wrapper)) noticesObserver.observe(wrapper, { childList: true });
    });

    // A new attempt clears the previous banner (WooCommerce removes its own groups by class).
    document.addEventListener('submit', (e) => {
      if (e.target === checkoutForm) document.querySelector('.checkout-errors')?.replaceChildren();
    }, true);
  }
})();
