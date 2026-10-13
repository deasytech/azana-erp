(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // Mobile sidebar
  const toggle = $('#navToggle'), sidenav = $('#sidenav'), scrim = $('#scrim');
  const setNav = (open) => {
    sidenav.classList.toggle('open', open);
    scrim.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
  };
  toggle.addEventListener('click', () => setNav(!sidenav.classList.contains('open')));
  scrim.addEventListener('click', () => setNav(false));
  $$('.nav-link', sidenav).forEach((a) => a.addEventListener('click', () => { if (window.innerWidth <= 900) setNav(false); }));

  // Filter the sidebar as the reader types; "/" jumps to the search box.
  const search = $('#navSearch'), groups = $$('.nav-group'), empty = $('#navEmpty');
  search.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    let any = false;
    groups.forEach((g) => {
      let has = false;
      $$('.nav-link', g).forEach((a) => {
        const hit = q === '' || `${a.dataset.search || ''} ${a.textContent}`.toLowerCase().includes(q);
        a.style.display = hit ? '' : 'none';
        has = has || hit;
      });
      g.style.display = has ? '' : 'none';
      any = any || has;
    });
    empty.style.display = any ? 'none' : 'block';
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !/input|textarea/i.test(document.activeElement.tagName)) { e.preventDefault(); search.focus(); }
    if (e.key === 'Escape') { search.blur(); lightbox.classList.remove('open'); }
  });

  // Highlight the chapter being read.
  const links = $$('.nav-link');
  const sections = links.map((a) => document.getElementById(a.getAttribute('href').slice(1))).filter(Boolean);
  if ('IntersectionObserver' in window) {
    let current = null;
    const observer = new IntersectionObserver((entries) => {
      entries.filter((e) => e.isIntersecting).forEach((e) => {
        const link = $(`.nav-link[href="#${e.target.id}"]`);
        if (link && link !== current) {
          current?.classList.remove('active');
          link.classList.add('active');
          link.scrollIntoView({ block: 'nearest' });
          current = link;
        }
      });
    }, { rootMargin: '-72px 0px -70% 0px', threshold: 0 });
    sections.forEach((s) => observer.observe(s));
  }

  // Reading progress and back-to-top
  const bar = $('#progress'), top = $('#backTop');
  const onScroll = () => {
    const h = document.documentElement;
    const max = h.scrollHeight - h.clientHeight;
    bar.style.width = max > 0 ? `${(h.scrollTop / max) * 100}%` : '0';
    top.classList.toggle('show', h.scrollTop > 900);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  top.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // Click a screenshot to see it large.
  const lightbox = $('#lightbox'), big = $('img', lightbox);
  $$('[data-zoom]').forEach((a) => a.addEventListener('click', (e) => {
    e.preventDefault();
    big.src = a.href;
    lightbox.classList.add('open');
  }));
  lightbox.addEventListener('click', () => lightbox.classList.remove('open'));

  // Light / dark
  const btn = $('#themeToggle'), root = document.documentElement;
  try { root.dataset.theme = localStorage.getItem('azana-docs-theme') || root.dataset.theme || ''; } catch { /* storage blocked: follow the system */ }
  btn.addEventListener('click', () => {
    const dark = root.dataset.theme === 'dark' || (!root.dataset.theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('azana-docs-theme', root.dataset.theme); } catch { /* lasts until the page closes */ }
  });
})();
