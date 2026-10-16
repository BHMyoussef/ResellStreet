/**
 * AJAX cart drawer on the WooCommerce Store API + Quick Shop. Vanilla JS.
 *
 * - Every write sends the Store API `Nonce` header; the value is refreshed from each response's `Nonce`
 *   header, and an invalid/missing nonce triggers one GET /cart (fresh nonce) and a retry.
 * - After add/update/remove, the matching jQuery events are fired on document.body when jQuery is present
 *   (added_to_cart / updated_cart_totals / removed_from_cart), after refreshing WooCommerce cart fragments,
 *   so pixel plugins (PixelYourSite, Facebook for WooCommerce…) keep tracking.
 */
(() => {
  const cfg = window.ciCart;
  if (!cfg) return;

  const ns = (window.CustomImpulse = window.CustomImpulse || {});
  const root = document.documentElement;
  const drawer = document.querySelector('dialog[data-drawer="cart"]');
  const $ = (sel, el = document) => el.querySelector(sel);

  let nonce = cfg.nonce;
  let cart = null;
  let queue = Promise.resolve();

  /* ---------- Helpers ---------- */

  // Store API strings can carry HTML entities/tags: decode and strip, then always insert as text.
  const text = (value) => new DOMParser().parseFromString(String(value ?? ''), 'text/html').body.textContent || '';

  const money = (minor, c) => {
    const unit = c.currency_minor_unit ?? 2;
    const [int, dec] = (Number(minor) / 10 ** unit).toFixed(unit).split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, c.currency_thousand_separator ?? ',');
    return `${c.currency_prefix ?? ''}${grouped}${dec ? (c.currency_decimal_separator ?? '.') + dec : ''}${c.currency_suffix ?? ''}`;
  };

  const el = (tag, attrs = {}, ...children) => {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
      if (v === false || v == null) return;
      if (k === 'class') node.className = v;
      else if (k === 'text') node.textContent = v;
      else node.setAttribute(k, v === true ? '' : v);
    });
    node.append(...children.filter(Boolean));
    return node;
  };

  // Run cart writes one at a time, in order.
  const enqueue = (fn) => {
    const run = queue.then(fn);
    queue = run.catch(() => {});
    return run;
  };

  /* ---------- Store API ---------- */

  async function api(path, { method = 'GET', body, retry = true } = {}) {
    const headers = { Accept: 'application/json' };
    if (body) headers['Content-Type'] = 'application/json';
    if (method !== 'GET') headers.Nonce = nonce;
    if (cfg.restNonce) headers['X-WP-Nonce'] = cfg.restNonce; // logged-in customers: keep their session

    const res = await fetch(cfg.storeApi + path, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined,
    });
    const fresh = res.headers.get('Nonce');
    if (fresh) nonce = fresh;

    let data = null;
    try {
      data = await res.json();
    } catch {
      /* empty body */
    }

    if (!res.ok) {
      const nonceProblem = ['woocommerce_rest_invalid_nonce', 'woocommerce_rest_missing_nonce'].includes(data?.code);
      if (retry && method !== 'GET' && nonceProblem) {
        await api('cart'); // Fresh nonce (e.g. page served from cache)
        return api(path, { method, body, retry: false });
      }
      const error = new Error(text(data?.message) || cfg.i18n.error);
      error.data = data;
      throw error;
    }
    return data;
  }

  /* ---------- Pixel / plugin compatibility ---------- */

  async function notify(event, button) {
    document.body.dispatchEvent(new CustomEvent(`ci:cart:${event}`, { bubbles: true, detail: { cart } }));

    const jq = window.jQuery;
    // Meta tracking (inc/meta-tracking.php) queues the browser AddToCart in the `div.nv-meta-q` fragment.
    if (!jq && !window.nvMeta) return;
    let fragments = {};
    let hash = '';
    try {
      const res = await fetch(cfg.fragmentsUrl, { method: 'POST', credentials: 'same-origin' });
      const data = await res.json();
      fragments = data?.fragments || {};
      hash = data?.cart_hash || '';
      // jQuery's replaceWith runs scripts inside fragments, which some pixel plugins rely on.
      if (jq) Object.entries(fragments).forEach(([selector, html]) => jq(selector).replaceWith(html));
      else if (fragments['div.nv-meta-q']) {
        document.querySelectorAll('div.nv-meta-q').forEach((el) => (el.outerHTML = fragments['div.nv-meta-q']));
      }
    } catch {
      /* Fragments are optional; still fire the event */
    }
    if (event === 'added') window.nvMeta && nvMeta.drain();

    if (!jq) return;
    const body = jq(document.body);
    if (event === 'added') body.trigger('added_to_cart', [fragments, hash, jq(button || [])]);
    else if (event === 'removed') body.trigger('removed_from_cart', [fragments, hash, jq(button || [])]);
    else body.trigger('updated_cart_totals');
    body.trigger('wc_fragments_refreshed');
  }

  /* ---------- Cart count (header dot) ---------- */

  function setCount(count) {
    document.querySelectorAll('[data-cart-dot]').forEach((dot) => (dot.hidden = !count));
    document.querySelectorAll('.header-icon--cart .visually-hidden').forEach((label) => {
      label.textContent = cfg.i18n.cartCount.replace('%d', count);
    });
    // Same cookie WooCommerce uses, so cached pages show the right dot on the next load.
    document.cookie = count
      ? 'woocommerce_items_in_cart=1; path=/; SameSite=Lax'
      : 'woocommerce_items_in_cart=; path=/; max-age=0; SameSite=Lax';
  }

  // Pages may come from a full-page cache: trust WooCommerce's cookie when it exists.
  if (/(?:^|; )woocommerce_items_in_cart=/.test(document.cookie)) {
    const has = /(?:^|; )woocommerce_items_in_cart=1/.test(document.cookie);
    document.querySelectorAll('[data-cart-dot]').forEach((dot) => (dot.hidden = !has));
  }

  /* ---------- Drawer rendering ---------- */

  const parts = drawer && {
    body: $('[data-cart-body]', drawer),
    loading: $('[data-cart-loading]', drawer),
    items: $('[data-cart-items]', drawer),
    empty: $('[data-cart-empty]', drawer),
    footer: $('[data-cart-footer]', drawer),
    subtotal: $('[data-cart-subtotal]', drawer),
    error: $('[data-cart-error]', drawer),
    status: $('[data-cart-status]', drawer),
    titleCount: $('[data-cart-title-count]', drawer),
    shipping: $('[data-cart-shipping]', drawer),
    shippingValue: $('[data-cart-shipping-value]', drawer),
    notice: $('[data-cart-notice]', drawer),
    noticeTitle: $('[data-cart-notice-title]', drawer),
    noticeText: $('[data-cart-notice-text]', drawer),
  };
  // "Added to cart" banner: shown after an add until the drawer closes.
  let justAdded = false;
  drawer?.addEventListener('close', () => {
    justAdded = false;
    if (parts) parts.notice.hidden = true;
  });

  function itemNode(item) {
    const p = item.prices;
    const image = item.images?.[0];
    const href = item.permalink || '#';
    const qty = item.quantity;
    const limits = item.quantity_limits || {};
    const name = text(item.name);
    const ci = item.extensions?.ci || {}; // brand, short title, "Mint Blue / UK 8" (inc/cart.php)

    // Variant line from our extension; plugin item data (add-ons) stays as label: value rows.
    const meta = el('dl', { class: 'cart-item__meta' });
    const rows = ci.variant
      ? (item.item_data || []).map((d) => [d.name || d.key, d.display || d.value])
      : [...(item.variation || []).map((v) => [v.attribute, v.value]), ...(item.item_data || []).map((d) => [d.name || d.key, d.display || d.value])];
    rows
      .filter(([k, v]) => k && v)
      .forEach(([k, v]) => meta.append(el('div', {}, el('dt', { text: `${text(k)}:` }), el('dd', { text: text(v) }))));

    const price = el('div', { class: 'cart-item__price' });
    if (Number(p.regular_price) > Number(p.price)) {
      price.append(el('s', { class: 'price__compare', text: money(Number(p.regular_price) * qty, p) }));
    }
    price.append(el('span', { class: 'cart-item__amount', text: money(Number(p.price) * qty, p) }));

    let quantity;
    if (limits.editable === false) {
      quantity = el('span', { class: 'cart-item__qty-fixed', text: `× ${qty}` });
    } else {
      const step = limits.multiple_of || 1;
      quantity = el(
        'div',
        { class: 'qty' },
        el('button', { type: 'button', class: 'qty__btn', 'data-qty-step': -step, 'aria-label': qty <= (limits.minimum || 1) ? `${cfg.i18n.remove} ${name}` : cfg.i18n.decrease }, '−'),
        el('input', {
          class: 'qty__input',
          type: 'number',
          inputmode: 'numeric',
          value: qty,
          min: 0,
          max: limits.maximum && limits.maximum < 9999 ? limits.maximum : false,
          step,
          'aria-label': `${cfg.i18n.quantity}: ${name}`,
        }),
        el('button', { type: 'button', class: 'qty__btn', 'data-qty-step': step, 'aria-label': cfg.i18n.increase, disabled: limits.maximum && qty >= limits.maximum }, '+')
      );
    }

    const img = image
      ? el('img', {
          src: image.thumbnail || image.src,
          srcset: image.srcset || false,
          sizes: image.srcset ? '100px' : false,
          alt: text(image.alt) || '',
          width: 100,
          height: 100,
          loading: 'lazy',
        })
      : null;

    return el(
      'li',
      { class: 'cart-item', 'data-key': item.key },
      el('a', { class: 'cart-item__image', href, tabindex: '-1', 'aria-hidden': 'true' }, img),
      el(
        'div',
        { class: 'cart-item__details' },
        ci.brand ? el('p', { class: 'cart-item__brand', text: text(ci.brand) }) : null,
        el('a', { class: 'cart-item__name', href, text: text(ci.title) || name }),
        ci.variant ? el('p', { class: 'cart-item__variant', text: text(ci.variant) }) : null,
        meta.children.length ? meta : null,
        el('div', { class: 'cart-item__row' }, quantity, price),
        limits.editable === false ? null : el('button', { type: 'button', class: 'cart-item__remove', 'data-qty-remove': '', 'aria-label': `${cfg.i18n.remove} ${name}`, text: cfg.i18n.remove })
      )
    );
  }

  function render(next) {
    cart = next;
    if (!parts) return setCount(next.items_count || 0);
    const items = next.items || [];
    parts.items.replaceChildren(...items.map(itemNode));
    parts.empty.hidden = items.length > 0;
    parts.footer.hidden = items.length === 0;
    const t = next.totals || {};
    const subtotal = Number(t.total_items || 0) + (cfg.taxIncl ? Number(t.total_items_tax || 0) : 0);
    parts.subtotal.textContent = money(subtotal, t);
    parts.titleCount.textContent = items.length ? `(${next.items_count})` : '';

    // Free shipping (WooCommerce "Free shipping" minimum): "UK shipping: Free" row + the banner message.
    const minor = 10 ** (t.currency_minor_unit ?? 2);
    const threshold = cfg.freeShipping;
    const left = threshold === null || threshold === undefined ? null : Math.max(0, threshold * minor - subtotal);
    parts.shipping.hidden = left === null;
    if (left !== null) parts.shippingValue.textContent = left === 0 ? cfg.i18n.free : cfg.i18n.atCheckout;
    parts.notice.hidden = !justAdded;
    if (justAdded) {
      parts.noticeTitle.textContent = cfg.i18n.added;
      parts.noticeText.textContent = left === null ? '' : left === 0 ? cfg.i18n.unlocked : cfg.i18n.spendMore.replace('%s', money(left, t));
    }
    parts.loading.hidden = true;
    parts.body.setAttribute('aria-busy', 'false');
    setCount(next.items_count || 0);
  }

  function showError(message) {
    if (!parts) return;
    parts.error.textContent = message || '';
    parts.error.hidden = !message;
  }

  async function load() {
    try {
      render(await api('cart'));
      showError('');
    } catch (err) {
      parts && (parts.loading.hidden = true);
      showError(err.message);
    }
  }

  let prefetched = null;
  const prefetch = () => (prefetched ||= load());

  function setQuantity(key, quantity, button) {
    const row = parts?.items.querySelector(`[data-key="${CSS.escape(key)}"]`);
    row?.classList.add('is-busy');
    return enqueue(async () => {
      try {
        const removing = quantity <= 0;
        const next = removing
          ? await api('cart/remove-item', { method: 'POST', body: { key } })
          : await api('cart/update-item', { method: 'POST', body: { key, quantity } });
        render(next);
        showError('');
        if (parts) parts.status.textContent = removing ? cfg.i18n.remove : cfg.i18n.quantity;
        notify(removing ? 'removed' : 'updated', button);
      } catch (err) {
        showError(err.message);
        if (cart) render(cart); // Restore the last known state
      }
    });
  }

  if (drawer) {
    drawer.addEventListener('drawer:open', () => {
      if (cart) load(); // Show what we have, refresh quietly (another tab may have changed it)
      else prefetch();
    });

    drawer.addEventListener('click', (e) => {
      // Checkout: slide the drawer out while the checkout page loads (same-tab clicks only)
      if (e.target.closest('.cart-drawer__checkout')) {
        if (e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey) window.CustomImpulse?.closeDrawer?.(drawer);
        return;
      }
      const remove = e.target.closest('[data-qty-remove]');
      if (remove) {
        setQuantity(remove.closest('[data-key]').dataset.key, 0, remove); // same path as "−" down to 0
        return;
      }
      const btn = e.target.closest('[data-qty-step]');
      if (!btn) return;
      const row = btn.closest('[data-key]');
      const input = $('.qty__input', row);
      const next = Math.max(0, Number(input.value) + Number(btn.dataset.qtyStep));
      input.value = next;
      setQuantity(row.dataset.key, next, btn);
    });

    drawer.addEventListener('change', (e) => {
      if (!e.target.matches('.qty__input')) return;
      const row = e.target.closest('[data-key]');
      setQuantity(row.dataset.key, Math.max(0, parseInt(e.target.value, 10) || 0), e.target);
    });

    // Warm up the cart before the drawer opens.
    document.querySelectorAll('[data-drawer-open="cart"]').forEach((link) => {
      ['pointerenter', 'focus', 'touchstart'].forEach((type) => link.addEventListener(type, prefetch, { once: true, passive: true }));
    });
  }

  /* ---------- Add to cart forms (product page, Quick Shop) ---------- */

  // Fields we can translate to a Store API request. Anything else (add-ons, gift wrap…) → normal POST.
  const KNOWN_FIELD = /^(quantity|add-to-cart|product_id|variation_id|attribute_.+|ci-attribute_.+)$/;

  function formError(form, message) {
    let node = form.querySelector('.form-error');
    if (!message) return node?.remove();
    if (!node) {
      node = el('p', { class: 'form-error', role: 'alert' });
      (form.querySelector('.single_add_to_cart_button') || form.lastElementChild).after(node);
    }
    node.textContent = message;
  }

  async function addToCart(payload, button, form) {
    formError(form, '');
    button?.classList.add('is-loading');
    if (button) button.disabled = true;
    try {
      const next = await enqueue(() => api('cart/add-item', { method: 'POST', body: payload }));
      justAdded = true;
      render(next);
      showError('');
      const modal = form.closest('dialog[data-modal]');
      if (modal?.open) modal.close();
      ns.openDrawer?.('cart');
      notify('added', button);
    } catch (err) {
      formError(form, err.message);
    } finally {
      button?.classList.remove('is-loading');
      if (button) button.disabled = false;
    }
  }

  // WooCommerce briefly marks the button disabled (~300ms) while it re-evaluates after each option change,
  // and alerts if it's clicked then. When a variation is already resolved, hold the click and submit
  // as soon as WooCommerce re-enables the button instead.
  const whenEnabled = (button, timeout = 1000) =>
    new Promise((resolve) => {
      if (!button.classList.contains('disabled')) return resolve(true);
      const timer = setTimeout(() => {
        observer.disconnect();
        resolve(false);
      }, timeout);
      const observer = new MutationObserver(() => {
        if (button.classList.contains('disabled')) return;
        clearTimeout(timer);
        observer.disconnect();
        resolve(true);
      });
      observer.observe(button, { attributes: true, attributeFilter: ['class'] });
    });

  // Right after page load every option can already be chosen while WooCommerce hasn't resolved the variation yet
  // (variation_id still 0, button not disabled): a tap then would fall through to a full-page POST. Wait for it.
  // The value is set without a native event, so poll briefly (AJAX variation mode needs a request).
  const variationIdOf = (form) => {
    const value = form.querySelector('input[name="variation_id"]')?.value;
    return value && value !== '0' ? value : '';
  };
  const whenResolved = (form, button, timeout = 3000) =>
    new Promise((resolve) => {
      const start = Date.now();
      const timer = setInterval(() => {
        const done = variationIdOf(form);
        if (done || button.classList.contains('wc-variation-is-unavailable') || Date.now() - start > timeout) {
          clearInterval(timer);
          resolve(Boolean(done));
        }
      }, 50);
    });

  document.addEventListener(
    'click',
    (e) => {
      const button = e.target.closest?.('form.variations_form .single_add_to_cart_button');
      if (!drawer || !button) return;
      const form = button.form;
      if (form && !variationIdOf(form) && !button.classList.contains('disabled')) {
        const selects = [...form.querySelectorAll('select[name^="attribute_"]')];
        if (!selects.length || selects.some((select) => !select.value)) return; // Really incomplete: let WooCommerce say so
        e.preventDefault();
        e.stopImmediatePropagation();
        whenResolved(form, button).then((ok) => {
          if (ok) button.click(); // Back through this handler, now with a variation (and WC's disabled state, if any)
          else formError(form, button.classList.contains('wc-variation-is-unavailable') ? cfg.i18n.unavailable : cfg.i18n.selectOptions);
        });
        return;
      }
      if (!button.classList.contains('disabled')) return;
      const variationId = form && variationIdOf(form);
      if (!variationId) return; // Really incomplete: let WooCommerce say so
      e.preventDefault();
      e.stopImmediatePropagation();
      whenEnabled(button).then((ok) => {
        if (ok) button.form.requestSubmit(button);
        else formError(button.form, button.classList.contains('wc-variation-is-unavailable') ? cfg.i18n.unavailable : cfg.i18n.selectOptions);
      });
    },
    true
  );

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!drawer || !(form instanceof HTMLFormElement) || !form.matches('form.cart') || form.matches('.grouped_form')) return;

    let data;
    try {
      data = new FormData(form, e.submitter);
    } catch {
      return; // Old browser: normal submit
    }
    if ([...data.keys()].some((name) => !KNOWN_FIELD.test(name))) return;
    const id = Number(data.get('variation_id') || data.get('product_id') || data.get('add-to-cart'));
    if (!id) return;

    e.preventDefault();
    const button = e.submitter || form.querySelector('.single_add_to_cart_button');
    if (button?.classList.contains('disabled')) {
      formError(form, cfg.i18n.selectOptions);
      return;
    }

    const variation = [];
    for (const [name, value] of data) {
      if (name.startsWith('attribute_') && value) variation.push({ attribute: name, value });
    }
    addToCart({ id, quantity: Math.max(1, Number(data.get('quantity')) || 1), variation }, button, form);
  });

  /* ---------- Quick Shop ---------- */

  const quickShop = document.querySelector('dialog[data-modal="quick-shop"]');
  let productAssets = null;

  function loadProductAssets() {
    if (productAssets) return productAssets;
    const css = new Promise((resolve) => {
      const link = el('link', { rel: 'stylesheet', href: cfg.productCss });
      link.onload = link.onerror = resolve;
      document.head.append(link);
    });
    const js = ns.initVariantForm
      ? Promise.resolve()
      : new Promise((resolve, reject) => {
          const script = el('script', { src: cfg.productJs });
          script.onload = resolve;
          script.onerror = reject;
          document.body.append(script);
        });
    productAssets = Promise.all([css, js]);
    return productAssets;
  }

  if (quickShop) {
    const body = $('[data-quick-shop-body]', quickShop);

    document.addEventListener(
      'pointerenter',
      (e) => {
        if (e.target instanceof Element && e.target.matches('.product-card__quick')) loadProductAssets();
      },
      true
    );

    document.addEventListener('click', async (e) => {
      const trigger = e.target.closest('[data-quick-shop]');
      if (trigger) {
        e.preventDefault();
        body.replaceChildren(el('div', { class: 'quick-shop-loading' }, el('span', { class: 'spinner', 'aria-hidden': 'true' })));
        quickShop.showModal();
        root.classList.add('has-drawer');
        try {
          const [res] = await Promise.all([
            fetch(cfg.quickShopUrl + trigger.dataset.quickShop, { credentials: 'same-origin' }).then((r) => (r.ok ? r.json() : Promise.reject(r))),
            loadProductAssets(),
          ]);
          body.innerHTML = res.html; // Markup from our own REST endpoint
          body.querySelectorAll('form.variations_form').forEach((f) => ns.initVariantForm?.(f));
        } catch {
          body.replaceChildren(el('p', { class: 'quick-shop-error', text: cfg.i18n.error }));
        }
        return;
      }

      // Thumbnail → main image
      const thumb = e.target.closest('[data-qs-thumb]');
      if (thumb) {
        const main = $('[data-qs-main]', thumb.closest('.quick-shop'));
        if (main) {
          main.src = thumb.dataset.qsThumb;
          if (thumb.dataset.qsSrcset) main.setAttribute('srcset', thumb.dataset.qsSrcset);
        }
        thumb.closest('.quick-shop__thumbs').querySelectorAll('[data-qs-thumb]').forEach((t) => t.classList.toggle('is-active', t === thumb));
      }
    });

    quickShop.addEventListener('close', () => body.replaceChildren());
  }

  ns.cart = { load, render, add: addToCart, setQuantity, api };
})();
