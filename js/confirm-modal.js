/**
 * Reusable confirmation modal module.
 * Usage: import { showConfirm } from './confirm-modal.js';
 *        const confirmed = await showConfirm({ message: 'Delete this?', variant: 'danger' });
 */

let modal = null;
let resolvePromise = null;

function ensureModal() {
  if (modal) return;
  modal = document.getElementById('confirm-modal');
  if (!modal) {
    console.warn('Confirm modal not found in DOM');
    return;
  }
  const cancelBtn = modal.querySelector('[data-confirm-cancel]');
  const okBtn = modal.querySelector('#confirm-ok');
  const overlay = modal;

  cancelBtn?.addEventListener('click', () => close(false));
  okBtn?.addEventListener('click', () => close(true));
  overlay?.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
}

function close(confirmed) {
  if (!modal) return;
  modal.hidden = true;
  document.body.style.overflow = '';
  if (resolvePromise) {
    resolvePromise(confirmed);
    resolvePromise = null;
  }
}

function handleKeydown(e) {
  if (e.key === 'Escape') close(false);
}

export function showConfirm(options = {}) {
  return new Promise((resolve) => {
    ensureModal();
    if (!modal) {
      resolve(window.confirm(options.message || 'Confirm?'));
      return;
    }

    resolvePromise = resolve;

    const {
      message = 'Are you sure?',
      title = 'Confirm',
      confirmText = 'Confirm',
      cancelText = 'Cancel',
      variant = 'primary'
    } = options;

    modal.querySelector('#confirm-title').textContent = title;
    modal.querySelector('#confirm-message').textContent = message;
    modal.querySelector('#confirm-ok').textContent = confirmText;
    modal.querySelector('[data-confirm-cancel]').textContent = cancelText;

    const okBtn = modal.querySelector('#confirm-ok');
    okBtn.classList.remove('btn-primary', 'btn-danger');
    okBtn.classList.add(variant === 'danger' ? 'btn-danger' : 'btn-primary');

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    okBtn.focus();

    document.addEventListener('keydown', handleKeydown, { once: true });
  });
}