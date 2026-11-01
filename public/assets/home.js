(() => {
  const button = document.querySelector('.menu-button');
  const menu = document.getElementById('mobile-nav');
  if (!button || !menu) return;
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
})();
