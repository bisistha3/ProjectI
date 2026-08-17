/* ---------- HealthFlow: Log Nutrition Module ---------- */

(() => {
  const post = async (fd) => {
    const res = await fetch('log_nutrition.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data && data.ok) location.reload();
    return data;
  };

  /* ---------- Log Food modal ---------- */

  const modal        = document.getElementById('food-modal');
  const nameInput    = document.getElementById('food-modal-name');
  const qtyInput     = document.getElementById('food-modal-qty');
  const unitSelect   = document.getElementById('food-modal-unit');
  const mealSelect   = document.getElementById('food-modal-meal');
  const nutritionBox = document.getElementById('food-modal-nutrition');
  const kcalInput    = document.getElementById('food-modal-kcal');
  const proteinInput = document.getElementById('food-modal-protein');
  const fatInput     = document.getElementById('food-modal-fat');
  const carbsInput   = document.getElementById('food-modal-carbs');
  const errorBox     = document.getElementById('food-modal-error');
  const confirmBtn   = document.getElementById('btn-log-food-confirm');

  const knownFoods = window.HEALTHFLOW_FOODS || {};

  const openModal = (foodName) => {
    const known = knownFoods[foodName];
    nameInput.value = foodName;

    if (known) {
      // Already logged before — only quantity is asked; unit is locked
      unitSelect.value = known.unit;
      unitSelect.disabled = true;
      qtyInput.placeholder = known.unit === 'piece'
        ? `e.g. 1 (${known.kcal} kcal each)`
        : `e.g. ${Math.round(known.serving_qty)} (${known.kcal} kcal per ${known.unit})`;
      nutritionBox.hidden = true;
    } else {
      // First time — name, quantity, unit and nutrition are all asked
      unitSelect.disabled = false;
      qtyInput.placeholder = 'e.g. 150';
      nutritionBox.hidden = false;
    }

    qtyInput.value = '';
    kcalInput.value = proteinInput.value = fatInput.value = carbsInput.value = '';
    errorBox.hidden = true;
    modal.hidden = false;
    qtyInput.focus();
  };

  const closeModal = () => { modal.hidden = true; };

  const logFood = async () => {
    const fd = new FormData();
    fd.append('action', 'food');
    fd.append('food_name', nameInput.value.trim());
    fd.append('meal_type', mealSelect.value);
    fd.append('qty', qtyInput.value);

    if (nutritionBox.hidden) {
      // Known food — unit comes from the saved food
      fd.append('unit_type', unitSelect.value);
    } else {
      // New food — full details, auto-saved to My Foods
      fd.append('unit_type', unitSelect.value);
      fd.append('calories', kcalInput.value || 0);
      fd.append('protein_g', proteinInput.value || 0);
      fd.append('fat_g', fatInput.value || 0);
      fd.append('carbs_g', carbsInput.value || 0);
    }

    confirmBtn.disabled = true;
    try {
      const data = await post(fd);
      if (data && !data.ok && data.error) {
        errorBox.textContent = data.error;
        errorBox.hidden = false;
        confirmBtn.disabled = false;
      }
    } catch {
      confirmBtn.disabled = false;
    }
  };

  // Quick Foods + My Foods buttons → open modal with the food pre-filled
  document.querySelectorAll('[data-action="food"]').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.food));
  });

  // Add Custom Food → submit details directly (name + qty + unit + nutrition)
  document.getElementById('btn-log-food-custom')?.addEventListener('click', async () => {
    const name = document.getElementById('log-food-name').value.trim();
    const qty  = document.getElementById('log-food-qty').value;
    const kcal = document.getElementById('log-food-kcal').value;
    if (!name || !qty || parseFloat(qty) <= 0 || !kcal || parseInt(kcal, 10) <= 0) return;
    const fd = new FormData();
    fd.append('action', 'food');
    fd.append('food_name', name);
    fd.append('meal_type', document.getElementById('log-food-meal').value);
    fd.append('qty', qty);
    fd.append('unit_type', document.getElementById('log-food-unit').value);
    fd.append('calories', kcal);
    fd.append('protein_g', document.getElementById('log-food-protein').value || 0);
    fd.append('fat_g', document.getElementById('log-food-fat').value || 0);
    fd.append('carbs_g', document.getElementById('log-food-carbs').value || 0);
    await post(fd);
  });

  nameInput.addEventListener('input', () => {
    unitSelect.disabled = !!knownFoods[nameInput.value.trim()];
    nutritionBox.hidden = !!knownFoods[nameInput.value.trim()];
  });

  confirmBtn.addEventListener('click', logFood);
  qtyInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') logFood(); });
  document.querySelectorAll('[data-modal-close]').forEach(btn => btn.addEventListener('click', closeModal));
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

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
})();