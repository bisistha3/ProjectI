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

  // Enter inside a goal field must NOT save — saving happens only via the
  // Save button. Instead, Enter advances focus to the next field (and does
  // nothing on the last one). Buttons are excluded so keyboard-activating
  // Save/Skip with Enter keeps working.
  form?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const t = e.target;
    if (!t || t.tagName !== 'INPUT' || t.type === 'hidden' || t.type === 'submit' || t.type === 'button') return;
    e.preventDefault();
    const fields = [...form.querySelectorAll('input')].filter((el) => el.type !== 'hidden' && !el.disabled);
    const i = fields.indexOf(document.activeElement);
    if (i >= 0 && i < fields.length - 1) fields[i + 1].focus();
  });

  // Pre-fill form with defaults, clamped into each input's range so that
  // stale out-of-range stored goals can never make Save unpassable.
  const fill = (id, val, fallback) => {
    const el = document.getElementById(id);
    if (!el) return;
    let n = parseInt(val, 10);
    if (isNaN(n)) n = fallback;
    const min = parseFloat(el.min);
    const max = parseFloat(el.max);
    if (!isNaN(min)) n = Math.max(min, n);
    if (!isNaN(max)) n = Math.min(max, n);
    el.value = n;
  };
  const defaults = window.DAILY_GOALS_DEFAULTS || {};
  fill('dg-water',    defaults.daily_goal_ml, 2500);
  fill('dg-calories', defaults.daily_calorie_goal, 2000);
  fill('dg-protein',  defaults.daily_protein_goal_g, 125);
  fill('dg-fat',      defaults.daily_fat_goal_g, 67);
  fill('dg-carbs',    defaults.daily_carbs_goal_g, 225);
  fill('dg-exercise', defaults.daily_exercise_goal_min, 30);
  fill('dg-burn',     defaults.daily_burn_goal_kcal, 300);

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

  // Form submission — only the Save button may save. Implicit submits
  // (Enter-key quirks, mobile Go-key) carry no submitter and are ignored.
  form?.addEventListener('submit', async (e) => {
    if (!e.submitter) return;
    e.preventDefault();
    if (errorEl) errorEl.style.display = 'none';

    const saveBtn = document.getElementById('daily-goal-save');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    const fd = new FormData();
    fd.append('ajax', '1');
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
