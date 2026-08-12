/* ---------- Chart Toggle (History) ---------- */
export function initChartToggle() {
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
export function initCalendarNav() {
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
export function initBarTooltips() {
  const bars = document.querySelectorAll('.bar-col');
  if (bars.length === 0) return;

  bars.forEach(col => {
    const value = parseInt(col.getAttribute('data-value'), 10);
    if (isNaN(value)) return;

    const goal = parseFloat(col.getAttribute('data-goal')) || 100;
    const unit = col.getAttribute('data-unit') || '';
    let shown = goal * value / 100;
    if (unit === 'L') shown = shown.toFixed(1);
    else shown = Math.round(shown);

    const bar = col.querySelector('.bar');
    if (!bar) return;

    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.textContent = shown + unit;
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