

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

/* ---------- Password Visibility Toggle -------- */
function initPasswordToggles() {
  const toggleBtns = document.querySelectorAll('.toggle-password');
  toggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const icon = btn.querySelector('.material-symbols-outlined');
      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility';
      } else {
        input.type = 'password';
        icon.textContent = 'visibility_off';
      }
    });
  });
}

/* ---------- Gender Toggle (Register Page) ---------- */
function initGenderToggle() {
  const toggle = document.getElementById('gender-toggle');
  if (!toggle) return;

  const slider = document.getElementById('gender-slider');
  const labelMale = document.getElementById('label-male');
  const labelFemale = document.getElementById('label-female');
  const radios = toggle.querySelectorAll('input[name="gender"]');

  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      if (radio.value === 'male') {
        slider.style.transform = 'translateX(0)';
        labelMale.classList.add('active');
        labelFemale.classList.remove('active');
      } else {
        slider.style.transform = 'translateX(100%)';
        labelFemale.classList.add('active');
        labelMale.classList.remove('active');
      }
    });
  });
}

/* ---------- Mobile Sidebar ---------- */
function initMobileSidebar() {
  const hamburger = document.getElementById('hamburger');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  if (!hamburger || !sidebar) return;

  hamburger.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active');
  });

  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
  }
}

/* ---------- Quick Log (Dashboard) ---------- */
function initQuickLog() {
  const quickBtns = document.querySelectorAll('.quick-log__btn');
  if (quickBtns.length === 0) return;

  const goalMl = 2500;
  let consumedMl = 1620; // initial state: 1.62L

  const gaugeFill = document.getElementById('gauge-fill');
  const gaugePercent = document.getElementById('gauge-percent-value');
  const totalConsumed = document.getElementById('total-consumed');
  const totalRemaining = document.getElementById('total-remaining');
  const logList = document.getElementById('log-list');

  quickBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const amount = parseInt(btn.getAttribute('data-amount'), 10);
      consumedMl = Math.min(consumedMl + amount, goalMl);

      // Update gauge
      const percent = Math.round((consumedMl / goalMl) * 100);
      if (gaugeFill) gaugeFill.style.height = percent + '%';
      if (gaugePercent) gaugePercent.textContent = percent;

      // Update stats
      if (totalConsumed) totalConsumed.textContent = (consumedMl / 1000).toFixed(2) + 'L';
      const remaining = Math.max(goalMl - consumedMl, 0);
      if (totalRemaining) totalRemaining.textContent = (remaining / 1000).toFixed(2) + 'L';

      // Add ripple animation to button
      btn.style.transform = 'scale(0.92)';
      setTimeout(() => { btn.style.transform = ''; }, 150);

      // Prepend new log entry
      if (logList) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

        const icons = { 250: 'water_bottle', 500: 'water_drop', 750: 'local_drink' };
        const labels = { 250: 'Glass of Water', 500: 'Water', 750: 'Bottle' };

        const logItem = document.createElement('div');
        logItem.className = 'log-item';
        logItem.style.opacity = '0';
        logItem.style.transform = 'translateY(-8px)';
        logItem.innerHTML = `
          <div class="log-item__left">
            <div class="log-item__avatar">
              <span class="material-symbols-outlined" style="font-size: 18px;">${icons[amount] || 'water_drop'}</span>
            </div>
            <div>
              <p class="log-item__amount">${amount}ml</p>
              <p class="log-item__desc">${labels[amount] || 'Water'}</p>
            </div>
          </div>
          <span class="log-item__time">${timeStr}</span>
        `;

        logList.prepend(logItem);

        // Animate in
        requestAnimationFrame(() => {
          logItem.style.transition = 'opacity 0.3s, transform 0.3s';
          logItem.style.opacity = '1';
          logItem.style.transform = 'translateY(0)';
        });
      }

      // Celebrate hitting 100%
      if (consumedMl >= goalMl) {
        showGoalAchieved();
      }
    });
  });
}

/* ---------- Goal Achieved Celebration ---------- */
function showGoalAchieved() {
  // Prevent showing multiple times
  if (document.getElementById('goal-toast')) return;

  const toast = document.createElement('div');
  toast.id = 'goal-toast';
  toast.style.cssText = `
    position: fixed; bottom: 32px; left: 50%; transform: translateX(-50%) translateY(20px);
    background: linear-gradient(135deg, #00629d, #00a3ff); color: white;
    padding: 16px 32px; border-radius: 9999px; font-weight: 600; font-size: 14px;
    box-shadow: 0 8px 30px rgba(0, 98, 157, 0.35); z-index: 100;
    display: flex; align-items: center; gap: 8px;
    opacity: 0; transition: opacity 0.4s, transform 0.4s;
  `;
  toast.innerHTML = `
    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1; font-size: 20px;">emoji_events</span>
    Daily goal achieved! Great job staying hydrated! 🎉
  `;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
  });

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(-50%) translateY(20px)';
    setTimeout(() => toast.remove(), 400);
  }, 4000);
}

/* ---------- Goal Calculator (Settings) — BMI-based ---------- */
function initGoalCalculator() {
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

/* ---------- Settings Actions ---------- */
function initSettingsActions() {
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
      // Reset form fields
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

      // Recalculate goal
      initGoalCalculator();

      // Visual feedback
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

/* ---------- Client-Side Form Validation (Login & Register) ---------- */
function initFormHandlers() {

  // --- Validation helpers ---
  function showError(input, msg) {
    input.classList.add('input-field--error');
    // Remove any existing error
    const existing = input.closest('.input-group')?.parentElement?.querySelector('.field-error')
                  || input.parentElement?.querySelector('.field-error');
    if (existing) existing.remove();
    const span = document.createElement('span');
    span.className = 'field-error';
    span.textContent = msg;
    const parent = input.closest('.input-group') || input.parentElement;
    parent.insertAdjacentElement('afterend', span);
  }

  function clearError(input) {
    input.classList.remove('input-field--error');
    const parent = input.closest('.input-group') || input.parentElement;
    const err = parent?.nextElementSibling;
    if (err && err.classList.contains('field-error')) err.remove();
  }

  function isValidEmail(email) {
    return /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+$/.test(email);
  }

  // --- Real-time: clear error on input ---
  document.querySelectorAll('.input-field').forEach(input => {
    input.addEventListener('input', () => clearError(input));
  });

  // === LOGIN FORM ===
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      let valid = true;
      const emailInput = document.getElementById('email');
      const passInput  = document.getElementById('password');

      // Clear previous JS errors
      [emailInput, passInput].forEach(clearError);

      if (!emailInput.value.trim()) {
        showError(emailInput, 'Email is required.');
        valid = false;
      } else if (!isValidEmail(emailInput.value.trim())) {
        showError(emailInput, 'Please enter a valid email address.');
        valid = false;
      }

      if (!passInput.value) {
        showError(passInput, 'Password is required.');
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
        loginForm.classList.add('shake');
        setTimeout(() => loginForm.classList.remove('shake'), 400);
      }
      // If valid, form submits normally to PHP via POST
    });
  }

  // === REGISTER FORM ===
  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', (e) => {
      let valid = true;
      const fields = {
        name:     document.getElementById('name'),
        email:    document.getElementById('reg-email'),
        password: document.getElementById('reg-password'),
        age:      document.getElementById('age'),
        weight:   document.getElementById('weight'),
        height:   document.getElementById('height'),
      };

      // Clear all previous errors
      Object.values(fields).forEach(f => { if (f) clearError(f); });

      // Full Name
      if (!fields.name?.value.trim()) {
        showError(fields.name, 'Full Name is required.');
        valid = false;
      } else if (fields.name.value.trim().length < 2) {
        showError(fields.name, 'Full Name must be at least 2 characters.');
        valid = false;
      }

      // Email
      if (!fields.email?.value.trim()) {
        showError(fields.email, 'Email is required.');
        valid = false;
      } else if (!isValidEmail(fields.email.value.trim())) {
        showError(fields.email, 'Please enter a valid email address.');
        valid = false;
      }

      // Password
      const pw = fields.password?.value || '';
      if (!pw) {
        showError(fields.password, 'Password is required.');
        valid = false;
      } else if (pw.length < 8) {
        showError(fields.password, 'Password must be at least 8 characters.');
        valid = false;
      } else if (!/[A-Z]/.test(pw)) {
        showError(fields.password, 'Password must contain at least one uppercase letter.');
        valid = false;
      } else if (!/[a-z]/.test(pw)) {
        showError(fields.password, 'Password must contain at least one lowercase letter.');
        valid = false;
      } else if (!/[0-9]/.test(pw)) {
        showError(fields.password, 'Password must contain at least one digit.');
        valid = false;
      }

      // Age
      const age = parseInt(fields.age?.value, 10);
      if (!fields.age?.value.trim()) {
        showError(fields.age, 'Age is required.');
        valid = false;
      } else if (isNaN(age) || age < 1 || age > 120) {
        showError(fields.age, 'Age must be between 1 and 120.');
        valid = false;
      }

      // Weight
      const weight = parseFloat(fields.weight?.value);
      if (!fields.weight?.value.trim()) {
        showError(fields.weight, 'Weight is required.');
        valid = false;
      } else if (isNaN(weight) || weight < 1 || weight > 300) {
        showError(fields.weight, 'Weight must be between 1 and 300 kg.');
        valid = false;
      }

      // Height
      const height = parseFloat(fields.height?.value);
      if (!fields.height?.value.trim()) {
        showError(fields.height, 'Height is required.');
        valid = false;
      } else if (isNaN(height) || height < 50 || height > 250) {
        showError(fields.height, 'Height must be between 50 and 250 cm.');
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
        registerForm.classList.add('shake');
        setTimeout(() => registerForm.classList.remove('shake'), 400);
      }
      // If valid, form submits normally to PHP via POST
    });
  }
}

/* ---------- Chart Toggle (History) ---------- */
function initChartToggle() {
  const toggleBtns = document.querySelectorAll('.chart-toggle__btn');
  if (toggleBtns.length === 0) return;

  toggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      toggleBtns.forEach(b => b.classList.remove('chart-toggle__btn--active'));
      btn.classList.add('chart-toggle__btn--active');
    });
  });
}

/* ---------- Calendar Navigation (History) ---------- */
function initCalendarNav() {
  const prevBtn = document.getElementById('cal-prev');
  const nextBtn = document.getElementById('cal-next');
  const monthLabel = document.getElementById('cal-month');

  if (!prevBtn || !nextBtn || !monthLabel) return;

  const months = ['January', 'February', 'March', 'April', 'May', 'June',
                  'July', 'August', 'September', 'October', 'November', 'December'];
  let currentIndex = months.indexOf(monthLabel.textContent);
  if (currentIndex === -1) currentIndex = 6; // default July

  function update() {
    monthLabel.textContent = months[currentIndex];
    // subtle animation
    monthLabel.style.opacity = '0';
    monthLabel.style.transition = 'opacity 0.2s';
    requestAnimationFrame(() => { monthLabel.style.opacity = '1'; });
  }

  prevBtn.addEventListener('click', () => {
    currentIndex = (currentIndex - 1 + 12) % 12;
    update();
  });

  nextBtn.addEventListener('click', () => {
    currentIndex = (currentIndex + 1) % 12;
    update();
  });
}

/* ---------- Bar Chart Tooltips (History) ---------- */
function initBarTooltips() {
  const bars = document.querySelectorAll('.bar-col');
  if (bars.length === 0) return;

  const goalL = 2.5;
  bars.forEach(col => {
    const value = parseInt(col.getAttribute('data-value'), 10);
    if (isNaN(value)) return;

    const litres = (goalL * value / 100).toFixed(1);
    const bar = col.querySelector('.bar');
    if (!bar) return;

    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.textContent = litres + 'L';
    tooltip.style.cssText = `
      position: absolute; top: -28px; left: 50%; transform: translateX(-50%);
      background: var(--color-inverse-surface, #2d3133); color: white;
      font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px;
      pointer-events: none; opacity: 0; transition: opacity 0.2s;
      white-space: nowrap; z-index: 5;
    `;
    bar.style.position = 'relative';
    bar.appendChild(tooltip);

    col.addEventListener('mouseenter', () => { tooltip.style.opacity = '1'; });
    col.addEventListener('mouseleave', () => { tooltip.style.opacity = '0'; });
  });
}
