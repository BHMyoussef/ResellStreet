/**
 * Homepage (reference/homepage-v2). Vanilla JS, deferred.
 * - Trending tabs: other categories load from REST ci/v1/trending/<cat> (cached per tab, prefetched on hover).
 */
(() => {
  /* ---------- Trending tabs ---------- */
  document.querySelectorAll('[data-trending]').forEach((section) => {
    const tabs = [...section.querySelectorAll('[role="tab"]')];
    const panel = section.querySelector('[data-trending-panel]');
    if (tabs.length < 2 || !panel) return;
    const cache = new Map();
    const selected = tabs.find((t) => t.getAttribute('aria-selected') === 'true') || tabs[0];
    cache.set(selected.dataset.trendingCat, Promise.resolve(panel.innerHTML));
    let current = 0;

    const load = (cat) => {
      if (!cache.has(cat)) {
        const request = fetch(section.dataset.trending + cat, { credentials: 'same-origin' })
          .then((r) => (r.ok ? r.json() : Promise.reject(new Error(r.status))))
          .then((data) => data.html || '');
        request.catch(() => cache.delete(cat)); // allow a retry
        cache.set(cat, request);
      }
      return cache.get(cat);
    };

    const select = async (tab, focus) => {
      const id = ++current;
      tabs.forEach((t) => {
        const on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
      });
      if (focus) tab.focus();
      panel.setAttribute('aria-labelledby', tab.id);
      panel.setAttribute('aria-busy', 'true');
      try {
        const html = await load(tab.dataset.trendingCat);
        if (id === current) panel.innerHTML = html; // a later click wins
      } catch {
        if (id === current) panel.innerHTML = '';
      } finally {
        if (id === current) panel.removeAttribute('aria-busy');
      }
    };

    tabs.forEach((tab, i) => {
      tab.addEventListener('click', () => select(tab, false));
      tab.addEventListener('pointerenter', () => load(tab.dataset.trendingCat).catch(() => {}), { once: true });
      tab.addEventListener('keydown', (e) => {
        const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
        if (next === undefined) return;
        e.preventDefault();
        select(tabs[(next + tabs.length) % tabs.length], true);
      });
    });
  });
})();
