/* Map an HTML rectangle to the four measured screen corners, TL/TR/BR/BL. */
(() => {
  'use strict';
  function project(width, height, corners) {
    const source = [[0, 0], [width, 0], [width, height], [0, height]];
    const rows = [];
    source.forEach(([x, y], i) => {
      const [u, v] = corners[i];
      rows.push([x, y, 1, 0, 0, 0, -u*x, -u*y, u]);
      rows.push([0, 0, 0, x, y, 1, -v*x, -v*y, v]);
    });
    for (let col = 0; col < 8; col++) {
      let pivot = col;
      for (let row = col+1; row < 8; row++) if (Math.abs(rows[row][col]) > Math.abs(rows[pivot][col])) pivot = row;
      [rows[col], rows[pivot]] = [rows[pivot], rows[col]];
      const divisor = rows[col][col];
      if (Math.abs(divisor) < 1e-10) throw new Error('Invalid tablet screen corners');
      rows[col] = rows[col].map(n => n/divisor);
      for (let row = 0; row < 8; row++) if (row !== col) {
        const factor = rows[row][col];
        rows[row] = rows[row].map((n, i) => n-factor*rows[col][i]);
      }
    }
    const [a,b,c,d,e,f,g,h] = rows.map(row => row[8]);
    return [a,d,0,g,b,e,0,h,0,0,1,0,c,f,0,1];
  }
  if (typeof module === 'object' && module.exports) { module.exports = project; return; }
  const stages = [...document.querySelectorAll('[data-screen-corners]')];
  let frame = 0;
  function fit() {
    frame = 0;
    // Read all dimensions first; write transforms afterwards.
    const measurements = stages.map(stage => {
      if (stage.closest('.hero') && matchMedia('(max-width: 1100px)').matches) return null;
      const screen = stage.querySelector('.tablet-screen');
      const scale = stage.clientWidth/1536;
      const corners = JSON.parse(stage.dataset.screenCorners).map(p => p.map(n => n*scale));
      return {screen, hardware:stage.querySelector('.tablet-hardware'), matrix:project(screen.offsetWidth, screen.offsetHeight, corners)};
    });
    measurements.filter(Boolean).forEach(({screen,hardware,matrix}) => {
      screen.style.transform = `matrix3d(${matrix.join(',')})`;
      screen.classList.add('screen-fitted');
      if (hardware) { hardware.style.transform = screen.style.transform; hardware.classList.add('screen-fitted'); }
    });
  }
  function queue() { if (!frame) frame = requestAnimationFrame(fit); }
  if ('ResizeObserver' in window) {
    const observer = new ResizeObserver(queue);
    stages.forEach(stage => observer.observe(stage));
  } else addEventListener('resize', queue);
  queue();
})();
