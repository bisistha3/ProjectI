import { initPasswordToggles } from './js/password-toggles.js';
import { initGenderToggle }   from './js/gender-toggle.js';
import { initMobileSidebar }  from './js/sidebar.js';
import { initQuickLog }       from './js/quicklog.js';
import { initGoalCalculator } from './js/goal-calculator.js';
import { initSettingsActions } from './js/settings-actions.js';
import { initFormHandlers }   from './js/forms.js';
import { initChartToggle, initCalendarNav, initBarTooltips } from './js/history.js';

document.addEventListener('DOMContentLoaded', () => {
  initPasswordToggles();
  initGenderToggle();
  initMobileSidebar();
  initQuickLog();
  initGoalCalculator();
  initSettingsActions();
  initFormHandlers();
  initChartToggle();
  initCalendarNav();
  initBarTooltips();
});