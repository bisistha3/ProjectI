import { initPasswordToggles } from './js/password-toggles.js?v=3';
import { initGenderToggle }   from './js/gender-toggle.js?v=3';
import { initMobileSidebar }  from './js/sidebar.js?v=3';
import { initGoalCalculator } from './js/goal-calculator.js?v=4';
import { initSettingsActions } from './js/settings-actions.js?v=3';
import { initFormHandlers }   from './js/forms.js?v=3';
import { initReminderToggle, initReminderToast } from './js/reminder.js?v=3';
import { initChartToggle, initCalendarNav, initBarTooltips } from './js/history.js?v=3';
import { showConfirm } from './js/confirm-modal.js?v=3';
import { initDailyGoalPrompt } from './js/daily-goal-prompt.js?v=3';

/**
 * Generic handler for individual log editing (log pages).
 * Buttons carry data-edit-type (water|food|exercise), data-edit-id, and data-* for current values.
 */
function initLogEdit() {
  // Modal elements
  const waterModal   = document.getElementById('edit-water-modal');
  const foodModal    = document.getElementById('edit-food-modal');
  const exerciseModal = document.getElementById('edit-exercise-modal');

  const waterForm    = document.getElementById('edit-water-form');
  const foodForm     = document.getElementById('edit-food-form');
  const exerciseForm = document.getElementById('edit-exercise-form');

  let currentEditId = null;
  let currentEditType = null;

  // Delegated click for edit buttons
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-edit-type]');
    if (!btn) return;

    currentEditType = btn.dataset.editType;
    currentEditId = btn.dataset.editId;

    if (currentEditType === 'water') {
      document.getElementById('edit-water-amount').value = btn.dataset.amount || '';
      document.getElementById('edit-water-drink').value = btn.dataset.drink || 'Water';
      document.getElementById('edit-water-error').style.display = 'none';
      waterModal.hidden = false;
      document.getElementById('edit-water-amount').focus();
    }
    else if (currentEditType === 'food') {
      document.getElementById('edit-food-meal').value = btn.dataset.meal || 'snack';
      document.getElementById('edit-food-qty').value = btn.dataset.qty || '';
      document.getElementById('edit-food-unit').value = btn.dataset.unit || 'g';
      document.getElementById('edit-food-error').style.display = 'none';
      foodModal.hidden = false;
      document.getElementById('edit-food-qty').focus();
    }
    else if (currentEditType === 'exercise') {
      document.getElementById('edit-exercise-type').value = btn.dataset.exercise || 'Walking';
      document.getElementById('edit-exercise-duration').value = btn.dataset.duration || 10;
      document.getElementById('edit-exercise-error').style.display = 'none';
      exerciseModal.hidden = false;
      document.getElementById('edit-exercise-duration').focus();
    }
  });

  // Close handlers
  [waterModal, foodModal, exerciseModal].forEach(modal => {
    modal?.querySelectorAll('[data-modal-close]').forEach(btn =>
      btn.addEventListener('click', () => modal.hidden = true)
    );
    modal?.addEventListener('click', (e) => { if (e.target === modal) modal.hidden = true; });
  });

  // Form submit handlers
  const submitEdit = async (form, type, fdExtra) => {
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'edit_log');
    fd.append('log_type', type);
    fd.append('log_id', currentEditId);
    fdExtra.forEach(([key, value]) => fd.append(key, value));

    try {
      const res = await fetch('dashboard.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data && data.ok) location.reload();
      else {
        const errorBox = form.querySelector('.field-error');
        if (errorBox) { errorBox.textContent = data?.error || 'Failed to update'; errorBox.style.display = 'block'; }
        submitBtn.disabled = false;
      }
    } catch {
      submitBtn.disabled = false;
    }
  };

  waterForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const amountInput = document.getElementById('edit-water-amount');
    const amount = parseInt(amountInput.value, 10);
    if (isNaN(amount) || amount <= 0 || amount > 5000) {
      const errorBox = waterForm.querySelector('.field-error');
      if (errorBox) {
        errorBox.textContent = 'Invalid amount (1-5000ml)';
        errorBox.style.display = 'block';
      }
      return;
    }
    submitEdit(waterForm, 'water', [
      ['amount_ml', amount],
      ['drink_type', document.getElementById('edit-water-drink').value]
    ]);
  });

  foodForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const qtyInput = document.getElementById('edit-food-qty');
    const qty = parseFloat(qtyInput.value);
    const mealSelect = document.getElementById('edit-food-meal');
    const unitSelect = document.getElementById('edit-food-unit');
    const validMeals = ['breakfast', 'lunch', 'dinner', 'snack'];
    const validUnits = ['g', 'piece', 'ml'];
    if (isNaN(qty) || qty <= 0 || qty > 100000 || !validMeals.includes(mealSelect.value) || !validUnits.includes(unitSelect.value)) {
      const errorBox = foodForm.querySelector('.field-error');
      if (errorBox) {
        errorBox.textContent = 'Invalid quantity, meal type, or unit';
        errorBox.style.display = 'block';
      }
      return;
    }
    submitEdit(foodForm, 'food', [
      ['meal_type', mealSelect.value],
      ['qty', qty],
      ['unit_type', unitSelect.value]
    ]);
  });

  exerciseForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const durationInput = document.getElementById('edit-exercise-duration');
    const duration = parseInt(durationInput.value, 10);
    const exerciseSelect = document.getElementById('edit-exercise-type');
    const validExercises = ['Walking', 'Running', 'Yoga', 'Gym / Strength'];
    if (isNaN(duration) || duration < 1 || duration > 600 || !validExercises.includes(exerciseSelect.value)) {
      const errorBox = exerciseForm.querySelector('.field-error');
      if (errorBox) {
        errorBox.textContent = 'Invalid exercise type or duration (1-600 min)';
        errorBox.style.display = 'block';
      }
      return;
    }
    submitEdit(exerciseForm, 'exercise', [
      ['exercise_type', exerciseSelect.value],
      ['duration_min', duration]
    ]);
  });
}

/**
 * Generic handler for individual log deletion (dashboard "Today's Log" and log pages).
 * Buttons carry data-delete-type (water|food|exercise) and data-delete-id.
 */
function initLogDelete() {
  // delegated on document so buttons rendered/re-rendered by PHP need no per-row binding
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-delete-type]');
    if (!btn) return;
    const type = btn.dataset.deleteType;
    const id   = btn.dataset.deleteId;
    if (!type || !id) return;

    const logItem = btn.closest('.log-item');
    const amount = logItem?.querySelector('.log-item__amount')?.textContent?.trim();
    const desc = logItem?.querySelector('.log-item__desc')?.textContent?.trim();
    const typeLabel = { water: 'drink', food: 'meal', exercise: 'workout' }[type] || 'entry';
    const parts = [amount, desc].filter(Boolean);
    const message = `Delete this ${typeLabel}${parts.length ? ` (${parts.join(' \u00b7 ')})` : ''}?`;

    const confirmed = await showConfirm({ message, variant: 'danger' });
    if (!confirmed) return;

    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('action', 'delete_log');
      fd.append('log_type', type);
      fd.append('log_id', id);
      const res = await fetch('dashboard.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data && data.ok) location.reload();
      else btn.disabled = false;
    } catch {
      btn.disabled = false;
    }
  });
}

// Call immediately — modules execute after DOM is fully parsed
initLogEdit();
initLogDelete();

document.addEventListener('DOMContentLoaded', () => {
  initPasswordToggles();
  initGenderToggle();
  initMobileSidebar();
  initGoalCalculator();
  initSettingsActions();
  initFormHandlers();
  initReminderToggle();
  initReminderToast();
  initChartToggle();
  initCalendarNav();
  initBarTooltips();
  initDailyGoalPrompt();
});