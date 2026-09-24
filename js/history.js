export function initChartToggle() {
  const toggleContainers = document.querySelectorAll('.chart-toggle');
  if (toggleContainers.length === 0) return;

  toggleContainers.forEach(container => {
    const btns = container.querySelectorAll('.chart-toggle__btn');
    btns.forEach(btn => {
      btn.addEventListener('click', () => {
        btns.forEach(b => b.classList.remove('chart-toggle__btn--active'));
        btn.classList.add('chart-toggle__btn--active');
      });
    });
  });
}

let monthLoadInFlight = false;
let popstateBound = false;

function rebindAfterMainSwap() {
  initCalendarNav();
  initBarTooltips();
  initChartToggle();
}

async function swapMainFromUrl(url, { push = false } = {}) {
  const curMain = document.querySelector('main.app-content');
  if (!curMain) {
    window.location.href = url;
    return;
  }

  const res = await fetch(url, { credentials: 'same-origin' });
  if (!res.ok) throw new Error('HTTP ' + res.status);

  const html = await res.text();
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const newMain = doc.querySelector('main.app-content');

  // Login redirect, error page, or unexpected body — fall back to full navigation.
  if (!newMain || !newMain.querySelector('#cal-prev')) {
    window.location.href = url;
    return;
  }

  curMain.replaceWith(newMain);
  if (push) window.history.pushState({ hfHistoryMonth: true }, '', url);

  rebindAfterMainSwap();

  const label = document.getElementById('cal-month');
  if (label) {
    label.style.transition = 'opacity 0.15s';
    label.style.opacity = '0';
    requestAnimationFrame(() => { label.style.opacity = '1'; });
  }
}

function bindPopstateOnce() {
  if (popstateBound) return;
  popstateBound = true;

  window.addEventListener('popstate', async () => {
    if (!document.getElementById('cal-month')) return;
    if (monthLoadInFlight) return;

    monthLoadInFlight = true;
    const label = document.getElementById('cal-month');
    if (label) {
      label.style.transition = 'opacity 0.15s';
      label.style.opacity = '0';
    }

    try {
      await swapMainFromUrl(window.location.href, { push: false });
    } catch {
      if (label) label.style.opacity = '1';
    } finally {
      monthLoadInFlight = false;
    }
  });
}

export function initCalendarNav() {
  const prevBtn = document.getElementById('cal-prev');
  const nextBtn = document.getElementById('cal-next');
  const monthLabel = document.getElementById('cal-month');

  if (!prevBtn || !nextBtn || !monthLabel) return;

  // Server-rendered anchors keep working without JS (and as fetch fallback).
  // With JS: fetch the target month, swap <main> in place, pushState — no full reload.
  const go = async (btn) => {
    const href = btn.getAttribute('href');
    if (!href || monthLoadInFlight) return;

    monthLoadInFlight = true;
    prevBtn.setAttribute('aria-disabled', 'true');
    nextBtn.setAttribute('aria-disabled', 'true');
    monthLabel.style.transition = 'opacity 0.15s';
    monthLabel.style.opacity = '0';

    try {
      await swapMainFromUrl(href, { push: true });
    } catch {
      window.location.href = href;
    } finally {
      monthLoadInFlight = false;
    }
  };

  prevBtn.addEventListener('click', (e) => {
    e.preventDefault();
    go(prevBtn);
  });

  nextBtn.addEventListener('click', (e) => {
    e.preventDefault();
    go(nextBtn);
  });

  bindPopstateOnce();
}

export function initBarTooltips() {
  const bars = document.querySelectorAll('.bar-col');
  if (bars.length === 0) return;

  bars.forEach(col => {
    if (col.dataset.tooltipBound) return;
    col.dataset.tooltipBound = '1';

    const value = parseInt(col.getAttribute('data-value'), 10);
    if (isNaN(value)) return;

    const goal = parseFloat(col.getAttribute('data-goal')) || 100;
    const unit = col.getAttribute('data-unit') || '';
    let shown = goal * value / 100;
    if (unit === 'L') shown = shown.toFixed(1);
    else shown = Math.round(shown);

    const bar = col.querySelector('.bar');
    if (!bar) return;

    const tooltip = document.createElement('div');
    tooltip.textContent = shown + unit;
    tooltip.style.cssText = `
      position: absolute; top: -28px; left: 50%; transform: translateX(-50%);
      background: #2d3133; color: white;
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
