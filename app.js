import { initPasswordToggles } from './js/password-toggles.js';
import { initGenderToggle }   from './js/gender-toggle.js';
import { initMobileSidebar }  from './js/sidebar.js';
import { initGoalCalculator } from './js/goal-calculator.js';
import { initSettingsActions } from './js/settings-actions.js';
import { initFormHandlers }   from './js/forms.js';
import { initReminderToggle, initReminderToast } from './js/reminder.js';
import { initChartToggle, initCalendarNav, initBarTooltips } from './js/history.js';

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

    if (!confirm('Delete this log entry?')) return;

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
  initLogDelete();
});