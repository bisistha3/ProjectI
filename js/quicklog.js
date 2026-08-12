export function initQuickLog() {
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