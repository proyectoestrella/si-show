/* Calculadoras de siShow (páginas independientes). Sin dependencias; rutas relativas. */
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const root = document.documentElement;
  const textNode = (id, content) => { const el = $(id); if (el) el.textContent = content; };
  const number = new Intl.NumberFormat('es-ES', {maximumFractionDigits:1});
  const euros = new Intl.NumberFormat('es-ES', {style:'currency', currency:'EUR', minimumFractionDigits:0, maximumFractionDigits:0, useGrouping:'always'});
  const money = new Intl.NumberFormat('es-ES', {maximumFractionDigits:0, useGrouping:'always'});
  const counterJobs = new Map();
  function settleCounters() {
    counterJobs.forEach((job, el) => { cancelAnimationFrame(job.frame); el.textContent = job.format(job.to); });
    counterJobs.clear();
  }
  function count(id, to, format, animate) {
    const el = $(id); if (!el) return;
    const old = counterJobs.get(el), from = old?.current ?? Number(el.dataset.amount ?? to);
    if (old) cancelAnimationFrame(old.frame);
    counterJobs.delete(el); el.dataset.amount = to;
    if (!animate || root.classList.contains('paused') || reduced.matches || from === to) { el.textContent = format(to); return; }
    const job = {to, format, current:from, frame:0}, start = performance.now(); counterJobs.set(el, job);
    function tick(now) {
      const t = Math.min(1, Math.max(0, (now - start) / 380)); job.current = from + (to - from) * (1 - (1 - t) ** 3); el.textContent = format(job.current);
      if (t < 1) job.frame = requestAnimationFrame(tick); else { el.textContent = format(to); counterJobs.delete(el); }
    }
    job.frame = requestAnimationFrame(tick);
  }
  let announceTimer;
  function value(id) { const el = $(id); return Math.max(Number(el.min), Math.min(Number(el.max), Number(el.value) || 0)); }
  const has = (id) => !!$(id);
  function calculate(animate = false) {
    if (has('appointments')) {
      const daily = value('appointments') * value('minutes'), hours = daily * value('days') / 60;
      count('time-output', hours, v => number.format(v), animate);
      count('workdays-output', hours / 8, v => `${number.format(v)} jornadas`, animate);
      textNode('time-daily-output', `${number.format(daily)} min`); textNode('time-days-output', `${number.format(value('days'))} días`);
      if (animate) announce(`${number.format(hours)} horas al mes; ${number.format(hours / 8)} jornadas.`);
    }
    if (has('bookings')) {
      const revenue = value('bookings') * value('ticket'), commission = revenue * value('rate') / 100, fixed = value('fee') + value('other'), total = commission + fixed;
      count('money-output', commission, v => money.format(v), animate);
      count('annual-output', commission * 12, v => euros.format(v), animate);
      textNode('commission-output', euros.format(commission)); textNode('fixed-output', euros.format(fixed)); textNode('total-output', euros.format(total));
      if (animate) announce(`Comisiones: ${euros.format(commission)} al mes, ${euros.format(commission * 12)} al año. Cuota y extras: ${euros.format(fixed)} al mes. Total: ${euros.format(total)} al mes.`);
    }
    document.querySelectorAll('[data-number]').forEach(range => { const n = value(range.dataset.number); range.max = Math.max(Number(range.dataset.baseMax || range.max), n); range.value = n; });
    document.querySelectorAll('[data-adjust]').forEach(b => { const input = $(b.dataset.adjust), n = value(input.id); b.disabled = Number(b.dataset.delta) < 0 ? n <= Number(input.min) : n >= Number(input.max); });
  }
  function announce(message) { clearTimeout(announceTimer); announceTimer = setTimeout(() => textNode('calc-status', message), 650); }
  document.querySelectorAll('.calc-inputs input[type=number]').forEach(input => {
    input.addEventListener('input', () => { const n = Number(input.value); if (input.value !== '' && Number.isFinite(n) && n > Number(input.max)) input.value = input.max; calculate(true); });
    input.addEventListener('focus', () => { try { input.select(); } catch {} });
    input.addEventListener('change', () => { input.value = value(input.id); calculate(true); });
  });
  document.querySelectorAll('[data-number]').forEach(range => { range.dataset.baseMax = range.max; range.addEventListener('input', () => { $(range.dataset.number).value = range.value; calculate(true); }); });
  document.querySelectorAll('[data-adjust]').forEach(b => b.addEventListener('click', () => { const input = $(b.dataset.adjust); input.value = Math.max(Number(input.min), Math.min(Number(input.max), value(input.id) + Number(input.step || 1) * Number(b.dataset.delta))); calculate(true); }));
  $('pause')?.addEventListener('click', () => { if (root.classList.contains('paused')) settleCounters(); });
  reduced.addEventListener('change', () => { if (reduced.matches) settleCounters(); });
  calculate();
})();
