/* ---------- Reminders (Settings toggle + app-wide toast) ---------- */

/**
 * Settings page: animate the Reminders toggle and show/hide the time input.
 */
export function initReminderToggle() {
  const checkbox  = document.getElementById('reminder-enabled');
  const switchEl  = document.getElementById('reminder-switch');
  const knobEl    = document.getElementById('reminder-knob');
  const labelEl   = document.getElementById('reminder-toggle-label');
  const timeArea  = document.getElementById('reminder-time-area');
  const interval  = document.getElementById('reminder-interval');
  if (!checkbox || !switchEl) return;

  const showTimeArea = () => {
    const on       = checkbox.checked;
    const isCustom = !interval || Number(interval.value) === 0;
    if (timeArea) timeArea.style.display = on && isCustom ? '' : 'none';
  };
  const refresh = () => {
    const on = checkbox.checked;
    switchEl.style.background = on ? 'var(--color-primary)' : 'var(--color-surface-container-high)';
    if (knobEl) knobEl.style.left = on ? '23px' : '3px';
    if (labelEl) labelEl.textContent = on ? 'On' : 'Off';
    showTimeArea();
  };
  switchEl.addEventListener('click', () => {
    checkbox.checked = !checkbox.checked;
    refresh();
  });
  if (interval) interval.addEventListener('change', showTimeArea);
  refresh();
}

/**
 * App pages: show recurring reminders while the app is open.
 * Config comes from window.HEALTHFLOW_REMINDER (printed by the PHP pages).
 * - interval_min > 0 : "Every 1/2/3 hours" — toast on load, then every interval
 * - interval_min = 0 : "Custom time" — one-time daily toast once the time is reached
 */
export function initReminderToast() {
  const cfg = window.HEALTHFLOW_REMINDER || { enabled: false, time: '20:00', interval_min: 0 };
  if (!cfg.enabled) return;

  const showToast = (onceKey) => {
    if (onceKey && localStorage.getItem(onceKey)) return;

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
      <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1; font-size: 22px;">notifications_active</span>
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
      if (onceKey) localStorage.setItem(onceKey, '1');
      remove();
      window.location.href = 'log.php?type=water';
    });
    toast.querySelector('#reminder-dismiss')?.addEventListener('click', () => {
      if (onceKey) localStorage.setItem(onceKey, '1');
      remove();
    });
  };

  const intervalMin = Number(cfg.interval_min) || 0;
  if (intervalMin > 0) {
    // Recurring interval reminders while the app is open
    showToast(null);
    setInterval(() => showToast(null), intervalMin * 60 * 1000);
    return;
  }

  // Custom once-daily time reminder
  if (!cfg.time) return;
  const today = new Date().toISOString().slice(0, 10);
  const onceKey = 'hf_reminder_dismissed_' + today;
  if (localStorage.getItem(onceKey)) return;

  const [h, m] = cfg.time.split(':').map(Number);
  const now = new Date();
  const reminderAt = new Date(now);
  reminderAt.setHours(h || 20, m || 0, 0, 0);

  if (now < reminderAt) return;
  showToast(onceKey);
}