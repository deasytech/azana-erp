(function(){
  var toggle = document.getElementById('navToggle');
  var sidenav = document.getElementById('sidenav');
  var scrim = document.getElementById('scrim');
  function closeNav(){ sidenav.classList.remove('open'); scrim.classList.remove('open'); toggle.setAttribute('aria-expanded','false'); }
  function openNav(){ sidenav.classList.add('open'); scrim.classList.add('open'); toggle.setAttribute('aria-expanded','true'); }
  toggle.addEventListener('click', function(){ sidenav.classList.contains('open') ? closeNav() : openNav(); });
  scrim.addEventListener('click', closeNav);
  sidenav.querySelectorAll('.nav-link').forEach(function(a){ a.addEventListener('click', function(){ if(window.innerWidth <= 900) closeNav(); }); });

  var search = document.getElementById('navSearch');
  var groups = Array.prototype.slice.call(document.querySelectorAll('.nav-group'));
  var empty = document.getElementById('navEmpty');
  search.addEventListener('input', function(){
    var q = search.value.trim().toLowerCase();
    var anyVisible = false;
    groups.forEach(function(g){
      var links = g.querySelectorAll('.nav-link');
      var groupHasMatch = false;
      links.forEach(function(a){
        var hay = (a.dataset.search || '') + ' ' + a.textContent;
        var match = q === '' || hay.toLowerCase().includes(q);
        a.style.display = match ? '' : 'none';
        if (match) groupHasMatch = true;
      });
      g.style.display = groupHasMatch ? '' : 'none';
      if (groupHasMatch) anyVisible = true;
    });
    empty.style.display = anyVisible ? 'none' : 'block';
  });

  document.querySelectorAll('.copy-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var pre = btn.closest('.code-block').querySelector('pre');
      var text = pre.dataset.raw || pre.textContent;
      function done(){ var old = btn.textContent; btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = old; }, 1200); }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function(){ fallbackCopy(text); done(); });
      } else {
        fallbackCopy(text); done();
      }
    });
  });
  function fallbackCopy(text){
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } catch(e){}
    ta.remove();
  }

  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.nav-link'));
  var sections = navLinks.map(function(a){ return document.getElementById(a.getAttribute('href').slice(1)); }).filter(Boolean);
  if ('IntersectionObserver' in window) {
    var current = null;
    var observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          var id = entry.target.id;
          var link = document.querySelector('.nav-link[href="#' + id + '"]');
          if (link && link !== current) {
            if (current) current.classList.remove('active');
            link.classList.add('active');
            current = link;
          }
        }
      });
    }, { rootMargin: '-72px 0px -70% 0px', threshold: 0 });
    sections.forEach(function(s){ observer.observe(s); });
  }
})();
(function(){
  var btn = document.getElementById('themeToggle');
  if (!btn) return;
  var root = document.documentElement;
  try { var saved = localStorage.getItem('azana-docs-theme'); if (saved) root.setAttribute('data-theme', saved); } catch(e){}
  btn.addEventListener('click', function(){
    var dark = root.getAttribute('data-theme') === 'dark' || (!root.getAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    var next = dark ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('azana-docs-theme', next); } catch(e){}
  });
})();
