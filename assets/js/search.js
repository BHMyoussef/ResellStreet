/**
 * Header search overlay with predictive product results. Vanilla JS.
 * Without JS the header icon is a plain link to the product search results page.
 */
(() => {
  const cfg = window.ciSearch;
  const dialog = document.querySelector('[data-search-dialog]');
  if (!cfg || !dialog) return;

  const root = document.documentElement;
  const input = dialog.querySelector('input[name="s"]');
  const results = dialog.querySelector('[data-search-results]');
  const status = dialog.querySelector('[data-search-status]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const cache = new Map();
  let controller = null;
  let timer = null;
  let active = -1;
  let current = '';

  const text = (tpl, value) => String(tpl || '').replace('%s', value).replace('%d', value);

  /* ---------- Open / close ---------- */

  function open() {
    if (dialog.open) return;
    dialog.showModal();
    root.classList.add('has-drawer');
    requestAnimationFrame(() => requestAnimationFrame(() => dialog.classList.add('is-open')));
    input.focus();
    input.select();
    if (input.value.trim().length >= 2) search(input.value);
  }

  function close() {
    if (!dialog.open || dialog.classList.contains('is-closing')) return;
    dialog.classList.remove('is-open');
    if (reducedMotion.matches) return dialog.close();
    dialog.classList.add('is-closing');
    setTimeout(() => {
      dialog.classList.remove('is-closing');
      dialog.close();
    }, 250);
  }

  dialog.addEventListener('cancel', (e) => {
    e.preventDefault(); // animate out instead of closing instantly
    close();
  });
  dialog.addEventListener('close', () => {
    if (!document.querySelector('dialog[open]')) root.classList.remove('has-drawer');
  });

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-search-open]')) {
      e.preventDefault();
      open();
      return;
    }
    if (e.target.closest('[data-search-close]') || e.target === dialog) close(); // ✕ or backdrop
  });

  /* ---------- Predictive results ---------- */

  const options = () => [...results.querySelectorAll('a')];

  function setActive(index) {
    const links = options();
    links.forEach((a, i) => a.classList.toggle('is-active', i === index));
    active = index;
    if (index >= 0 && links[index]) {
      input.setAttribute('aria-activedescendant', links[index].id);
      links[index].scrollIntoView({ block: 'nearest' });
    } else {
      input.removeAttribute('aria-activedescendant');
    }
  }

  function clear() {
    results.replaceChildren();
    results.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    setActive(-1);
  }

  function render(query, data) {
    const wrap = document.createElement('div');
    wrap.className = 'search-panel';

    if (!data.items.length) {
      const empty = document.createElement('p');
      empty.className = 'search-panel__empty';
      empty.textContent = text(cfg.none, query);
      wrap.append(empty);
      status.textContent = empty.textContent;
    } else {
      const title = document.createElement('p');
      title.className = 'search-panel__title';
      title.textContent = cfg.products;
      const list = document.createElement('ul');
      list.className = 'search-panel__list';
      list.setAttribute('role', 'listbox');
      list.setAttribute('aria-label', cfg.products);
      data.items.forEach((item, i) => {
        const li = document.createElement('li');
        li.setAttribute('role', 'presentation');
        const a = document.createElement('a');
        a.className = 'search-result';
        a.id = `search-result-${i}`;
        a.href = item.url;
        a.setAttribute('role', 'option');
        const img = document.createElement('img');
        img.src = item.image;
        img.alt = '';
        img.width = 64;
        img.height = 64;
        img.loading = 'lazy';
        const body = document.createElement('span');
        body.className = 'search-result__body';
        const name = document.createElement('span');
        name.className = 'search-result__name';
        name.textContent = item.name;
        const price = document.createElement('span');
        price.className = 'search-result__price';
        price.innerHTML = item.price; // built server-side by ci_price_html()
        body.append(name, price);
        a.append(img, body);
        li.append(a);
        list.append(li);
      });
      const all = document.createElement('a');
      all.className = 'search-panel__all';
      all.id = 'search-result-all';
      all.href = data.url;
      all.setAttribute('role', 'option');
      all.textContent = text(cfg.viewAll, query);
      wrap.append(title, list, all);
      status.textContent = text(data.total === 1 ? cfg.countOne : cfg.count, data.total);
    }

    results.replaceChildren(wrap);
    results.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    setActive(-1);
  }

  async function search(raw) {
    const query = raw.trim();
    current = query;
    if (query.length < 2) {
      controller?.abort();
      clear();
      return;
    }
    if (cache.has(query)) {
      render(query, cache.get(query));
      return;
    }
    controller?.abort();
    controller = new AbortController();
    dialog.classList.add('is-loading');
    try {
      const res = await fetch(`${cfg.endpoint}?q=${encodeURIComponent(query)}`, { signal: controller.signal, credentials: 'same-origin' });
      if (!res.ok) throw new Error(String(res.status));
      const data = await res.json();
      cache.set(query, data);
      if (query === current) render(query, data); // ignore answers to older keystrokes
    } catch (err) {
      if (err.name === 'AbortError') return;
      const msg = document.createElement('p');
      msg.className = 'search-panel__empty';
      msg.textContent = cfg.error;
      results.replaceChildren(msg);
      results.hidden = false;
    } finally {
      dialog.classList.remove('is-loading');
    }
  }

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => search(input.value), 220);
  });

  input.addEventListener('keydown', (e) => {
    const links = options();
    if (e.key === 'ArrowDown' && links.length) {
      e.preventDefault();
      setActive((active + 1) % links.length);
    } else if (e.key === 'ArrowUp' && links.length) {
      e.preventDefault();
      setActive(active <= 0 ? links.length - 1 : active - 1);
    } else if (e.key === 'Enter' && active >= 0 && links[active]) {
      e.preventDefault();
      window.location.href = links[active].href;
    }
  });
})();
