/* siShow · Google Analytics 4 con Consent Mode v2 y aviso de cookies.
   - Todo el consentimiento nace denegado; solo analytics_storage puede pasar a concedido.
   - Los permisos de publicidad quedan SIEMPRE denegados (no hacemos publicidad).
   - La elección se guarda en localStorage (clave «sishow-cookies»). */
(function () {
  'use strict';

  var GA_ID = 'G-GPSVQRGYMT';
  var KEY = 'sishow-cookies';
  var ACCEPTED = 'aceptadas';
  var REJECTED = 'rechazadas';

  /* 1. dataLayer + gtag, y el consentimiento por defecto ANTES de cargar nada. */
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = window.gtag || gtag;

  gtag('consent', 'default', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    wait_for_update: 500
  });

  /* 2. Lectura / escritura de la elección (el almacenamiento puede no estar disponible). */
  function readChoice() {
    try {
      var v = window.localStorage.getItem(KEY);
      return v === ACCEPTED || v === REJECTED ? v : null;
    } catch (e) { return null; }
  }
  function saveChoice(v) {
    try { window.localStorage.setItem(KEY, v); } catch (e) { /* sin almacenamiento: se pregunta de nuevo */ }
  }

  function applyConsent(choice) {
    gtag('consent', 'update', {
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: choice === ACCEPTED ? 'granted' : 'denied'
    });
  }

  /* Borra las cookies de Google Analytics del dominio actual y de sishow.es. */
  function deleteGaCookies() {
    var host = window.location.hostname;
    var domains = [null, host, '.' + host, 'sishow.es', '.sishow.es'];
    var names = document.cookie.split(';').map(function (c) { return c.split('=')[0].trim(); })
      .filter(function (n) { return n.indexOf('_ga') === 0 || n === '_gid'; });
    names.forEach(function (name) {
      domains.forEach(function (d) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (d ? '; domain=' + d : '');
      });
    });
  }

  /* 3. Consentimiento previo (si existe) antes de configurar GA, para que la primera visita de página ya lo respete. */
  var stored = readChoice();
  if (stored) applyConsent(stored);

  /* 4. Carga de gtag.js y configuración. */
  gtag('js', new Date());
  var tag = document.createElement('script');
  tag.async = true;
  tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
  document.head.appendChild(tag);
  gtag('config', GA_ID, { allow_google_signals: false, allow_ad_personalization_signals: false });

  /* 5. Aviso de cookies. */
  var banner = null;
  var lastFocus = null;

  function setOffset(on) {
    var root = document.documentElement;
    if (on && banner) {
      root.style.setProperty('--sishow-cookies-h', banner.offsetHeight + 'px');
      root.classList.add('sishow-cookies-open');
    } else {
      root.classList.remove('sishow-cookies-open');
      root.style.removeProperty('--sishow-cookies-h');
    }
  }

  function hideBanner(restoreFocus) {
    if (!banner) return;
    banner.hidden = true;
    setOffset(false);
    if (restoreFocus && lastFocus && document.contains(lastFocus)) {
      try { lastFocus.focus(); } catch (e) { /* nada */ }
    }
    lastFocus = null;
  }

  function choose(choice) {
    saveChoice(choice);
    applyConsent(choice);
    if (choice === REJECTED) deleteGaCookies();
    hideBanner(true);
  }

  function buildBanner() {
    var el = document.createElement('div');
    el.className = 'sishow-cookies';
    el.setAttribute('role', 'region');
    el.setAttribute('aria-label', 'Aviso de cookies');
    el.tabIndex = -1;
    el.hidden = true;
    el.innerHTML =
      '<p class="sishow-cookies-text"><strong>Cookies</strong> ' +
      'Usamos cookies de analítica (Google Analytics) solo si las aceptas, para saber cómo se usa la web. ' +
      'Puedes cambiar de opinión cuando quieras. ' +
      '<a href="cookies.html">Más información</a>.</p>' +
      '<div class="sishow-cookies-actions">' +
      '<button type="button" class="sishow-cookies-btn" data-choice="' + ACCEPTED + '">Aceptar</button>' +
      '<button type="button" class="sishow-cookies-btn" data-choice="' + REJECTED + '">Rechazar</button>' +
      '</div>';
    el.addEventListener('click', function (ev) {
      var b = ev.target.closest ? ev.target.closest('button[data-choice]') : null;
      if (b) choose(b.getAttribute('data-choice'));
    });
    el.addEventListener('keydown', function (ev) {
      /* Con una elección ya guardada, Escape cierra el aviso sin cambiarla. */
      if (ev.key === 'Escape' && readChoice()) hideBanner(true);
    });
    document.body.insertBefore(el, document.body.firstChild);
    return el;
  }

  function showBanner(userInitiated) {
    if (!banner) banner = buildBanner();
    if (userInitiated) lastFocus = document.activeElement;
    banner.hidden = false;
    setOffset(true);
    if (userInitiated) {
      try { banner.focus(); } catch (e) { /* nada */ }
    }
  }

  window.sishowAbrirCookies = function () { showBanner(true); };

  function init() {
    if (!readChoice()) showBanner(false);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
