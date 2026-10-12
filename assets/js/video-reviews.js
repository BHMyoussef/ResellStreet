/**
 * Video reviews ("Hear from our customers"), homepage + product page. Vanilla JS, deferred.
 * - Carousels ([data-carousel]): CSS scroll-snap; arrows scroll by one page and disable at the ends.
 * - Videos: poster only until tapped; then a muted <video> is created (the file loads now, not before).
 */
(() => {
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
    // No sound (store choice): muted, and kept muted if the visitor uses the volume control.
    video.muted = true;
    video.defaultMuted = true;
    video.setAttribute('muted', '');
    video.addEventListener('volumechange', () => {
      if (!video.muted) video.muted = true;
    });
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
