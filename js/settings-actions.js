import { initGoalCalculator } from './goal-calculator.js';

export function initSettingsActions() {
  const saveBtn = document.getElementById('btn-save-settings');
  const resetBtn = document.getElementById('btn-reset-settings');

  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      // Simulate save with visual feedback
      const originalText = saveBtn.innerHTML;
      saveBtn.innerHTML = `
        <span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span>
        Saved!
      `;
      saveBtn.style.backgroundColor = '#16a34a';

      setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.style.backgroundColor = '';
      }, 2000);
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      const weightInput = document.getElementById('setting-weight');
      const activitySelect = document.getElementById('setting-activity');
      const nameInput = document.getElementById('profile-name');
      const emailInput = document.getElementById('profile-email');
      const ageInput = document.getElementById('profile-age');
      const heightInput = document.getElementById('profile-height');

      if (weightInput) weightInput.value = 70;
      if (activitySelect) activitySelect.selectedIndex = 1;
      if (nameInput) nameInput.value = '';
      if (emailInput) emailInput.value = '';
      if (ageInput) ageInput.value = '';
      if (heightInput) heightInput.value = '';

      // Re-check all notification toggles
      document.querySelectorAll('.toggle input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
      });

      initGoalCalculator();

      const originalText = resetBtn.innerHTML;
      resetBtn.innerHTML = `
        <span class="material-symbols-outlined" style="font-size: 18px;">check</span>
        Reset Done
      `;
      setTimeout(() => {
        resetBtn.innerHTML = originalText;
      }, 1500);
    });
  }
}