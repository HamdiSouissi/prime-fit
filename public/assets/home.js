(() => {
  const button = document.querySelector('.menu-button');
  const menu = document.getElementById('mobile-nav');
  if (button && menu) {
    const close = () => { menu.hidden = true; button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-label', 'Ouvrir le menu'); };
    button.addEventListener('click', () => {
      const opening = menu.hidden;
      menu.hidden = !opening;
      button.setAttribute('aria-expanded', String(opening));
      button.setAttribute('aria-label', opening ? 'Fermer le menu' : 'Ouvrir le menu');
    });
    menu.addEventListener('click', event => { if (event.target.closest('a')) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !menu.hidden) { close(); button.focus(); } });
    window.addEventListener('resize', () => { if (window.innerWidth > 800 && !menu.hidden) close(); });
  }

  const reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: true };
  let revealObserver = null;
  const updateMotion = () => {
    const enable = !reducedMotion.matches;
    const elements = document.querySelectorAll('[data-reveal]');
    document.documentElement.classList.remove('motion-ready');
    if (revealObserver) revealObserver.disconnect();
    if (enable && 'IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
      }), { threshold: 0.1, rootMargin: '0px 0px -25px 0px' });
      revealObserver = observer;
      elements.forEach(element => observer.observe(element));
      document.documentElement.classList.add('motion-ready');
    }
    if (!enable) document.querySelectorAll('[data-parallax]').forEach(element => { element.style.transform = ''; });
  };
  updateMotion();
  if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', updateMotion);
  else if (reducedMotion.addListener) reducedMotion.addListener(updateMotion);

  // A bounded, requestAnimationFrame-throttled parallax; the base image works without JS.
  let scheduled = false;
  const updateParallax = () => {
    scheduled = false;
    if (reducedMotion.matches) return;
    document.querySelectorAll('[data-parallax]').forEach(element => {
      const section = element.parentElement;
      const rect = section.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > window.innerHeight) return;
      const distance = (window.innerHeight / 2 - (rect.top + rect.height / 2)) * 0.07;
      element.style.transform = `translate3d(0, ${Math.max(-28, Math.min(28, distance)).toFixed(1)}px, 0)`;
    });
  };
  const requestParallax = () => {
    if (!scheduled && !reducedMotion.matches) { scheduled = true; window.requestAnimationFrame(updateParallax); }
  };
  window.addEventListener('scroll', requestParallax, { passive: true });
  window.addEventListener('resize', requestParallax);
  if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', requestParallax);
  requestParallax();

  document.querySelectorAll('[data-carousel]').forEach(carousel => {
    const track = carousel.querySelector('[data-carousel-track]');
    const controls = carousel.querySelector('[data-carousel-controls]');
    const prev = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');
    const toggle = carousel.querySelector('[data-carousel-toggle]');
    const count = carousel.querySelector('.carousel-current');
    if (!track || !controls || !prev || !next || !toggle) return;
    const cards = Array.from(track.children);
    if (cards.length < 2) return;
    controls.hidden = false;
    let paused = false;
    let hovering = false;
    let focused = false;
    const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
    const positions = () => cards.map(card => Math.min(maxScroll(), Math.max(0, card.getBoundingClientRect().left - track.getBoundingClientRect().left + track.scrollLeft)));
    const updateCount = () => {
      const distances = positions().map(position => Math.abs(position - track.scrollLeft));
      const index = distances.indexOf(Math.min(...distances));
      if (count) count.textContent = `${String(index + 1).padStart(2, '0')} / ${String(cards.length).padStart(2, '0')}`;
    };
    const updateToggle = () => {
      toggle.hidden = reducedMotion.matches;
      const stopped = paused || reducedMotion.matches;
      toggle.setAttribute('aria-label', stopped ? 'Activer le défilement automatique' : 'Mettre en pause le défilement automatique');
      toggle.innerHTML = stopped ? 'LECTURE <span aria-hidden="true">▶</span>' : 'PAUSE <span aria-hidden="true">Ⅱ</span>';
    };
    const go = direction => {
      const current = track.scrollLeft;
      const points = [...new Set(positions().map(value => Math.round(value)))].sort((a, b) => a - b);
      const target = direction > 0 ? (points.find(value => value > current + 2) ?? points[0]) : ([...points].reverse().find(value => value < current - 2) ?? points[points.length - 1]);
      if (typeof track.scrollTo === 'function') track.scrollTo({ left: target, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
      else track.scrollLeft = target;
      updateCount();
    };
    prev.addEventListener('click', () => { paused = true; updateToggle(); go(-1); });
    next.addEventListener('click', () => { paused = true; updateToggle(); go(1); });
    toggle.addEventListener('click', () => { paused = !paused; updateToggle(); });
    carousel.addEventListener('mouseenter', () => { hovering = true; });
    carousel.addEventListener('mouseleave', () => { hovering = false; });
    carousel.addEventListener('focusin', () => { focused = true; });
    carousel.addEventListener('focusout', event => { if (!carousel.contains(event.relatedTarget)) focused = false; });
    track.addEventListener('pointerdown', () => { paused = true; updateToggle(); });
    track.addEventListener('keydown', () => { paused = true; updateToggle(); });
    track.addEventListener('wheel', () => { paused = true; updateToggle(); }, { passive: true });
    track.addEventListener('scroll', updateCount, { passive: true });
    window.addEventListener('resize', updateCount);
    if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', updateToggle);
    else if (reducedMotion.addListener) reducedMotion.addListener(updateToggle);
    updateToggle(); updateCount();
    window.setInterval(() => {
      const rect = carousel.getBoundingClientRect();
      if (!paused && !hovering && !focused && !reducedMotion.matches && !document.hidden && rect.bottom > 0 && rect.top < window.innerHeight) go(1);
    }, 7000);
  });
})();
