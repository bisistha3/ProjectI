(() => {
  const post = async (fd) => {
    const res = await fetch('log_water.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data && data.ok) location.reload();
  };

  document.querySelectorAll('[data-action="water"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const fd = new FormData();
      fd.append('action', 'water');
      fd.append('amount_ml', btn.dataset.amount);
      fd.append('drink_type', btn.dataset.type || 'Water');
      post(fd);
    });
  });

  document.getElementById('btn-log-water-custom')?.addEventListener('click', () => {
    const amount = document.getElementById('log-water-amount').value;
    if (!amount || parseInt(amount, 10) <= 0) return;
    const fd = new FormData();
    fd.append('action', 'water');
    fd.append('amount_ml', amount);
    fd.append('drink_type', document.getElementById('log-water-drink').value);
    post(fd);
  });
})();