/**
 * Homepage (reference/homepage-v2). Vanilla JS, deferred.
 * - Trending tabs: other categories load from REST ci/v1/trending/<cat> (cached per tab, prefetched on hover).
 * - Carousels ([data-carousel]): CSS scroll-snap; arrows scroll by one page and disable at the ends.
 * - Video reviews: poster only until tapped; then a <video> is created (the file loads now, not before).
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

  /* ---------- Carousel arrows ---------- */
  document.querySelectorAll('[data-carousel]').forEach((root) => {
    const track = root.querySelector('[data-carousel-track]');
    const prev = root.querySelector('[data-carousel-prev]');
    const next = root.querySelector('[data-carousel-next]');
    if (!track || !prev || !next) return;
    const update = () => {
      const max = track.scrollWidth - track.clientWidth - 2;
      const atStart = track.scrollLeft <= 2;
      const atEnd = track.scrollLeft >= max;
      if (prev.disabled !== atStart) prev.disabled = atStart;
      if (next.disabled !== atEnd) next.disabled = atEnd;
    };
    const step = (dir) => {
      const card = track.firstElementChild;
      const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      const width = card ? card.getBoundingClientRect().width + gap : track.clientWidth;
      const perPage = Math.max(1, Math.floor((track.clientWidth + gap) / width));
      track.scrollBy({ left: dir * perPage * width, behavior: 'smooth' });
    };
    prev.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    update();
  });

  /* ---------- Video reviews: tap to play ---------- */
  let playing = null;
  document.addEventListener('click', (e) => {
    const button = e.target.closest('.video-card__play[data-video-src]');
    if (!button) return;
    const media = button.closest('[data-video]');
    const card = button.closest('.video-card');
    const video = document.createElement('video');
    video.src = button.dataset.videoSrc;
    video.controls = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.setAttribute('aria-label', button.textContent.trim());
    const poster = media.querySelector('.video-card__poster');
    if (poster?.currentSrc) video.poster = poster.currentSrc;
    button.replaceWith(video);
    card?.classList.add('is-playing');
    if (playing && playing !== video) playing.pause();
    playing = video;
    video.addEventListener('play', () => {
      if (playing && playing !== video) playing.pause();
      playing = video;
    });
    video.play().catch(() => {}); // a tap is a user gesture; if blocked, the controls are there
    video.focus();
  });
})();
