(() => {
  'use strict';
  const root = document.documentElement;
  root.classList.add('js');
  document.querySelectorAll('.brand-mark').forEach(mark => {
    mark.classList.add('brand-loading');
    const finish = () => mark.classList.remove('brand-loading');
    mark.addEventListener('animationend', finish, {once:true});
    // Reduced motion and a paused page need no animationend to settle the check.
    setTimeout(finish, 800);
  });
  const button = document.getElementById('theme-toggle');
  // Shared motion controls also work on the standalone comparison pages.
  const pause = document.getElementById('pause');
  if (pause && !document.querySelector('script[src$="app.js"]')) pause.addEventListener('click', () => {
    const paused = root.classList.toggle('paused');
    pause.setAttribute('aria-pressed', String(paused));
    pause.setAttribute('aria-label', paused ? 'Reanudar animaciones' : 'Pausar animaciones');
  });
  document.querySelectorAll('[data-motion],.dashboard-window,.product-demo').forEach(element => {
    if ('IntersectionObserver' in window) {
      element.classList.add('motion-offscreen');
      new IntersectionObserver(entries => entries.forEach(entry => {
        (entry.target || element).classList.toggle('motion-offscreen', !entry.isIntersecting);
      }), {threshold:0}).observe(element);
    }
  });
  document.addEventListener('visibilitychange', () => root.classList.toggle('page-hidden', document.hidden));
  if (!button) return;
  const modes = ['dark', 'light'];
  const labels = {light:'Claro',dark:'Oscuro'};
  document.querySelectorAll('.icon-button').forEach(control => {
    const showHelp = () => root.classList.remove('tooltips-dismissed');
    control.addEventListener('mouseenter', showHelp);
    control.addEventListener('focus', showHelp);
  });
  addEventListener('keydown', event => {
    if (event.key === 'Escape') root.classList.add('tooltips-dismissed');
  });
  function describe() {
    const theme = root.dataset.theme || 'dark';
    const next = modes[(modes.indexOf(theme)+1)%modes.length];
    document.getElementById('theme-label').textContent = labels[theme];
    button.setAttribute('aria-label', `Tema: ${labels[theme].toLowerCase()}. Cambiar a ${labels[next].toLowerCase()}.`);
    const meta = document.querySelector('meta[name=theme-color]');
    if (meta) meta.setAttribute('content', getComputedStyle(root).getPropertyValue('--bg').trim());
  }
  button.addEventListener('click', () => {
    const next = modes[(modes.indexOf(root.dataset.theme)+1)%modes.length];
    root.dataset.theme = next;
    try { localStorage.setItem('sishow-theme', next); } catch {}
    describe();
  });
  addEventListener('storage', event => {
    if (event.key === 'sishow-theme') {
      root.dataset.theme = modes.includes(event.newValue) ? event.newValue : 'dark';
      describe();
    }
  });
  describe();
  root.classList.add('theme-ready');
})();
