/* ---------- Client-Side Form Validation (Login & Register) ---------- */
export function initFormHandlers() {

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