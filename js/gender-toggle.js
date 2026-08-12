export function initGenderToggle() {
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