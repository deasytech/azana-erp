/* The sidebar, search, section highlight and light/dark switch come from api-docs.js; this adds what only the guide has. */
(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // "/" jumps to the search box.
  const search = $('#navSearch');
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !/input|textarea/i.test(document.activeElement.tagName)) { e.preventDefault(); search.focus(); }
  });

  // Reading progress and back-to-top.
  const bar = $('#progress'), top = $('#backTop');
  window.addEventListener('scroll', () => {
    const h = document.documentElement;
    const max = h.scrollHeight - h.clientHeight;
    bar.style.width = max > 0 ? `${(h.scrollTop / max) * 100}%` : '0';
    top.classList.toggle('show', h.scrollTop > 900);
  }, { passive: true });
  top.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // Click a screenshot to see it large (a native dialog: Escape closes it).
  const lightbox = $('#lightbox'), big = $('img', lightbox);
  $$('[data-zoom]').forEach((a) => a.addEventListener('click', (e) => {
    e.preventDefault();
    big.src = a.href;
    lightbox.showModal();
  }));
  lightbox.addEventListener('click', () => lightbox.close());
})();
