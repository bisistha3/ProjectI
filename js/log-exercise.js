/* ---------- HealthFlow: Log Nutrition Module ---------- */

(() => {
  const post = async (fd) => {
    const res = await fetch('log_nutrition.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data && data.ok) location.reload();
  };

  // Preset foods + saved My Foods (one-click)
  document.querySelectorAll('[data-action="food"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const fd = new FormData();
      fd.append('action', 'food');
      fd.append('food_name', btn.dataset.food);
      post(fd);
    });
  });

  // Remove saved custom food from My Foods
  document.querySelectorAll('[data-delete-food]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Remove this food from My Foods?')) return;
      const fd = new FormData();
      fd.append('action', 'delete_custom_food');
      fd.append('food_id', btn.dataset.deleteFood);
      const res = await fetch('log_nutrition.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data && data.ok) location.reload();
    });
  });

  // Custom food
  document.getElementById('btn-log-food-custom')?.addEventListener('click', () => {
    const name = document.getElementById('log-food-name').value.trim();
    const kcal = document.getElementById('log-food-kcal').value;
    if (!name || !kcal || parseInt(kcal, 10) <= 0) return;
    const fd = new FormData();
    fd.append('action', 'food');
    fd.append('food_name', name);
    fd.append('meal_type', document.getElementById('log-food-meal').value);
    fd.append('calories', kcal);
    fd.append('protein_g', document.getElementById('log-food-protein').value || 0);
    fd.append('fat_g', document.getElementById('log-food-fat').value || 0);
    fd.append('carbs_g', document.getElementById('log-food-carbs').value || 0);
    post(fd);
  });
})();