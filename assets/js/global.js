/**
 * Global behaviors: drawers, announcement bar, mobile menu accordions, footer collapsibles.
 * Vanilla JS, no dependencies. Loaded with `defer` on every page.
 */
(() => {
  const root = document.documentElement;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const desktop = window.matchMedia('(min-width: 769px)');

  /* ---------- Drawers (<dialog data-drawer="name">) ---------- */

  const getDrawer = (name) => document.querySelector(`dialog[data-drawer="${name}"]`);

  const setTriggersExpanded = (name, expanded) => {
    document
      .querySelectorAll(`[data-drawer-open="${name}"][aria-controls]`)
      .forEach((el) => el.setAttribute('aria-expanded', String(expanded)));
  };

  function openDrawer(drawer) {
    if (typeof drawer === 'string') drawer = getDrawer(drawer);
    if (!drawer || drawer.open) return;
    // Only one drawer at a time
    document.querySelectorAll('dialog[data-drawer][open]').forEach((d) => closeDrawer(d, true));
    drawer.showModal();
    root.classList.add('has-drawer');
    setTriggersExpanded(drawer.dataset.drawer, true);
    // Two frames so the closed transform is painted before the transition starts
    requestAnimationFrame(() => requestAnimationFrame(() => drawer.classList.add('is-open')));
    drawer.dispatchEvent(new CustomEvent('drawer:open', { bubbles: true }));
  }

  function closeDrawer(drawer, immediate = false) {
    if (typeof drawer === 'string') drawer = getDrawer(drawer);
    if (!drawer || !drawer.open || drawer.classList.contains('is-closing')) return;
    drawer.classList.remove('is-open');

    const finish = () => {
      drawer.classList.remove('is-closing');
      drawer.close();
    };
    if (immediate || reducedMotion.matches) return finish();

    drawer.classList.add('is-closing');
    const onEnd = (e) => {
      if (e.target !== drawer || e.propertyName !== 'transform') return;
      drawer.removeEventListener('transitionend', onEnd);
      clearTimeout(fallback);
      finish();
    };
    const fallback = setTimeout(() => {
      drawer.removeEventListener('transitionend', onEnd);
      finish();
    }, 700);
    drawer.addEventListener('transitionend', onEnd);
  }

  document.querySelectorAll('dialog[data-drawer]').forEach((drawer) => {
    // Esc: animate out instead of the instant native close
    drawer.addEventListener('cancel', (e) => {
      e.preventDefault();
      closeDrawer(drawer);
    });
    drawer.addEventListener('close', () => {
      if (!document.querySelector('dialog[data-drawer][open]')) root.classList.remove('has-drawer');
      setTriggersExpanded(drawer.dataset.drawer, false);
      drawer.dispatchEvent(new CustomEvent('drawer:close', { bubbles: true }));
    });
  });

  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-drawer-open]');
    if (opener) {
      const drawer = getDrawer(opener.dataset.drawerOpen);
      // No drawer on the page (e.g. cart drawer not rendered): let the link navigate
      if (!drawer) return;
      e.preventDefault();
      openDrawer(drawer);
      return;
    }
    const closer = e.target.closest('[data-drawer-close]');
    if (closer) {
      closeDrawer(closer.closest('dialog'));
      return;
    }
    // A click on the ::backdrop targets the dialog element itself
    if (e.target.matches('dialog[data-drawer]')) closeDrawer(e.target);

    /* Modals (size guide, …): <dialog data-modal="name"> */
    const modalOpener = e.target.closest('[data-modal-open]');
    if (modalOpener) {
      const modal = document.querySelector(`dialog[data-modal="${modalOpener.dataset.modalOpen}"]`);
      if (modal) {
        e.preventDefault();
        modal.dispatchEvent(new CustomEvent('ci:modal:open', { bubbles: true, detail: { opener: modalOpener } }));
        modal.showModal();
        root.classList.add('has-drawer');
      }
      return;
    }
    if (e.target.closest('[data-modal-close]')) {
      e.target.closest('dialog').close();
      return;
    }
    if (e.target.matches('dialog[data-modal]')) e.target.close();
  });

  document.querySelectorAll('dialog[data-modal]').forEach((modal) => {
    modal.addEventListener('close', () => {
      if (!document.querySelector('dialog[open]')) root.classList.remove('has-drawer');
    });
  });

  desktop.addEventListener('change', (e) => {
    if (e.matches) closeDrawer('menu', true);
  });

  /* ---------- Mobile menu: submenu accordions ---------- */

  document.querySelectorAll('.mobile-nav .menu-item-has-children').forEach((item, i) => {
    const link = item.querySelector(':scope > a');
    const sub = item.querySelector(':scope > .sub-menu');
    if (!link || !sub) return;
    sub.id = sub.id || `mobile-sub-${i}`;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'mobile-nav__toggle';
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-controls', sub.id);
    btn.setAttribute('aria-label', link.textContent.trim());
    btn.innerHTML =
      '<svg class="icon" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path d="M12 24l20 20 20-20"/></svg>';
    link.after(btn);
    btn.addEventListener('click', () => {
      const open = item.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
    });
  });

  /* ---------- Announcement bar ---------- */

  document.querySelectorAll('[data-announcement]').forEach((bar) => {
    const msgs = [...bar.querySelectorAll('.announcement__msg')];
    if (msgs.length < 2) return;
    const interval = Number(bar.dataset.interval) || 5000;
    let current = msgs.findIndex((m) => m.classList.contains('is-active'));
    if (current < 0) current = 0;
    let timer = null;

    const show = (next) => {
      msgs[current].classList.remove('is-active');
      msgs[current].inert = true;
      current = (next + msgs.length) % msgs.length;
      msgs[current].classList.add('is-active');
      msgs[current].inert = false;
    };
    const stop = () => {
      clearInterval(timer);
      timer = null;
    };
    const start = () => {
      stop();
      timer = setInterval(() => show(current + 1), interval);
    };

    bar.addEventListener('mouseenter', stop);
    bar.addEventListener('mouseleave', start);
    bar.addEventListener('focusin', stop);
    bar.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    start();
  });

  /* ---------- Footer collapsibles (mobile only) ---------- */

  const collapsibles = [...document.querySelectorAll('[data-collapsible]')];
  const syncCollapsibles = () => {
    collapsibles.forEach((block) => {
      const btn = block.querySelector('[data-collapsible-toggle]');
      const open = desktop.matches;
      block.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', String(open));
      btn.tabIndex = open ? -1 : 0;
    });
  };
  collapsibles.forEach((block) => {
    const btn = block.querySelector('[data-collapsible-toggle]');
    btn.addEventListener('click', () => {
      if (desktop.matches) return;
      const open = block.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', String(open));
    });
  });
  syncCollapsibles();
  desktop.addEventListener('change', syncCollapsibles);

  /* ---------- Footer sign-up (built-in, inc/subscribers.php): submit in place ---------- */

  document.querySelectorAll('form[data-newsletter]').forEach((form) => {
    const message = form.querySelector('[data-newsletter-message]');
    const email = form.querySelector('input[type="email"]');
    const show = (text, error) => {
      message.textContent = text;
      message.classList.toggle('is-error', error);
      message.hidden = false;
    };
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (form.classList.contains('is-busy')) return;
      form.classList.add('is-busy');
      try {
        // getAttribute: the form has an input named "action" (admin-post), which shadows form.action.
        const res = await fetch(form.getAttribute('action'), {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        show(data.message, !data.ok);
        if (data.ok) {
          email.value = '';
          email.blur();
        } else {
          email.focus();
        }
      } catch {
        form.submit(); // network/JSON problem: fall back to the normal post (comes back with the result)
      } finally {
        form.classList.remove('is-busy');
      }
    });
  });

  /* Public API for other modules (cart drawer, search, quick shop) */
  window.CustomImpulse = Object.assign(window.CustomImpulse || {}, { openDrawer, closeDrawer });
})();
