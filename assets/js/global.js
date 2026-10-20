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

  // Back button restores the page from the bfcache as it was left: never with a drawer open
  window.addEventListener('pageshow', (e) => {
    if (e.persisted) document.querySelectorAll('dialog[data-drawer][open]').forEach((d) => closeDrawer(d, true));
  });

  window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
    if (e.matches) closeDrawer('menu', true); // full menu shown from 1024px (header.css)
  });

  /* ---------- Mobile menu: drill-down panels ---------- */
  // Every sub-menu is moved out to a flat panel next to the root panel and slides in over it; its parent link
  // becomes a button, and its URL a "View all" link at the top of the panel (none for "#" items).

  const chevron = (d) =>
    `<svg class="icon" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path d="${d}"/></svg>`;
  const mobileNav = document.querySelector('.mobile-nav');
  if (mobileNav) {
    const rootPanel = mobileNav.querySelector('.mobile-nav__root');
    const stack = [rootPanel];
    const panels = [rootPanel];
    const sync = () => {
      const current = stack[stack.length - 1];
      panels.forEach((panel) => {
        panel.classList.toggle('is-active', stack.includes(panel));
        panel.inert = panel !== current;
      });
    };
    const show = (panel) => {
      panel.scrollTop = 0;
      stack.push(panel);
      sync();
      panel.querySelector('.mobile-nav__back').focus({ preventScroll: true });
    };
    const back = () => {
      const panel = stack.pop();
      sync();
      mobileNav.querySelector(`[aria-controls="${panel.id}"]`)?.focus({ preventScroll: true });
    };

    mobileNav.querySelectorAll('.menu-item-has-children').forEach((item, i) => {
      const link = item.querySelector(':scope > a');
      const sub = item.querySelector(':scope > .sub-menu');
      if (!link || !sub) return;
      sub.id = sub.id || `mobile-sub-${i}`;
      const title = link.textContent.trim();

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mobile-nav__next';
      btn.setAttribute('aria-controls', sub.id);
      btn.innerHTML = `${link.innerHTML}${chevron('M24 12l20 20-20 20')}`;
      btn.addEventListener('click', () => show(sub));

      const head = document.createElement('li');
      head.className = 'mobile-nav__head';
      head.innerHTML = `<button type="button" class="mobile-nav__back">${chevron('M40 12L20 32l20 20')}<span></span></button>`;
      head.querySelector('span').textContent = title;
      head.querySelector('button').setAttribute('aria-label', `${mobileNav.dataset.labelBack}: ${title}`);
      head.querySelector('button').addEventListener('click', back);
      const href = link.getAttribute('href');
      if (href && href !== '#') {
        const all = document.createElement('a');
        all.className = 'mobile-nav__all';
        all.href = href;
        all.textContent = mobileNav.dataset.labelAll;
        head.append(all);
      }
      sub.prepend(head);
      sub.classList.add('mobile-nav__panel');
      sub.setAttribute('aria-label', title);
      link.replaceWith(btn);
      panels.push(sub);
    });
    // Moved after the loop: nested sub-menus are found through their (still nested) parents first.
    panels.slice(1).forEach((panel) => mobileNav.append(panel));
    sync();

    mobileNav.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && stack.length > 1) {
        e.stopPropagation();
        e.preventDefault(); // keeps the drawer open: Esc goes back one level first
        back();
      }
    });
    // Search opens over the page: close the drawer first.
    mobileNav.querySelector('[data-search-open]')?.addEventListener('click', () => closeDrawer('menu', true));
    // Next time the drawer opens on the main level.
    document.addEventListener('drawer:close', (e) => {
      if (e.target.dataset.drawer !== 'menu') return;
      stack.length = 1;
      sync();
    });
  }

  /* ---------- Desktop menu: dropdowns + mega panel ---------- */
  // Opens on hover (closes after a short delay, so brushing past doesn't flicker), keyboard focus and tap
  // (first tap on a touch screen opens, second follows the link). Esc closes and returns focus.

  const navItems = [...document.querySelectorAll('.site-nav__list > .menu-item-has-children')];
  let lastPointer = 'mouse';
  const setNavOpen = (item, open) => {
    item.classList.toggle('is-open', open);
    if (!open) delete item.dataset.tapped;
    item.querySelector(':scope > a').setAttribute('aria-expanded', String(open));
  };
  navItems.forEach((item) => {
    const link = item.querySelector(':scope > a');
    let timer = null;
    link.setAttribute('aria-expanded', 'false');
    const open = () => {
      clearTimeout(timer);
      navItems.forEach((other) => other !== item && setNavOpen(other, false));
      setNavOpen(item, true);
    };
    item.addEventListener('pointerenter', (e) => e.pointerType === 'mouse' && open());
    item.addEventListener('pointerleave', (e) => {
      if (e.pointerType === 'mouse') timer = setTimeout(() => setNavOpen(item, false), 200);
    });
    item.addEventListener('focusin', open);
    item.addEventListener('focusout', (e) => {
      if (!item.contains(e.relatedTarget)) setNavOpen(item, false);
    });
    item.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape' || !item.classList.contains('is-open')) return;
      setNavOpen(item, false);
      link.focus();
    });
    link.addEventListener('pointerdown', (e) => (lastPointer = e.pointerType));
    link.addEventListener('keydown', () => (lastPointer = 'mouse')); // Enter follows the link
    link.addEventListener('click', (e) => {
      if (lastPointer === 'mouse' || item.dataset.tapped) return;
      e.preventDefault();
      item.dataset.tapped = '1';
      open();
    });
  });
  document.addEventListener('pointerdown', (e) => {
    navItems.forEach((item) => {
      if (item.contains(e.target)) return;
      setNavOpen(item, false);
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
