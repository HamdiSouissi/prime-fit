(() => {
  // Progressive enhancement: without JavaScript every entry remains visible.
  document.querySelectorAll('[data-member-pager]').forEach((container) => {
    const items = Array.from(container.querySelectorAll('[data-member-item]'));
    const pager = container.querySelector('.member-pager');
    const prev = pager?.querySelector('[data-prev]');
    const next = pager?.querySelector('[data-next]');
    const count = pager?.querySelector('[data-count]');
    const size = Math.max(1, Number.parseInt(container.dataset.pageSize, 10) || 1);
    if (!pager || !prev || !next || !count || items.length <= size) return;
    let page = 0;
    const pages = Math.ceil(items.length / size);
    const render = () => {
      items.forEach((item, index) => { item.hidden = index < page * size || index >= (page + 1) * size; });
      prev.disabled = page === 0;
      next.disabled = page === pages - 1;
      count.textContent = `${page + 1} / ${pages}`;
    };
    prev.addEventListener('click', () => { if (page > 0) { page--; render(); } });
    next.addEventListener('click', () => { if (page < pages - 1) { page++; render(); } });
    pager.hidden = false;
    render();
  });
})();
