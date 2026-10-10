/**
 * Product page: variation buttons ↔ WooCommerce's (hidden) selects, price/stock sync, sticky add to cart.
 * Vanilla JS. WooCommerce's own variation script keeps doing its job (variation_id, images, AJAX lookups);
 * we only set the selects and fire native `change` events, which its listeners receive.
 */
(() => {
  const priceEl = document.querySelector('[data-product-price]');
  const stockEl = document.querySelector('[data-product-stock]');
  const sticky = document.querySelector('[data-sticky-atc]');
  const stickyPrice = sticky?.querySelector('[data-sticky-price]');
  const basePrice = priceEl?.innerHTML ?? '';

  const setPrice = (html) => {
    if (priceEl && priceEl.innerHTML !== html) priceEl.innerHTML = html;
    if (stickyPrice && stickyPrice.innerHTML !== html) stickyPrice.innerHTML = html;
  };

  const setStock = (state, qty) => {
    if (!stockEl) return;
    stockEl.classList.remove('product-stock--ok', 'product-stock--low', 'product-stock--out');
    stockEl.classList.add(`product-stock--${state}`);
    const label = stockEl.dataset[`label${state[0].toUpperCase()}${state.slice(1)}`] || '';
    stockEl.querySelector('.product-stock__text').textContent = label.replace('%d', qty ?? '');
  };

  /* ---------- Variation buttons ---------- */

  const form = document.querySelector('form.variations_form');
  const dataEl = form?.querySelector('.ci-variant-data');

  if (form && dataEl) {
    const { attrs, variations, prices, threshold } = JSON.parse(dataEl.textContent);
    const select = (name) => form.querySelector(`select[name="${CSS.escape(name)}"]`);
    const group = (name) => form.querySelector(`[data-variant-group="${CSS.escape(name)}"]`);
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

    const render = () => {
      const sel = current();
      attrs.forEach((name, i) => {
        group(name)?.querySelectorAll('input').forEach((input) => {
          input.disabled = !available(i, input.value, sel);
          input.checked = sel[name] === input.value;
        });
      });

      const match = attrs.every((n) => sel[n]) ? variations.find((v) => fits(v, sel)) : null;
      if (match) {
        setPrice(prices[match.p]);
        const low = match.q !== null && match.q <= threshold;
        setStock(match.s ? (low ? 'low' : 'ok') : 'out', match.q);
      } else {
        setPrice(basePrice);
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

    render();
  }

  /* ---------- Sticky add to cart (mobile) ---------- */

  const mainButton = document.querySelector('form.cart .single_add_to_cart_button');
  if (sticky && mainButton) {
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
        (form || mainButton).scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'center' });
        return;
      }
      mainButton.click(); // Same path as the main button (validation, AJAX cart, pixel events)
    });
  }
})();
