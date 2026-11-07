/* Menú de la cabecera en móvil: un botón abre/cierra la navegación principal.
   Sin JS la navegación queda visible en una segunda fila (mejora progresiva). */
(() => {
  'use strict';
  const header = document.querySelector('.header');
  const toggle = document.getElementById('nav-toggle');
  const nav = document.getElementById('site-nav');
  if (!header || !toggle || !nav) return;
  const mobile = matchMedia('(max-width: 700px)');
  const set = (open, focusToggle) => {
    header.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Menú');
    if (focusToggle) toggle.focus();
  };
  toggle.addEventListener('click', () => set(toggle.getAttribute('aria-expanded') !== 'true'));
  nav.addEventListener('click', event => { if (event.target.closest('a')) set(false); });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && header.classList.contains('nav-open')) set(false, true);
  });
  document.addEventListener('pointerdown', event => {
    if (header.classList.contains('nav-open') && !header.contains(event.target)) set(false);
  });
  mobile.addEventListener('change', () => set(false));
})();
