(() => {
  const all = document.getElementById('select-members');
  const members = Array.from(document.querySelectorAll('.member-selection'));
  if (!all || !members.length) return;
  const sync = () => {
    const checked = members.filter((item) => item.checked).length;
    all.checked = checked === members.length;
    all.indeterminate = checked > 0 && checked < members.length;
  };
  all.addEventListener('change', () => { members.forEach((item) => { item.checked = all.checked; }); sync(); });
  members.forEach((item) => item.addEventListener('change', sync));
  sync();
})();
