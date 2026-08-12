/* ---------- Goal Calculator (Settings) — BMI-based ---------- */
export function initGoalCalculator() {
  const weightInput  = document.getElementById('setting-weight');
  const heightInput  = document.getElementById('setting-height');
  const activitySel  = document.getElementById('setting-activity');
  const goalDisplay  = document.getElementById('recommended-goal');
  const bmiValue     = document.getElementById('bmi-value');
  const bmiCategory  = document.getElementById('bmi-category');
  const hiddenGoal   = document.getElementById('daily-goal-ml');
  const goalMode     = document.getElementById('goal-mode');

  // Gender radios live inside the goal card
  const genderMale   = document.getElementById('setting-gender-male');
  const genderFemale = document.getElementById('setting-gender-female');

  // Custom goal toggle
  const switchEl     = document.getElementById('custom-goal-switch');
  const knobEl       = document.getElementById('custom-goal-knob');
  const toggleLabel  = document.getElementById('custom-toggle-label');
  const customArea   = document.getElementById('custom-goal-area');
  const customInput  = document.getElementById('custom-goal-input');

  // Gender toggle slider for the goal card
  const gSlider      = document.getElementById('goal-gender-slider');
  const gLabelMale   = document.getElementById('goal-label-male');
  const gLabelFemale = document.getElementById('goal-label-female');

  if (!weightInput || !heightInput || !goalDisplay) return;

  // ── BMI-based calculation ────────────────────────────────────────────────
  function calcBmi() {
    const w = parseFloat(weightInput.value) || 70;
    const h = parseFloat(heightInput.value)  || 170;
    const gender   = genderFemale?.checked ? 'female' : 'male';
    const activity = activitySel?.value || 'medium';

    // BMI
    const bmi = w / ((h / 100) ** 2);

    // Multiplier and category based on BMI
    let mult, catLabel, catColor;
    if      (bmi < 18.5) { mult = 40; catLabel = 'Underweight'; catColor = '#f59e0b'; }
    else if (bmi < 25.0) { mult = 35; catLabel = 'Normal Weight'; catColor = '#10b981'; }
    else if (bmi < 30.0) { mult = 30; catLabel = 'Overweight';   catColor = '#f97316'; }
    else                 { mult = 25; catLabel = 'Obese';         catColor = '#ef4444'; }

    // Base goal
    let goal = Math.round(w * mult);

    // Gender adjustment: women need ~10% less water than men
    if (gender === 'female') goal = Math.round(goal * 0.9);

    // Activity bonus
    const bonus = { low: 0, medium: 500, high: 1000 }[activity] ?? 500;
    goal = Math.max(1500, Math.min(5000, goal + bonus));

    // Update BMI display
    if (bmiValue)    bmiValue.textContent    = bmi.toFixed(1);
    if (bmiCategory) {
      bmiCategory.textContent = catLabel;
      bmiCategory.style.color = catColor;
    }

    // Update recommendation banner
    goalDisplay.textContent = (goal / 1000).toFixed(1) + 'L / Day';

    // Only update hidden field if NOT in custom mode
    if (hiddenGoal && goalMode?.value !== 'custom') {
      hiddenGoal.value = goal;
    }
  }

  // ── Custom goal toggle switch ─────────────────────────────────────────────
  let isCustom = false;
  if (switchEl) {
    switchEl.addEventListener('click', () => {
      isCustom = !isCustom;
      // Animate knob
      knobEl.style.transform      = isCustom ? 'translateX(20px)' : 'translateX(0)';
      switchEl.style.background   = isCustom ? 'var(--color-primary)' : 'var(--color-surface-container-high)';
      toggleLabel.textContent     = isCustom ? 'On' : 'Off';
      toggleLabel.style.color     = isCustom ? 'var(--color-primary)' : 'var(--color-on-surface-variant)';
      customArea.style.display    = isCustom ? 'block' : 'none';

      if (goalMode) goalMode.value = isCustom ? 'custom' : 'bmi';

      // When switching to custom, pre-fill with current recommendation
      if (isCustom && customInput && hiddenGoal) {
        customInput.value = hiddenGoal.value;
      }
      // When switching back to bmi, restore calculated value
      if (!isCustom) calcBmi();
    });
  }

  // Sync custom input → hidden field
  if (customInput) {
    customInput.addEventListener('input', () => {
      if (hiddenGoal) hiddenGoal.value = customInput.value;
    });
  }

  // ── Gender toggle in goal card ────────────────────────────────────────────
  if (genderMale && genderFemale && gSlider && gLabelMale && gLabelFemale) {
    [genderMale, genderFemale].forEach(radio => {
      radio.addEventListener('change', () => {
        const isFemale = genderFemale.checked;
        gSlider.style.transform = isFemale ? 'translateX(100%)' : 'translateX(0)';
        gLabelMale.classList.toggle('active', !isFemale);
        gLabelFemale.classList.toggle('active', isFemale);
        calcBmi();
      });
    });
  }

  // ── Event listeners ───────────────────────────────────────────────────────
  weightInput.addEventListener('input',  calcBmi);
  heightInput.addEventListener('input',  calcBmi);
  if (activitySel) activitySel.addEventListener('change', calcBmi);

  calcBmi(); // Initial calculation on page load
}