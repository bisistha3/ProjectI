export function initFormHandlers() {

  function showError(input, msg) {
    input.classList.add('input-field--error');
    input.classList.remove('input-field--success');
    removeSuccessIcon(input);
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

  function showSuccess(input) {
    input.classList.remove('input-field--error');
    input.classList.add('input-field--success');
    clearError(input);
    addSuccessIcon(input);
  }

  function addSuccessIcon(input) {
    if (input.classList.contains('input-field--icon-right') && input.type !== 'password') {
      const group = input.closest('.input-group');
      if (group && !group.querySelector('.field-success')) {
        const icon = document.createElement('span');
        icon.className = 'material-symbols-outlined field-success';
        icon.textContent = 'check_circle';
        group.appendChild(icon);
      }
    }
  }

  function removeSuccessIcon(input) {
    const group = input.closest('.input-group');
    const icon = group?.querySelector('.field-success');
    if (icon) icon.remove();
  }

  function clearAllStates(input) {
    input.classList.remove('input-field--error', 'input-field--success');
    removeSuccessIcon(input);
    clearError(input);
  }

  function isValidEmail(email) {
    return /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+$/.test(email);
  }

  // Validators (matching PHP rules exactly)
  function validateName(value) {
    const v = value.trim();
    if (!v) return { valid: true }; // empty handled on blur/submit
    if (v.length < 2) return { valid: false, msg: 'Full Name must be at least 2 characters.' };
    if (!/^[\p{L}]/u.test(v)) return { valid: false, msg: 'Full Name cannot start with a number.' };
    if (!/^[\p{L}][\p{L}\s'\-]*$/u.test(v)) return { valid: false, msg: 'Full Name can only contain letters, spaces, apostrophes and hyphens.' };
    return { valid: true };
  }

  function validateEmail(value) {
    const v = value.trim();
    if (!v) return { valid: true };
    if (!isValidEmail(v)) return { valid: false, msg: 'Please enter a valid email address.' };
    return { valid: true };
  }

  function validatePassword(value) {
    const v = value;
    if (!v) return { valid: true };
    if (v.length < 8) return { valid: false, msg: 'Password must be at least 8 characters.' };
    if (!/[A-Z]/.test(v)) return { valid: false, msg: 'Password must contain at least one uppercase letter.' };
    if (!/[a-z]/.test(v)) return { valid: false, msg: 'Password must contain at least one lowercase letter.' };
    if (!/[0-9]/.test(v)) return { valid: false, msg: 'Password must contain at least one digit.' };
    return { valid: true };
  }

  function validateAge(value) {
    const v = value.trim();
    if (!v) return { valid: true };
    const n = parseInt(v, 10);
    if (isNaN(n) || n < 1 || n > 120) return { valid: false, msg: 'Age must be between 1 and 120.' };
    return { valid: true };
  }

  function validateWeight(value) {
    const v = value.trim();
    if (!v) return { valid: true };
    const n = parseFloat(v);
    if (isNaN(n) || n < 1 || n > 300) return { valid: false, msg: 'Weight must be between 1 and 300 kg.' };
    return { valid: true };
  }

  function validateHeight(value) {
    const v = value.trim();
    if (!v) return { valid: true };
    const n = parseFloat(v);
    if (isNaN(n) || n < 50 || n > 250) return { valid: false, msg: 'Height must be between 50 and 250 cm.' };
    return { valid: true };
  }

  

  function debounce(fn, ms) {
    let timeoutId;
    return (...args) => {
      clearTimeout(timeoutId);
      timeoutId = setTimeout(() => fn(...args), ms);
    };
  }

  function validateField(input, validator, checkRequired = false) {
    const value = input.value;
    const result = validator(value);

    if (checkRequired && !value.trim()) {
      showError(input, `${getFieldLabel(input)} is required.`);
      return false;
    }

    if (!result.valid) {
      showError(input, result.msg);
      return false;
    }

    if (value.trim()) {
      showSuccess(input);
    } else {
      clearAllStates(input);
    }
    return true;
  }

  function getFieldLabel(input) {
    const label = document.querySelector(`label[for="${input.id}"]`);
    return label ? label.textContent.replace('*', '').trim() : 'Field';
  }

  // Attach real-time validation to all input fields
  const validators = {
    name:     validateName,
    email:    validateEmail,
    password: validatePassword,
    age:      validateAge,
    weight:   validateWeight,
    height:   validateHeight,
  };

  document.querySelectorAll('.input-field').forEach(input => {
    const key = input.id.replace('reg-', ''); // normalize reg-email -> email
    const validator = validators[key];
    if (!validator) return;

    const debouncedValidate = debounce(() => validateField(input, validator), 300);
    input.addEventListener('input', debouncedValidate);

    input.addEventListener('blur', () => validateField(input, validator, true));
  });

  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      const emailInput = document.getElementById('email');
      const passInput  = document.getElementById('password');

      const emailValid = validateField(emailInput, validateEmail, true);
      const passValid  = validateField(passInput, validatePassword, true);

      if (!emailValid || !passValid) {
        e.preventDefault();
        loginForm.classList.add('shake');
        setTimeout(() => loginForm.classList.remove('shake'), 400);
      }
    });
  }

  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', (e) => {
      const fields = {
        name:     document.getElementById('name'),
        email:    document.getElementById('reg-email'),
        password: document.getElementById('reg-password'),
        age:      document.getElementById('age'),
        weight:   document.getElementById('weight'),
        height:   document.getElementById('height'),
      };

      const nameValid     = validateField(fields.name, validateName, true);
      const emailValid    = validateField(fields.email, validateEmail, true);
      const passwordValid = validateField(fields.password, validatePassword, true);
      const ageValid      = validateField(fields.age, validateAge, true);
      const weightValid   = validateField(fields.weight, validateWeight, true);
      const heightValid   = validateField(fields.height, validateHeight, true);

      if (!(nameValid && emailValid && passwordValid && ageValid && weightValid && heightValid)) {
        e.preventDefault();
        registerForm.classList.add('shake');
        setTimeout(() => registerForm.classList.remove('shake'), 400);
      }
    });
  }
}