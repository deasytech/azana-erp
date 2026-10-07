(function () {
  const toggle = document.getElementById('navToggle');
  const sidenav = document.getElementById('sidenav');
  const scrim = document.getElementById('scrim');

  function setNav(open) {
    sidenav.classList.toggle('open', open);
    scrim.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
  }

  toggle.addEventListener('click', () => setNav(!sidenav.classList.contains('open')));
  scrim.addEventListener('click', () => setNav(false));
  sidenav.querySelectorAll('.nav-link').forEach((a) => a.addEventListener('click', () => {
    if (window.innerWidth <= 900) setNav(false);
  }));

  // Filter the sidebar as the reader types.
  const search = document.getElementById('navSearch');
  const groups = [...document.querySelectorAll('.nav-group')];
  const empty = document.getElementById('navEmpty');

  search.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    let anyVisible = false;

    groups.forEach((group) => {
      let groupHasMatch = false;

      group.querySelectorAll('.nav-link').forEach((a) => {
        const match = q === '' || `${a.dataset.search || ''} ${a.textContent}`.toLowerCase().includes(q);
        a.style.display = match ? '' : 'none';
        groupHasMatch = groupHasMatch || match;
      });

      group.style.display = groupHasMatch ? '' : 'none';
      anyVisible = anyVisible || groupHasMatch;
    });

    empty.style.display = anyVisible ? 'none' : 'block';
  });

  // Copy buttons. The clipboard needs a secure page (https or localhost); where it is not available the button says so.
  document.querySelectorAll('.copy-btn').forEach((btn) => btn.addEventListener('click', async () => {
    const pre = btn.closest('.code-block').querySelector('pre');
    const old = btn.textContent;
    let label = 'Copied';

    try {
      await navigator.clipboard.writeText(pre.dataset.raw || pre.textContent);
    } catch {
      label = 'Not allowed';   // clipboard blocked or unavailable: nothing was copied, so do not claim it was
    }

    btn.textContent = label;
    setTimeout(() => { btn.textContent = old; }, 1200);
  }));

  // Highlight the section being read.
  const navLinks = [...document.querySelectorAll('.nav-link')];
  const sections = navLinks.map((a) => document.getElementById(a.getAttribute('href').slice(1))).filter(Boolean);

  if ('IntersectionObserver' in window) {
    let current = null;
    const observer = new IntersectionObserver((entries) => {
      entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
        const link = document.querySelector(`.nav-link[href="#${entry.target.id}"]`);

        if (link && link !== current) {
          current?.classList.remove('active');
          link.classList.add('active');
          current = link;
        }
      });
    }, { rootMargin: '-72px 0px -70% 0px', threshold: 0 });

    sections.forEach((s) => observer.observe(s));
  }
})();

(function () {
  const btn = document.getElementById('themeToggle');
  const root = document.documentElement;

  if (!btn) return;

  try {
    root.dataset.theme = localStorage.getItem('azana-docs-theme') || root.dataset.theme || '';
  } catch {
    // Storage is blocked (private window): the page simply follows the system theme.
  }

  btn.addEventListener('click', () => {
    const dark = root.dataset.theme === 'dark' || (!root.dataset.theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
    const next = dark ? 'light' : 'dark';

    root.dataset.theme = next;

    try {
      localStorage.setItem('azana-docs-theme', next);
    } catch {
      // Storage is blocked: the choice lasts until the page is closed.
    }
  });
})();
