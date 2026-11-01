(() => {
  document.querySelectorAll('[data-select-group]').forEach((all) => {
    const name = all.dataset.selectGroup;
    const items = Array.from(document.querySelectorAll('[data-selection-group]')).filter(item => item.dataset.selectionGroup === name);
    if (!items.length) return;
    const sync = () => {
      const checked = items.filter(item => item.checked).length;
      all.checked = checked === items.length;
      all.indeterminate = checked > 0 && checked < items.length;
    };
    all.addEventListener('change', () => { items.forEach(item => { item.checked = all.checked; }); sync(); });
    items.forEach(item => item.addEventListener('change', sync));
    sync();
  });
})();
