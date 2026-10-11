/**
 * My account (inc/account.php, account.css). Vanilla, no jQuery.
 * - Sign in / Create account tabs on the login page (without JS both forms are stacked).
 * - Phones: the active menu chip is scrolled into view.
 * - Account details: the password hint goes under the "Change password" heading.
 */
(() => {
  /* ---------- Sign in / Create account tabs ---------- */
  const login = document.getElementById('customer_login');
  const panels = login ? [...login.children].filter((el) => el.querySelector('form')) : [];
  if (panels.length === 2) {
    const tablist = document.createElement('div');
    tablist.className = 'account-tabs';
    tablist.setAttribute('role', 'tablist');

    const tabs = panels.map((panel, i) => {
      const heading = panel.querySelector('h2');
      const tab = document.createElement('button');
      tab.type = 'button';
      tab.className = 'account-tabs__tab';
      tab.id = `ci-account-tab-${i}`;
      tab.textContent = heading ? heading.textContent.trim() : '';
      tab.setAttribute('role', 'tab');
      tab.setAttribute('aria-controls', panel.id || (panel.id = `ci-account-panel-${i}`));
      panel.setAttribute('role', 'tabpanel');
      panel.setAttribute('aria-labelledby', tab.id);
      tablist.append(tab);
      return tab;
    });

    const select = (index, focus = false) => {
      tabs.forEach((tab, i) => {
        const on = i === index;
        tab.setAttribute('aria-selected', String(on));
        tab.tabIndex = on ? 0 : -1;
        panels[i].hidden = !on;
      });
      if (focus) tabs[index].focus();
    };

    tablist.addEventListener('click', (e) => {
      const i = tabs.indexOf(e.target.closest('.account-tabs__tab'));
      if (i > -1) select(i);
    });
    tablist.addEventListener('keydown', (e) => {
      const i = tabs.indexOf(document.activeElement);
      if (i < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
      e.preventDefault();
      const last = tabs.length - 1;
      const next = { ArrowLeft: i ? i - 1 : last, ArrowRight: i < last ? i + 1 : 0, Home: 0, End: last }[e.key];
      select(next, true);
    });

    login.classList.add('has-tabs');
    login.prepend(tablist);
    // After a failed sign-up WooCommerce refills the sign-up email: reopen that tab (also #register links).
    const signup = panels[1].querySelector('input[type="email"]');
    select(location.hash === '#register' || (signup && signup.value) ? 1 : 0);
  }

  /* ---------- Menu chips (phones): keep the current page visible ---------- */
  const active = document.querySelector('.woocommerce-MyAccount-navigation .is-active a');
  const list = active?.closest('ul');
  if (list && list.scrollWidth > list.clientWidth) {
    list.scrollLeft = active.offsetLeft - (list.clientWidth - active.offsetWidth) / 2;
  }

  /* ---------- Account details: password hint inside the "Change password" section ---------- */
  const hint = document.querySelector('.account-form__hint--password');
  const legend = document.querySelector('.woocommerce-EditAccountForm fieldset legend');
  if (hint && legend) legend.after(hint);
})();
