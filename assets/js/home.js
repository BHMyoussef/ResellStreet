/**
 * Homepage: hero slideshow, desktop parallax, testimonials carousel. Vanilla JS.
 */
(() => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const desktop = window.matchMedia('(min-width: 769px)');

  /* ---------- Hero slideshow (only with 2+ slides) ---------- */

  document.querySelectorAll('[data-hero]').forEach((hero) => {
    const slides = [...hero.querySelectorAll('[data-hero-slide]')];
    if (slides.length < 2) return;
    const bars = [...hero.querySelectorAll('[data-hero-go]')];
    const delay = Number(hero.dataset.autoplay) || 0;
    let index = 0;
    let timer = null;
    let paused = false;

    hero.style.setProperty('--hero-autoplay', `${delay}ms`);

    const schedule = () => {
      clearTimeout(timer);
      if (delay && !paused && !reducedMotion.matches) timer = setTimeout(() => go(index + 1), delay);
    };

    function go(next) {
      const from = slides[index];
      from.classList.remove('is-active');
      from.inert = true;
      from.setAttribute('aria-hidden', 'true');
      bars[index]?.classList.remove('is-active');
      bars[index]?.removeAttribute('aria-current');

      index = (next + slides.length) % slides.length;
      const to = slides[index];
      to.querySelectorAll('img[loading="lazy"]').forEach((img) => (img.loading = 'eager'));
      to.classList.add('is-active');
      to.inert = false;
      to.removeAttribute('aria-hidden');
      const bar = bars[index];
      if (bar) {
        bar.classList.remove('is-active');
        void bar.offsetWidth; // restart the progress animation
        bar.classList.add('is-active');
        bar.setAttribute('aria-current', 'true');
      }
      schedule();
    }

    const pause = (state) => {
      paused = state;
      hero.classList.toggle('is-paused', state);
      schedule();
    };

    bars.forEach((bar, i) => bar.addEventListener('click', () => go(i)));
    hero.addEventListener('mouseenter', () => pause(true));
    hero.addEventListener('mouseleave', () => pause(false));
    hero.addEventListener('focusin', () => pause(true));
    hero.addEventListener('focusout', () => pause(false));
    document.addEventListener('visibilitychange', () => pause(document.hidden));

    // Swipe
    let startX = null;
    hero.addEventListener('pointerdown', (e) => (startX = e.clientX), { passive: true });
    hero.addEventListener('pointerup', (e) => {
      if (startX === null) return;
      const dx = e.clientX - startX;
      startX = null;
      if (Math.abs(dx) > 50) go(index + (dx < 0 ? 1 : -1));
    });

    // Other slides' images load after the page is done (the first one is the LCP).
    window.addEventListener('load', () => {
      slides.forEach((s) => s.querySelectorAll('img[loading="lazy"]').forEach((img) => (img.loading = 'eager')));
    });

    schedule();
  });

  /* ---------- Parallax (desktop, motion allowed) ---------- */

  const layers = [...document.querySelectorAll('.hero--parallax [data-parallax] img, .story--parallax [data-parallax] img')];
  if (layers.length) {
    let ticking = false;
    const update = () => {
      ticking = false;
      const active = desktop.matches && !reducedMotion.matches;
      const vh = window.innerHeight;
      layers.forEach((img) => {
        if (!active) {
          img.style.transform = '';
          return;
        }
        const box = img.closest('[data-parallax]').getBoundingClientRect();
        if (box.bottom < 0 || box.top > vh) return;
        // -1 (entering at the bottom) … 1 (leaving at the top) → ±60px
        const progress = (vh / 2 - (box.top + box.height / 2)) / (vh / 2 + box.height / 2);
        img.style.transform = `translate3d(0, ${(progress * 60).toFixed(1)}px, 0)`;
      });
    };
    const request = () => {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(update);
      }
    };
    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request, { passive: true });
    desktop.addEventListener('change', update);
    reducedMotion.addEventListener('change', update);
    update();
  }

  /* ---------- Testimonials carousel (scroll-snap) ---------- */

  document.querySelectorAll('[data-carousel]').forEach((viewport) => {
    const slides = [...viewport.querySelectorAll('[data-carousel-slide]')];
    const dots = [...viewport.parentElement.querySelectorAll('[data-carousel-go]')];
    if (!slides.length) return;
    let selected = -1;

    const select = (i) => {
      if (i === selected) return;
      selected = i;
      slides.forEach((s, k) => s.classList.toggle('is-selected', k === i));
      dots.forEach((d, k) => {
        d.classList.toggle('is-active', k === i);
        if (k === i) d.setAttribute('aria-current', 'true');
        else d.removeAttribute('aria-current');
      });
    };
    const centre = (s) => s.offsetLeft + s.offsetWidth / 2;
    const nearest = () => {
      const c = viewport.scrollLeft + viewport.clientWidth / 2;
      let best = 0;
      let distance = Infinity;
      slides.forEach((s, k) => {
        const d = Math.abs(centre(s) - c);
        if (d < distance) {
          distance = d;
          best = k;
        }
      });
      return best;
    };
    const scrollToSlide = (i, smooth = true) => {
      const k = Math.max(0, Math.min(slides.length - 1, i));
      viewport.scrollTo({ left: centre(slides[k]) - viewport.clientWidth / 2, behavior: smooth && !reducedMotion.matches ? 'smooth' : 'auto' });
      select(k);
    };

    let frame;
    viewport.addEventListener(
      'scroll',
      () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => select(nearest()));
      },
      { passive: true }
    );
    dots.forEach((dot, i) => dot.addEventListener('click', () => scrollToSlide(i)));
    viewport.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') scrollToSlide(selected + 1);
      else if (e.key === 'ArrowLeft') scrollToSlide(selected - 1);
      else return;
      e.preventDefault();
    });
    window.addEventListener('resize', () => scrollToSlide(selected, false), { passive: true });

    // Like the reference: the second card starts in the centre with one on each side.
    scrollToSlide(slides.length > 2 ? 1 : 0, false);
  });
})();
