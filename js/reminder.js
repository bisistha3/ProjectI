/* ---------- Reminders (Settings toggle + app-wide toast) ---------- */

/**
 * Settings page: animate the Reminders toggle.
 */
export function initReminderToggle() {
  const checkbox  = document.getElementById('reminder-enabled');
  const switchEl  = document.getElementById('reminder-switch');
  const knobEl    = document.getElementById('reminder-knob');
  const labelEl   = document.getElementById('reminder-toggle-label');
  if (!checkbox || !switchEl) return;

  const refresh = () => {
    const on = checkbox.checked;
    switchEl.style.background = on ? '#00696d' : '#e6e8ea';
    if (knobEl) knobEl.style.left = on ? '23px' : '3px';
    if (labelEl) labelEl.textContent = on ? 'On' : 'Off';
  };
  switchEl.addEventListener('click', () => {
    checkbox.checked = !checkbox.checked;
    refresh();
  });
  refresh();
}

/**
 * Settings page: animate the Email Reminder toggle.
 */
export function initEmailReminderToggle() {
  const checkbox  = document.getElementById('email-reminder-enabled');
  const switchEl  = document.getElementById('email-reminder-switch');
  const knobEl    = document.getElementById('email-reminder-knob');
  const labelEl   = document.getElementById('email-reminder-toggle-label');
  if (!checkbox || !switchEl) return;

  const refresh = () => {
    const on = checkbox.checked;
    switchEl.style.background = on ? '#00696d' : '#e6e8ea';
    if (knobEl) knobEl.style.left = on ? '23px' : '3px';
    if (labelEl) labelEl.textContent = on ? 'On' : 'Off';
  };
  switchEl.addEventListener('click', () => {
    checkbox.checked = !checkbox.checked;
    refresh();
  });
  refresh();
}

/**
 * App pages: show recurring reminders while the app is open.
 * Config comes from window.HEALTHFLOW_REMINDER (printed by the PHP pages).
 * interval_min: how often to show the toast (in minutes)
 * Uses localStorage to persist timing across page navigations.
 */
export function initReminderToast() {
  const cfg = window.HEALTHFLOW_REMINDER || { enabled: false, interval_min: 60 };
  if (!cfg.enabled) return;

  const STORAGE_KEY = 'hf_last_toast_at';
  const intervalMs = (Number(cfg.interval_min) || 60) * 60 * 1000;

  const showToast = () => {
    const toast = document.createElement('div');
    toast.id = 'reminder-toast';
    toast.style.cssText = `
      position: fixed; bottom: 32px; left: 50%; transform: translateX(-50%) translateY(20px);
      background: linear-gradient(135deg, #00696d, #00a7ad); color: white;
      padding: 14px 24px; border-radius: 16px; font-weight: 600; font-size: 14px;
      box-shadow: 0 8px 30px rgba(0, 105, 109, 0.35); z-index: 1000;
      display: flex; align-items: center; gap: 12px;
      opacity: 0; transition: opacity 0.4s, transform 0.4s;
    `;
    toast.innerHTML = `
      <span class="material-symbols-outlined" data-icon="notifications_active-filled" style="font-size: 22px"></span>
      <div>
        Time to log your water, meals and exercise!
        <button id="reminder-goto" style="margin-left:8px; background:rgba(255,255,255,0.22); border:none; color:#fff; font-size:12px; font-weight:700; padding:5px 10px; border-radius:8px; cursor:pointer;">Log now</button>
        <button id="reminder-dismiss" style="margin-left:4px; background:transparent; border:none; color:rgba(255,255,255,0.85); font-size:12px; font-weight:600; padding:5px 10px; border-radius:8px; cursor:pointer;">Dismiss</button>
      </div>
    `;
    document.body.appendChild(toast);
    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateX(-50%) translateY(0)';
    });

    const remove = () => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(-50%) translateY(20px)';
      setTimeout(() => toast.remove(), 400);
    };
    toast.querySelector('#reminder-goto')?.addEventListener('click', () => {
      remove();
      window.location.href = 'log.php?type=water';
    });
    toast.querySelector('#reminder-dismiss')?.addEventListener('click', () => {
      remove();
    });
  };

  // Check localStorage for last toast time
  const lastShown = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
  const now = Date.now();

  if (now - lastShown >= intervalMs) {
    showToast();
    localStorage.setItem(STORAGE_KEY, String(now));
  }

  // Also set interval for staying on same page
  setInterval(() => {
    showToast();
    localStorage.setItem(STORAGE_KEY, String(Date.now()));
  }, intervalMs);
}