/* ---------- Goal Calculator (Settings) — BMI water + Mifflin-St Jeor nutrition ---------- */
export function initGoalCalculator() {
  const weightInput  = document.getElementById('setting-weight');
  const heightInput  = document.getElementById('setting-height');
  const ageInput     = document.getElementById('profile-age');
  const activitySel  = document.getElementById('setting-activity');
  const goalDisplay  = document.getElementById('recommended-goal');
  const bmiValue     = document.getElementById('bmi-value');
  const bmiCategory  = document.getElementById('bmi-category');
  const hiddenGoal   = document.getElementById('daily-goal-ml');
  const goalMode     = document.getElementById('goal-mode');

  // Nutrition recommendation elements
  const recCalories  = document.getElementById('rec-calories');
  const recProtein   = document.getElementById('rec-protein');
  const recFat       = document.getElementById('rec-fat');
  const recCarbs     = document.getElementById('rec-carbs');
  const nutriMode    = document.getElementById('nutrition-mode');
  const nutriSwitch  = document.getElementById('nutrition-goal-switch');
  const nutriKnob    = document.getElementById('nutrition-goal-knob');
  const nutriToggleLabel = document.getElementById('nutrition-toggle-label');
  const nutriCustomArea  = document.getElementById('nutrition-custom-area');

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

  function round5(n) { return Math.round(n / 5) * 5; }

  // ── BMI-based water calculation ────────────────────────────────────────────
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

  // ── Mifflin-St Jeor nutrition calculation (mirror of includes/calculator.php) ──
  function calcNutrition() {
    const w = parseFloat(weightInput.value) || 70;
    const h = parseFloat(heightInput.value)  || 170;
    const a = parseFloat(ageInput?.value)    || 25;
    const gender   = genderFemale?.checked ? 'female' : 'male';
    const activity = activitySel?.value || 'medium';

    const bmr     = 10 * w + 6.25 * h - 5 * a + (gender === 'female' ? -161 : 5);
    const factor  = { low: 1.375, medium: 1.55, high: 1.725 }[activity] ?? 1.55;
    let calories  = round5(bmr * factor);
    let protein   = round5((calories * 0.25) / 4);
    let fat       = round5((calories * 0.30) / 9);
    let carbs     = round5((calories * 0.45) / 4);

    calories = Math.max(1200, Math.min(5000, calories));
    protein  = Math.max(40,   Math.min(300, protein));
    fat      = Math.max(20,   Math.min(150, fat));
    carbs    = Math.max(100,  Math.min(600, carbs));

    if (recCalories) recCalories.textContent = calories;
    if (recProtein)  recProtein.textContent  = protein + 'g';
    if (recFat)      recFat.textContent      = fat + 'g';
    if (recCarbs)    recCarbs.textContent    = carbs + 'g';
  }

  // ── Custom goal toggle switch (water) ──────────────────────────────────────
  let isCustom = false;
  if (switchEl) {
    switchEl.addEventListener('click', () => {
      isCustom = !isCustom;
      // Animate knob
      knobEl.style.transform      = isCustom ? 'translateX(20px)' : 'translateX(0)';
      switchEl.style.background   = isCustom ? '#00696d' : '#e6e8ea';
      toggleLabel.textContent     = isCustom ? 'On' : 'Off';
      toggleLabel.style.color     = isCustom ? '#00696d' : '#3f4852';
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

  // ── Custom nutrition toggle switch ─────────────────────────────────────────
  let isNutriCustom = false;
  if (nutriSwitch) {
    nutriSwitch.addEventListener('click', () => {
      isNutriCustom = !isNutriCustom;
      nutriKnob.style.transform        = isNutriCustom ? 'translateX(20px)' : 'translateX(0)';
      nutriSwitch.style.background     = isNutriCustom ? '#3d6b23' : '#e6e8ea';
      nutriToggleLabel.textContent     = isNutriCustom ? 'On' : 'Off';
      nutriToggleLabel.style.color     = isNutriCustom ? '#3d6b23' : '#3f4852';
      nutriCustomArea.style.display    = isNutriCustom ? 'block' : 'none';

      if (nutriMode) nutriMode.value = isNutriCustom ? 'custom' : 'auto';

      if (isNutriCustom) {
        document.getElementById('custom-calorie-input').value = recCalories.textContent;
        document.getElementById('custom-protein-input').value = recProtein.textContent.replace('g', '');
        document.getElementById('custom-fat-input').value     = recFat.textContent.replace('g', '');
        document.getElementById('custom-carbs-input').value   = recCarbs.textContent.replace('g', '');
      }
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
  weightInput.addEventListener('input',  () => { calcBmi(); calcNutrition(); });
  heightInput.addEventListener('input',  () => { calcBmi(); calcNutrition(); });
  if (ageInput)     ageInput.addEventListener('input', calcNutrition);
  if (activitySel)  activitySel.addEventListener('change', () => { calcBmi(); calcNutrition(); });

  calcBmi();       // Initial calculation on page load
  calcNutrition();
}