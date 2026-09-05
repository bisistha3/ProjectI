/**
 * Daily Goal Prompt module.
 * Shows a modal on first login of the day for the user to set their daily goals.
 */

export function initDailyGoalPrompt() {
  const modal = document.getElementById('daily-goal-modal');
  if (!modal) return;

  const form    = document.getElementById('daily-goal-form');
  const errorEl = document.getElementById('daily-goal-error');
  const skipBtn = document.getElementById('daily-goal-skip');
  const closeBtn = document.getElementById('daily-goal-close');

  // Pre-fill form with defaults
  const defaults = window.DAILY_GOALS_DEFAULTS || {};
  document.getElementById('dg-water').value    = defaults.daily_goal_ml || 2500;
  document.getElementById('dg-calories').value = defaults.daily_calorie_goal || 2000;
  document.getElementById('dg-protein').value  = defaults.daily_protein_goal_g || 125;
  document.getElementById('dg-fat').value      = defaults.daily_fat_goal_g || 67;
  document.getElementById('dg-carbs').value    = defaults.daily_carbs_goal_g || 225;
  document.getElementById('dg-exercise').value = defaults.daily_exercise_goal_min || 30;
  document.getElementById('dg-burn').value     = defaults.daily_burn_goal_kcal || 300;

  function close() {
    modal.hidden = true;
    document.body.style.overflow = '';
  }

  // Skip and close buttons
  skipBtn?.addEventListener('click', async () => {
    try {
      const fd = new FormData();
      fd.append('action', 'skip_daily_goals');
      await fetch('dashboard.php', { method: 'POST', body: fd });
    } catch { /* ignore */ }
    close();
  });
  closeBtn?.addEventListener('click', close);
  modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

  // Form submission
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (errorEl) errorEl.style.display = 'none';

    const saveBtn = document.getElementById('daily-goal-save');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    const fd = new FormData();
    fd.append('action', 'update_daily_goals');
    fd.append('daily_goal_ml', document.getElementById('dg-water').value);
    fd.append('daily_calorie_goal', document.getElementById('dg-calories').value);
    fd.append('daily_protein_goal_g', document.getElementById('dg-protein').value);
    fd.append('daily_fat_goal_g', document.getElementById('dg-fat').value);
    fd.append('daily_carbs_goal_g', document.getElementById('dg-carbs').value);
    fd.append('daily_exercise_goal_min', document.getElementById('dg-exercise').value);
    fd.append('daily_burn_goal_kcal', document.getElementById('dg-burn').value);

    try {
      const res  = await fetch('dashboard.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data && data.ok) {
        location.reload();
      } else {
        if (errorEl) {
          errorEl.textContent = data?.error || 'Failed to save goals.';
          errorEl.style.display = 'block';
        }
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Goals';
      }
    } catch {
      if (errorEl) {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.style.display = 'block';
      }
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Goals';
    }
  });

  // Show modal if flag is set
  if (window.SHOW_DAILY_GOAL_PROMPT) {
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }
}
