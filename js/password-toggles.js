export function initPasswordToggles() {
  const toggleBtns = document.querySelectorAll('.toggle-password');
  toggleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const icon = btn.querySelector('.material-symbols-outlined');
      if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-icon', 'visibility');
      } else {
        input.type = 'password';
        icon.setAttribute('data-icon', 'visibility_off');
      }
    });
  });
}