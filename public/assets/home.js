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
  // Banner imagery is decorative; controls are available only after enhancement.
  const hero = document.querySelector('.hero');
  if (hero) {
    const slides = Array.from(hero.querySelectorAll('.hero-slide'));
    const messages = Array.from(hero.querySelectorAll('[data-hero-message]'));
    const announcement = hero.querySelector('[data-hero-announcement]');
    const controls = hero.querySelector('[data-hero-controls]');
    const previous = hero.querySelector('[data-hero-prev]');
    const next = hero.querySelector('[data-hero-next]');
    const toggle = hero.querySelector('[data-hero-toggle]');
    const count = hero.querySelector('[data-hero-count]');
    if (slides.length > 1 && messages.length === slides.length && controls && previous && next && toggle && count) {
      controls.hidden = false;
      let index = 0, paused = false, hovering = false, focused = false;
      const updateToggle = () => {
        toggle.hidden = reducedMotion.matches;
        toggle.setAttribute('aria-label', paused ? 'Activer le défilement de la bannière' : 'Mettre en pause le défilement de la bannière');
        toggle.innerHTML = paused ? 'LECTURE <span aria-hidden="true">▶</span>' : 'PAUSE <span aria-hidden="true">Ⅱ</span>';
      };
      const show = (nextIndex, announce = false) => {
        slides[index].classList.remove('is-active');
        messages[index].hidden = true;
        index = (nextIndex + slides.length) % slides.length;
        slides[index].classList.add('is-active');
        messages[index].hidden = false;
        if (announce && announcement) announcement.textContent = `Diapositive ${index + 1} sur ${slides.length} : ${messages[index].querySelector('h1, h2').textContent.trim()}`;
        count.textContent = `${String(index + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
      };
      previous.addEventListener('click', () => { paused = true; updateToggle(); show(index - 1, true); });
      next.addEventListener('click', () => { paused = true; updateToggle(); show(index + 1, true); });
      toggle.addEventListener('click', () => { paused = !paused; updateToggle(); });
      hero.addEventListener('mouseenter', () => { hovering = true; });
      hero.addEventListener('mouseleave', () => { hovering = false; });
      hero.addEventListener('focusin', () => { focused = true; });
      hero.addEventListener('focusout', event => { if (!hero.contains(event.relatedTarget)) focused = false; });
      if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', updateToggle);
      else if (reducedMotion.addListener) reducedMotion.addListener(updateToggle);
      updateToggle();
      window.setInterval(() => {
        const rect = hero.getBoundingClientRect();
        if (!paused && !hovering && !focused && !reducedMotion.matches && !document.hidden && !document.querySelector('dialog[open]') && rect.bottom > 0 && rect.top < window.innerHeight) show(index + 1);
      }, 2000);
    }
  }

  // Load and play the decorative film only while visible; the poster is the no-JS fallback.
  const film = document.querySelector('[data-film]');
  if (film) {
    const video = film.querySelector('[data-film-video]');
    if (video && video.dataset.src) {
      let visible = !('IntersectionObserver' in window);
      const sync = () => {
        if (!visible || document.hidden || reducedMotion.matches) { video.pause(); return; }
        if (!video.getAttribute('src')) video.setAttribute('src', video.dataset.src);
        // Autoplay can be blocked by the browser; the local poster remains visible.
        try {
          const result = video.play();
          if (result && typeof result.catch === 'function') result.catch(() => {});
        } catch (_) { /* Keep the poster if playback is unavailable. */ }
      };
      document.addEventListener('visibilitychange', sync);
      if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', sync);
      else if (reducedMotion.addListener) reducedMotion.addListener(sync);
      if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
          entries.forEach(entry => { if (entry.target === film) { visible = entry.isIntersecting; sync(); } });
        }, { threshold: 0.1 });
        observer.observe(film);
      }
      sync();
    }
  }

  // Native modal supplies focus containment, Escape and focus restoration. Never block without JS.
  const welcome = document.querySelector('.welcome-dialog');
  if (welcome && typeof welcome.showModal === 'function' && !window.location.hash) {
    let seen = false;
    try { seen = window.sessionStorage.getItem('horizon-welcome-seen') === '1'; } catch (_) { /* Storage may be unavailable. */ }
    if (!seen) {
      window.setTimeout(() => {
        // Do not interrupt someone who has started navigating, or another open dialog.
        if (window.location.hash || document.hidden || window.scrollY > 100 || (document.activeElement && document.activeElement !== document.body) || document.querySelector('dialog[open]') || (menu && !menu.hidden)) return;
        try {
          welcome.showModal();
          try { window.sessionStorage.setItem('horizon-welcome-seen', '1'); } catch (_) { /* Optional persistence. */ }
          welcome.querySelector('[data-welcome-close]').focus();
        } catch (_) { /* Unsupported or invalid dialog state: leave the page usable. */ }
      }, 1500);
    }
    welcome.querySelectorAll('[data-welcome-close], [data-welcome-link]').forEach(control => {
      control.addEventListener('click', () => welcome.close());
    });
    welcome.addEventListener('click', event => { if (event.target === welcome) welcome.close(); });
  }
})();
