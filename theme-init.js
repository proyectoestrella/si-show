/* Synchronous two-state theme, also used by PHP. */
(()=>{let t='dark';try{const s=localStorage.getItem('sishow-theme');if(['light','dark'].includes(s))t=s;}catch{}document.documentElement.dataset.theme=t;document.documentElement.classList.add('js');})();
