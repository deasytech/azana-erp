// Public site behaviour: mobile menu, header shadow, reveal on scroll. Everything works without it.
(() => {
    const header = document.querySelector('.top');
    const burger = document.querySelector('.burger');
    const drawer = document.getElementById('drawer');

    const setOpen = (open) => {
        if (!burger || !drawer) return;
        burger.setAttribute('aria-expanded', String(open));
        burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        drawer.classList.toggle('is-open', open);
    };

    if (burger && drawer) {
        setOpen(false);
        burger.addEventListener('click', () => setOpen(burger.getAttribute('aria-expanded') !== 'true'));
        drawer.addEventListener('click', (e) => { if (e.target.closest('a')) setOpen(false); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') { setOpen(false); burger.focus(); }
        });
        window.matchMedia('(min-width: 62rem)').addEventListener('change', (e) => { if (e.matches) setOpen(false); });
    }

    if (header) {
        const onScroll = () => header.classList.toggle('is-stuck', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    const items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) { items.forEach((el) => el.classList.add('is-in')); return; }
    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    items.forEach((el) => io.observe(el));
})();
