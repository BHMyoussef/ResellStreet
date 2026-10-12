/**
 * Product forms: variation buttons ↔ WooCommerce's (hidden) selects, price/stock sync, sticky add to cart.
 * Vanilla JS.
 *
 * On the product page WooCommerce's variation script (jQuery) keeps doing its job (variation_id, images,
 * AJAX lookups); we only set the selects and fire native `change` events, which its listeners receive.
 * In Quick Shop that script isn't loaded, so we run "standalone": set variation_id, the image and the
 * button state ourselves from the variation map.
 */
(() => {
  const ns = (window.CustomImpulse = window.CustomImpulse || {});
  if (ns.initVariantForm) return; // Loaded twice (product page + Quick Shop loader)

  function initVariantForm(form) {
    if (!form || form.dataset.ciInit) return;
    form.dataset.ciInit = '1';

    const scope = form.closest('[data-product-scope]') || document;
    const priceEl = scope.querySelector('[data-product-price]');
    const stockEl = scope.querySelector('[data-product-stock]');
    const sticky = scope === document ? document.querySelector('[data-sticky-atc]') : null;
    const stickyPrice = sticky?.querySelector('[data-sticky-price]');
    const basePrice = priceEl?.innerHTML ?? '';

    const setPrice = (html) => {
      if (priceEl && priceEl.innerHTML !== html) priceEl.innerHTML = html;
      if (stickyPrice && stickyPrice.innerHTML !== html) stickyPrice.innerHTML = html;
    };

    // state: ok | low | out | pick (nothing chosen yet: "In stock, ready to ship – select your size")
    const setStock = (state, qty) => {
      if (!stockEl) return;
      stockEl.classList.remove('product-stock--ok', 'product-stock--low', 'product-stock--out');
      stockEl.classList.add(`product-stock--${state === 'pick' ? 'ok' : state}`);
      const label = stockEl.dataset[`label${state[0].toUpperCase()}${state.slice(1)}`] || '';
      const text = stockEl.querySelector('.product-stock__text');
      const next = label.replace('%d', qty ?? '');
      if (text.textContent !== next) text.textContent = next;
    };

    initQuantity(form);

    // Sticky bar line "Mint Blue / UK 8": the product's colour + every chosen option (sizes get "UK").
    const stickyVariant = sticky?.querySelector('[data-sticky-variant]');
    const setStickyVariant = () => {
      if (!stickyVariant) return;
      const parts = [...scope.querySelectorAll('.summary .product-colours .variant-head__value, [data-variant-value]')]
        .filter((el) => el.closest('form') === form || el.closest('.product-colours'))
        .map((el) => (el.matches('[data-variant-value]') && el.textContent === el.dataset.empty ? '' : `${el.dataset.prefix ? el.dataset.prefix + ' ' : ''}${el.textContent.trim()}`))
        .filter(Boolean);
      const text = parts.join(' / ');
      if (stickyVariant.textContent !== text) stickyVariant.textContent = text;
    };
    setStickyVariant(); // simple products: colour only (variable products update it in render())

    const dataEl = form.querySelector('.ci-variant-data');
    if (!dataEl) return; // Simple product: nothing to sync

    const { attrs, variations, prices, threshold } = JSON.parse(dataEl.textContent);
    const select = (name) => form.querySelector(`select[name="${CSS.escape(name)}"]`);
    const group = (name) => form.querySelector(`[data-variant-group="${CSS.escape(name)}"]`);
    // Check the script tag, not jQuery.fn: WooCommerce's (deferred) script may not have run yet.
    const standalone = !document.getElementById('wc-add-to-cart-variation-js');
    const button = form.querySelector('.single_add_to_cart_button');
    const variationInput = form.querySelector('input[name="variation_id"]');
    const mainImage = scope.querySelector('[data-qs-main]');
    const baseImage = mainImage ? { src: mainImage.src, srcset: mainImage.getAttribute('srcset') || '' } : null;
    let applying = false;

    const current = () => Object.fromEntries(attrs.map((n) => [n, select(n)?.value || '']));

    // A variation attribute of '' means "any value".
    const fits = (v, sel) => Object.entries(sel).every(([k, val]) => !val || !v.a[k] || v.a[k] === val);

    // An option is available if an in-stock variation matches it together with the choices made
    // for the attributes *before* it. Later attributes adapt instead of blocking earlier ones.
    const available = (index, value, sel) => {
      const partial = {};
      attrs.slice(0, index).forEach((n) => (partial[n] = sel[n]));
      partial[attrs[index]] = value;
      return variations.some((v) => v.s && fits(v, partial));
    };

    const setImage = (img) => {
      if (!mainImage || !baseImage) return;
      const next = img || baseImage;
      mainImage.src = next.src;
      if (next.srcset) mainImage.setAttribute('srcset', next.srcset);
      else mainImage.removeAttribute('srcset');
    };

    const render = () => {
      const sel = current();
      attrs.forEach((name, i) => {
        group(name)?.querySelectorAll('input').forEach((input) => {
          input.disabled = !available(i, input.value, sel);
          input.checked = sel[name] === input.value;
        });
      });

      // Visible "SIZE (UK): 8" value
      attrs.forEach((name) => {
        const out = form.querySelector(`[data-variant-value="${CSS.escape(name)}"]`);
        if (!out) return;
        const input = group(name)?.querySelector('input:checked');
        const text = input ? input.labels[0].textContent.trim() : out.dataset.empty;
        if (out.textContent !== text) out.textContent = text;
      });
      setStickyVariant();

      const match = attrs.every((n) => sel[n]) ? variations.find((v) => fits(v, sel)) : null;
      if (match) {
        setPrice(prices[match.p]);
        const low = match.q !== null && match.q <= threshold;
        setStock(match.s ? (low ? 'low' : 'ok') : 'out', match.q);
      } else {
        setPrice(basePrice);
        if (variations.some((v) => v.s)) setStock('pick');
      }

      if (standalone) {
        if (variationInput) variationInput.value = match && match.s ? match.i : '';
        button?.classList.toggle('disabled', !(match && match.s));
        button?.classList.toggle('wc-variation-selection-needed', !match);
        setImage(match?.img);
      }
    };

    // Set the selects in attribute order, firing `change` after each so WooCommerce rebuilds
    // the next select's options (≤ 30 variations mode) before we pick from it.
    const apply = (desired) => {
      applying = true;
      attrs.forEach((n) => {
        const s = select(n);
        if (s) s.value = '';
      });
      attrs.forEach((n) => {
        const s = select(n);
        if (!s) return;
        if (desired[n]) s.value = desired[n];
        s.dispatchEvent(new Event('change', { bubbles: true }));
      });
      applying = false;
      render();
    };

    const choose = (name, value) => {
      const index = attrs.indexOf(name);
      const desired = { ...current(), [name]: value };
      // Keep later choices when still possible, otherwise move to their first available option.
      attrs.slice(index + 1).forEach((n, k) => {
        const j = index + 1 + k;
        if (desired[n] && available(j, desired[n], desired)) return;
        const first = [...(group(n)?.querySelectorAll('input') ?? [])].find((inp) => available(j, inp.value, desired));
        desired[n] = first ? first.value : '';
      });
      apply(desired);
    };

    form.addEventListener('change', (e) => {
      if (applying) return;
      if (e.target.matches('.variant-buttons__input')) {
        choose(e.target.closest('[data-variant-group]').dataset.variantGroup, e.target.value);
      } else if (e.target.matches('.variations select')) {
        render(); // Changed by WooCommerce or another plugin
      }
    });

    if (standalone) {
      // WooCommerce's script normally reveals these; without it they'd stay hidden or disabled.
      form.querySelector('.single_variation_wrap')?.removeAttribute('style');
      form.querySelector('.woocommerce-variation-add-to-cart')?.classList.remove('woocommerce-variation-add-to-cart-disabled');
    }

    render();
  }

  /* ---------- Sticky add to cart (mobile, product page only) ---------- */

  function initSticky() {
    const sticky = document.querySelector('[data-sticky-atc]');
    const mainButton = document.querySelector('form.cart .single_add_to_cart_button');
    if (!sticky || !mainButton) return;

    const form = mainButton.closest('form');
    const stickyButton = sticky.querySelector('[data-sticky-button]');
    const mobile = window.matchMedia('(max-width: 768px)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let scrolledPast = false;

    const update = () => {
      const show = mobile.matches && scrolledPast;
      sticky.classList.toggle('is-visible', show);
      sticky.inert = !show;
      sticky.setAttribute('aria-hidden', String(!show));
      document.body.classList.toggle('has-sticky-atc', show);
    };

    // The root extends far below the screen, so the button only stops "intersecting" once it has
    // scrolled past the top edge. A fast fling from below to above can't skip the state change.
    new IntersectionObserver(
      ([entry]) => {
        scrolledPast = !entry.isIntersecting;
        update();
      },
      { rootMargin: '0px 0px 100000px 0px' }
    ).observe(mainButton);
    mobile.addEventListener('change', update);

    const isBlocked = () => mainButton.disabled || mainButton.classList.contains('disabled');
    const sync = () => {
      stickyButton.classList.toggle('is-disabled', isBlocked());
      stickyButton.textContent = mainButton.textContent.trim();
    };
    new MutationObserver(sync).observe(mainButton, {
      attributes: true,
      attributeFilter: ['class', 'disabled'],
      childList: true,
      characterData: true,
      subtree: true,
    });
    sync();

    stickyButton.addEventListener('click', () => {
      if (isBlocked()) {
        // Nothing valid selected yet: take the shopper to the options.
        form.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'center' });
        return;
      }
      mainButton.click(); // Same path as the main button (validation, AJAX cart, pixel events)
    });
  }

  /* ---------- Reviews: form toggle, "Load more", photo lightbox ---------- */

  function initReviews() {
    const root = document.querySelector('[data-reviews]');
    if (!root) return;
    const cfg = window.ciReviews || {};
    const form = document.getElementById('review-form');
    const list = root.querySelector('[data-reviews-list]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let uploadLoaded = false;

    // CusRev's upload script (photo upload on the form) is only fetched when the form is about to be used.
    // If a photo was picked before it finished loading, replay the change so CusRev still uploads it.
    const loadUploadScript = () => {
      if (uploadLoaded || !cfg.uploadScript || !window.jQuery) return;
      uploadLoaded = true;
      const script = document.createElement('script');
      script.src = cfg.uploadScript;
      // CusRev binds its handler in a jQuery ready callback, which jQuery runs asynchronously even after load:
      // queue the replay as a ready callback too, so it runs after CusRev's.
      script.onload = () =>
        window.jQuery(() => {
          const input = form?.querySelector('input[type="file"]');
          if (input?.files?.length) input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      document.body.append(script);
    };
    root.querySelectorAll('[data-review-form-toggle]').forEach((btn) => {
      ['pointerenter', 'touchstart', 'focus'].forEach((type) => btn.addEventListener(type, loadUploadScript, { once: true, passive: true }));
    });

    const setFormOpen = (open, focus = true) => {
      if (!form) return;
      form.hidden = !open;
      root.querySelectorAll('[data-review-form-toggle]').forEach((b) => b.setAttribute('aria-expanded', String(open)));
      if (!open) return;
      loadUploadScript();
      form.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'start' });
      if (focus) form.querySelector('p.stars a, select#rating, textarea')?.focus({ preventScroll: true });
    };

    root.addEventListener('click', async (e) => {
      if (e.target.closest('[data-review-form-toggle]')) {
        setFormOpen(form?.hidden ?? false);
        return;
      }
      const more = e.target.closest('[data-reviews-more]');
      if (!more || !list) return;
      more.disabled = true;
      more.classList.add('is-loading');
      const page = Number(root.dataset.page || 1) + 1;
      try {
        const res = await fetch(`${cfg.moreUrl}${root.dataset.product}?page=${page}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));
        const data = await res.json();
        list.insertAdjacentHTML('beforeend', data.html); // markup from our own endpoint
        root.dataset.page = String(page);
        if (!data.hasMore) more.closest('.reviews__more')?.remove();
      } catch {
        more.insertAdjacentHTML('afterend', `<p class="form-error" role="alert">${cfg.error || ''}</p>`);
      } finally {
        more.disabled = false;
        more.classList.remove('is-loading');
      }
    });

    // Arriving from a review request link (#review_form / #respond) opens the form.
    if (['#review-form', '#review_form', '#respond'].includes(window.location.hash)) {
      setFormOpen(true, false);
    }

    // Customer photo lightbox (photo strip, card photos, extra thumbnails).
    const modal = document.querySelector('dialog[data-modal="review-photo"]');
    if (!modal) return;
    const img = modal.querySelector('[data-review-photo-img]');
    const reviewBox = modal.querySelector('[data-review-photo-review]');
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-review-photo]');
      if (!trigger) return;
      img.src = trigger.dataset.reviewPhoto;
      const body = document.querySelector(`#review-${CSS.escape(trigger.dataset.reviewId || '')} [data-review-body]`);
      reviewBox.replaceChildren(...(body ? [body.cloneNode(true)] : []));
      modal.showModal();
      document.documentElement.classList.add('has-drawer');
    });
  }

  /* Size guide: highlight the row of the selected size (first column = the size the buttons show). */
  function initSizeGuide() {
    document.addEventListener('ci:modal:open', (e) => {
      const modal = e.target;
      if (modal.dataset.modal !== 'size-guide') return;
      const scope = e.detail?.opener?.closest('[data-product-scope], form') || document;
      const checked = scope.querySelector('[data-size-group] input:checked') ||
        document.querySelector('[data-size-group] input:checked');
      const size = (checked?.labels[0]?.textContent || '').trim().toLowerCase();
      let match = null;
      modal.querySelectorAll('tbody tr').forEach((row) => {
        const first = (row.cells[0]?.textContent || '').trim().toLowerCase();
        const on = size !== '' && first === size && !match;
        if (on) match = row;
        row.classList.toggle('is-selected', on);
      });
      const note = modal.querySelector('[data-size-guide-current]');
      if (note) {
        note.hidden = !match;
        if (match) note.querySelector('strong').textContent = checked.labels[0].textContent.trim();
      }
    });
  }

  /* Quantity: − / + buttons around WooCommerce's number input (the input stays the form field). */
  function initQuantity(form) {
    form.querySelectorAll('.quantity:not(.is-stepper)').forEach((box) => {
      const input = box.querySelector('input.qty');
      if (!input || input.type === 'hidden') return;
      box.classList.add('is-stepper');
      const make = (dir, label) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `qty-step qty-step--${dir > 0 ? 'up' : 'down'}`;
        b.setAttribute('aria-label', label);
        b.textContent = dir > 0 ? '+' : '−';
        b.addEventListener('click', () => {
          const step = Number(input.step) || 1;
          const min = input.min === '' ? 1 : Number(input.min);
          const max = input.max === '' ? Infinity : Number(input.max);
          const next = Math.min(max, Math.max(min, (Number(input.value) || min) + dir * step));
          if (String(next) === input.value) return;
          input.value = next;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        return b;
      };
      box.prepend(make(-1, box.dataset.labelDown || 'Decrease quantity'));
      box.append(make(1, box.dataset.labelUp || 'Increase quantity'));
    });
  }

  /* Gallery extras: badge + "1 / N" counter + "Hover to zoom" label inside WC's gallery (flexslider). */
  function initGalleryExtras() {
    const gallery = document.querySelector('.single-product .woocommerce-product-gallery');
    const extras = document.querySelector('[data-gallery-extras]');
    if (!gallery || !extras) return;
    gallery.append(extras);
    extras.classList.add('is-ready');
    const counter = extras.querySelector('[data-gallery-counter]');
    const total = counter ? counter.textContent.split('/')[1].trim() : '';
    const sync = () => {
      const slides = [...gallery.querySelectorAll('.woocommerce-product-gallery__image')];
      const i = slides.findIndex((s) => s.classList.contains('flex-active-slide'));
      const text = `${Math.max(0, i) + 1} / ${total}`;
      if (counter && counter.textContent !== text) counter.textContent = text; // only write on change (observer)
      const trigger = gallery.querySelector('.woocommerce-product-gallery__trigger');
      if (trigger && !trigger.dataset.label) trigger.dataset.label = extras.dataset.zoomLabel;
    };
    new MutationObserver(sync).observe(gallery, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
    sync();
  }

  /* Recently viewed: product IDs in localStorage (newest first, max 12); cards from REST when the section nears. */
  function initRecentlyViewed() {
    const section = document.querySelector('[data-recent]');
    if (!section) return;
    const KEY = 'ci_recent';
    const id = Number(section.dataset.productId);
    let ids = [];
    try {
      ids = JSON.parse(localStorage.getItem(KEY) || '[]').filter((n) => Number.isInteger(n) && n > 0);
      localStorage.setItem(KEY, JSON.stringify([id, ...ids.filter((n) => n !== id)].slice(0, 12)));
    } catch {
      return; // private mode / blocked storage: no section
    }
    const others = ids.filter((n) => n !== id).slice(0, 6);
    if (!others.length) return;
    const load = async () => {
      try {
        const res = await fetch(`${section.dataset.recentUrl}?ids=${others.join(',')}`, { credentials: 'same-origin' });
        const data = res.ok ? await res.json() : null;
        if (!data?.html) return;
        section.querySelector('[data-recent-list]').innerHTML = data.html; // markup from our own endpoint
        section.hidden = false;
      } catch {
        /* offline: keep hidden */
      }
    };
    if (!('IntersectionObserver' in window)) return load();
    const io = new IntersectionObserver((entries) => {
      if (!entries.some((e) => e.isIntersecting)) return;
      io.disconnect();
      load();
    }, { rootMargin: '600px 0px' });
    section.hidden = false; // must be laid out to be observed; empty until loaded
    io.observe(section);
  }

  ns.initVariantForm = initVariantForm;
  document.querySelectorAll('form.variations_form').forEach(initVariantForm);
  initGalleryExtras();
  initRecentlyViewed();
  document.querySelectorAll('.single-product form.cart:not(.variations_form)').forEach(initQuantity); // simple products
  initSticky();
  initReviews();
  initSizeGuide();
})();
