const navToggle = document.querySelector('.nav-toggle');
const navMenu = document.querySelector('#site-menu');
if (navToggle && navMenu) {
  navToggle.addEventListener('click', () => {
    const expanded = navToggle.getAttribute('aria-expanded') === 'true';
    navToggle.setAttribute('aria-expanded', String(!expanded));
    navMenu.classList.toggle('is-open', !expanded);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || navToggle.getAttribute('aria-expanded') !== 'true') return;
    navToggle.setAttribute('aria-expanded', 'false');
    navMenu.classList.remove('is-open');
    navToggle.focus({ preventScroll: true });
  });
}

(() => {
  const params = new URLSearchParams(window.location.search);
  const isAdminMode = params.get('bobi_admin') === '1';
  const endpoint = document.body.dataset.visitEndpoint || '/api/visit-signal.php';

  const formatNumber = (value) => Number(value || 0).toLocaleString('en-IN');
  const setStat = (name, value) => {
    const el = document.querySelector(`[data-bobi-stat="${name}"]`);
    if (el) el.textContent = formatNumber(value);
  };

  const loadAdminStats = () => {
    if (!isAdminMode) return;
    const panel = document.querySelector('#bobi-visit-stats');
    const jump = document.querySelector('#bobi-visit-jump');
    if (!panel) return;
    panel.hidden = false;
    panel.classList.add('is-visible');
    if (jump) jump.classList.add('is-visible');

    const statsKey = window.prompt('Administrator access key') || '';
    if (!statsKey) return;
    fetch(`${endpoint}?mode=stats&v=20260510a`, {
      headers: {'X-Visit-Admin-Key': statsKey},
      cache: 'no-store',
      credentials: 'same-origin',
    }).then((response) => response.json()).then((data) => {
      if (!data || !data.ok || !data.stats) return;
      const stats = data.stats;
      setStat('today_page_views', stats.today?.page_views);
      setStat('today_unique_visitors', stats.today?.unique_visitors);
      setStat('today_promo_shown', stats.today?.promo_shown);
      setStat('total_page_views', stats.total?.page_views);
      setStat('total_daily_unique_visitors', stats.total?.daily_unique_visitors);
      setStat('total_promo_shown', stats.total?.promo_shown);

      const pages = stats.top_pages || {};
      const rows = Object.keys(pages).slice(0, 5).map((path) => (
        `${path} (${formatNumber(pages[path])})`
      ));
      const pageEl = document.querySelector('[data-bobi-stat-pages]');
      if (pageEl) {
        pageEl.textContent = rows.length ? `Top pages: ${rows.join(' · ')}` : 'Top pages: no data yet';
      }
    }).catch(() => {});

    window.setTimeout(() => {
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 350);
  };

  if (isAdminMode) {
    localStorage.setItem('bobiAdminOptout', '1');
    loadAdminStats();
  }
  if (params.get('bobi_admin') === '0') {
    localStorage.removeItem('bobiAdminOptout');
  }
  if (localStorage.getItem('bobiAdminOptout') === '1') {
    return;
  }

  const body = document.body;
  const registerUrl = body.dataset.registerUrl || '/register/';
  let visitorId = localStorage.getItem('bobiVisitorId');
  if (!visitorId) {
    visitorId = Math.random().toString(36).slice(2) + Date.now().toString(36);
    localStorage.setItem('bobiVisitorId', visitorId);
  }

  const sendSignal = (eventName) => {
    const payload = JSON.stringify({
      event: eventName,
      visitor_id: visitorId,
      path: window.location.pathname || '/',
      title: document.title || '',
      referrer: document.referrer || '',
    });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, new Blob([payload], { type: 'application/json' }));
      return;
    }
    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: payload,
      keepalive: true,
    }).catch(() => {});
  };

  sendSignal('page_view');

  const today = new Date().toISOString().slice(0, 10);
  const seenKey = 'bobiPromoSeenDate';
  if (localStorage.getItem(seenKey) === today) {
    return;
  }

  const showPromo = () => {
    if (document.querySelector('.promo-nudge')) {
      return;
    }
    localStorage.setItem(seenKey, today);
    const previousFocus = document.activeElement;

    const promo = document.createElement('aside');
    promo.className = 'promo-nudge';
    promo.setAttribute('role', 'dialog');
    promo.setAttribute('aria-modal', 'false');
    promo.setAttribute('aria-labelledby', 'promo-nudge-title');
    promo.setAttribute('tabindex', '-1');

    const closeButton = document.createElement('button');
    closeButton.className = 'promo-nudge__close';
    closeButton.type = 'button';
    closeButton.setAttribute('aria-label', 'Close promotion');
    closeButton.textContent = '×';

    const eyebrow = document.createElement('div');
    eyebrow.className = 'promo-nudge__eyebrow';
    eyebrow.textContent = 'GameHub register window';

    const headline = document.createElement('strong');
    headline.id = 'promo-nudge-title';
    headline.textContent = 'Ready to start with invite code 72238107987?';

    const copy = document.createElement('p');
    copy.textContent = 'Open the guided GameHub registration path before the next cricket gaming India session.';

    const cta = document.createElement('a');
    cta.className = 'promo-nudge__cta';
    cta.href = registerUrl;
    cta.target = '_blank';
    cta.rel = 'nofollow noopener';
    cta.textContent = 'Register Now';

    promo.append(closeButton, eyebrow, headline, copy, cta);

    const closePromo = () => {
      document.removeEventListener('keydown', handlePromoKeydown);
      promo.remove();
      if (previousFocus && typeof previousFocus.focus === 'function') {
        previousFocus.focus({ preventScroll: true });
      }
    };

    const handlePromoKeydown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closePromo();
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = promo.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    closeButton.addEventListener('click', closePromo);

    document.body.appendChild(promo);
    document.addEventListener('keydown', handlePromoKeydown);
    requestAnimationFrame(() => {
      promo.classList.add('is-visible');
      closeButton.focus({ preventScroll: true });
    });
    sendSignal('promo_shown');
  };

  window.setTimeout(showPromo, 8000);
})();
