/* Civil dates and times always refer to Europe/Madrid, independent of the device. */
((root, factory) => {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.SiShowAgenda = api;
})(globalThis, () => {
  'use strict';
  const zone = 'Europe/Madrid';
  const clock = new Intl.DateTimeFormat('en-GB', {timeZone:zone, year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit', hourCycle:'h23'});
  function madrid(now = new Date()) {
    const p = Object.fromEntries(clock.formatToParts(now).map(x => [x.type, x.value]));
    return {date:`${p.year}-${p.month}-${p.day}`, time:`${p.hour}:${p.minute}`};
  }
  function shift(date, days) {
    const d = new Date(`${date}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0,10);
  }
  function end(now = new Date()) {
    const current = madrid(now), d = new Date(`${current.date.slice(0,7)}-01T12:00:00Z`);
    d.setUTCMonth(d.getUTCMonth()+2,0);
    return d.toISOString().slice(0,10);
  }
  function base(date) {
    const day = new Date(`${date}T12:00:00Z`).getUTCDay();
    const ranges = day === 0 || day === 6 ? [] : day === 5 ? [[960,1080]] : day === 2 || day === 4 ? [[600,720],[960,1080]] : [[930,1170]];
    const times = ranges.flatMap(([a,b]) => Array.from({length:(b-a)/30}, (_,i) => {
      const n = a+i*30;
      return `${String(Math.floor(n/60)).padStart(2,'0')}:${String(n%60).padStart(2,'0')}`;
    }));
    let seed = 0;
    for (const c of date) seed = (seed*31+c.charCodeAt(0))%65521;
    const busy = new Set(times.length ? [seed%times.length] : []);
    if (times.length === 8) busy.add((seed%8+3+Math.floor(seed/7)%4)%8);
    return times.map((time,i) => ({time, busy:busy.has(i)}));
  }
  function days(now = new Date(), occupied = []) {
    const current = madrid(now), booked = new Set(occupied.map(x => `${x.fecha} ${x.hora}`));
    const count = Math.round((new Date(`${end(now)}T12:00Z`)-new Date(`${current.date}T12:00Z`))/86400000)+1;
    return Array.from({length:count}, (_,i) => {
      const date = shift(current.date,i);
      const slots = base(date).map(s => {
        const past = date < current.date || date === current.date && s.time <= current.time;
        return {...s, past, available:!past && !s.busy && !booked.has(`${date} ${s.time}`)};
      });
      return {date, slots, available:slots.some(s => s.available)};
    });
  }
  return {zone, madrid, shift, end, base, days};
});
