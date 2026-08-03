<?php
/**
 * HydroFlow — History Page (server-side rendered)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// ── User goal ─────────────────────────────────────────────────────────────────
$u = $db->prepare('SELECT full_name, daily_goal_ml FROM users WHERE user_id=?');
$u->execute([$userId]);
$user     = $u->fetch();
$goalMl   = (int)($user['daily_goal_ml'] ?? 2500);
$goalL    = $goalMl / 1000;
$goalLabel = number_format($goalL, 1) . 'L';
$fullName  = htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');

// ── Last 7 days data ──────────────────────────────────────────────────────────
$weekly = $db->prepare('
    SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
    FROM water_logs
    WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(logged_at)
    ORDER BY day ASC
');
$weekly->execute([$userId]);
$weeklyRaw = $weekly->fetchAll(PDO::FETCH_KEY_PAIR); // [date => ml]

// Build 7-day array always showing all 7 days
$weekDays = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $ml   = (int)($weeklyRaw[$date] ?? 0);
    $weekDays[] = [
        'date'    => $date,
        'label'   => date('D', strtotime($date)), // Mon, Tue...
        'ml'      => $ml,
        'pct'     => $goalMl > 0 ? min(100, round($ml / $goalMl * 100)) : 0,
        'is_today'=> $date === date('Y-m-d'),
    ];
}

// ── Metrics ───────────────────────────────────────────────────────────────────
$metricsQ = $db->prepare('
    SELECT
        ROUND(AVG(daily_total)/1000, 1)   AS avg_l,
        ROUND(MAX(daily_total)/1000, 1)   AS best_l,
        ROUND(SUM(daily_total)/1000, 1)   AS total_l
    FROM (
        SELECT DATE(logged_at) AS d, SUM(amount_ml) AS daily_total
        FROM water_logs WHERE user_id=?
        GROUP BY DATE(logged_at)
    ) sub
');
$metricsQ->execute([$userId]);
$metrics = $metricsQ->fetch();

// ── Streak ────────────────────────────────────────────────────────────────────
$streakQ = $db->prepare('
    SELECT DATE(logged_at) AS day FROM water_logs
    WHERE user_id=? GROUP BY DATE(logged_at) ORDER BY day DESC
');
$streakQ->execute([$userId]);
$days   = $streakQ->fetchAll(PDO::FETCH_COLUMN);
$streak = 0;
$check  = new DateTime('today');
foreach ($days as $day) {
    if ($day === $check->format('Y-m-d')) { $streak++; $check->modify('-1 day'); }
    else break;
}

// ── Last 7 days table rows ────────────────────────────────────────────────────
$tableQ = $db->prepare('
    SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml,
           MAX(drink_type) AS top_source
    FROM water_logs
    WHERE user_id=? AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(logged_at)
    ORDER BY day DESC
');
$tableQ->execute([$userId]);
$tableRows = $tableQ->fetchAll();

// ── Current month streak calendar ─────────────────────────────────────────────
$calQ = $db->prepare('
    SELECT DATE(logged_at) AS day, SUM(amount_ml) AS total_ml
    FROM water_logs
    WHERE user_id=? AND YEAR(logged_at)=YEAR(CURDATE()) AND MONTH(logged_at)=MONTH(CURDATE())
    GROUP BY DATE(logged_at)
');
$calQ->execute([$userId]);
$calData = $calQ->fetchAll(PDO::FETCH_KEY_PAIR); // [date => ml]
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>History - HydroFlow</title>
  <meta name="description" content="View your hydration history, weekly trends, streak calendar, and daily intake logs.">
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="app-layout" style="display: flex; flex-direction: column;">

  <button class="hamburger" id="hamburger" aria-label="Toggle navigation">
    <span class="material-symbols-outlined">menu</span>
  </button>
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
      <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">water_drop</span>
      HydroFlow
    </div>
    <div class="sidebar__profile">
      <div class="sidebar__avatar-initials"><?= mb_strtoupper(mb_substr($fullName, 0, 1)) ?></div>
      <div>
        <p class="sidebar__profile-name"><?= $fullName ?></p>
        <p class="sidebar__profile-goal">Daily Goal: <?= $goalLabel ?></p>
      </div>
    </div>
    <nav class="sidebar__nav">
      <a href="dashboard.php" class="nav-item"><span class="material-symbols-outlined">dashboard</span>Dashboard</a>
      <a href="history.php" class="nav-item nav-item--active"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">history</span>History</a>
      <a href="settings.php" class="nav-item"><span class="material-symbols-outlined">settings</span>Settings</a>
    </nav>
    <div class="sidebar__footer">
      <a href="logout.php" class="nav-item" style="color: var(--color-error);">
        <span class="material-symbols-outlined">logout</span>Logout
      </a>
    </div>
  </aside>

  <header class="topnav" id="topnav">
    <div style="flex:1;"></div>
    <div class="topnav__actions">
      <span class="topnav__greeting">Hello, <?= $fullName ?>!</span>
      <a href="logout.php" class="topnav__icon-btn" aria-label="Logout" title="Logout">
        <span class="material-symbols-outlined">logout</span>
      </a>
      <a href="dashboard.php" class="btn-primary btn-sm" style="margin-left:8px; text-decoration:none;">
        <span class="material-symbols-outlined" style="font-size:18px;">water_drop</span>
        Log Water
      </a>
    </div>
  </header>

  <main class="app-content" style="flex-grow:1; gap:32px; padding-bottom:48px;">

    <h1 class="text-headline-lg history-desktop-title" style="color:var(--color-on-surface);">Hydration History</h1>

    <!-- Key Metrics Cards -->
    <section class="metric-grid">
      <div class="glass-card metric-card">
        <div class="metric-card__header">
          <span class="material-symbols-outlined" style="color:var(--color-primary);font-variation-settings:'FILL' 1;">water_ph</span>
        </div>
        <div class="metric-card__body">
          <h3 class="metric-card__label">Avg. Daily Intake</h3>
          <p class="metric-card__value" style="color:var(--color-primary);">
            <?= $metrics['avg_l'] ?? '0.0' ?><span class="metric-card__unit">L</span>
          </p>
        </div>
      </div>
      <div class="glass-card metric-card">
        <div class="metric-card__header">
          <span class="material-symbols-outlined" style="color:var(--color-tertiary-container);font-variation-settings:'FILL' 1;">calendar_month</span>
        </div>
        <div class="metric-card__body">
          <h3 class="metric-card__label">Best Day</h3>
          <p class="metric-card__value"><?= $metrics['best_l'] ?? '0.0' ?><span class="metric-card__unit">L</span></p>
        </div>
      </div>
      <div class="glass-card metric-card">
        <div class="metric-card__header">
          <span class="material-symbols-outlined" style="color:var(--color-secondary);font-variation-settings:'FILL' 1;">bar_chart</span>
          <span class="metric-badge metric-badge--muted"><?= date('M Y') ?></span>
        </div>
        <div class="metric-card__body">
          <h3 class="metric-card__label">Total Consumed</h3>
          <p class="metric-card__value"><?= $metrics['total_l'] ?? '0.0' ?><span class="metric-card__unit">L</span></p>
        </div>
      </div>
    </section>

    <!-- Charts Section -->
    <section class="charts-grid">
      <!-- Weekly Bar Chart -->
      <div class="glass-card chart-card chart-card--wide">
        <div class="chart-card__header">
          <h3 class="text-headline-md" style="color:var(--color-on-surface);">Last 7 Days</h3>
        </div>
        <div class="bar-chart">
          <div class="bar-chart__goal-line"></div>
          <?php foreach ($weekDays as $d): ?>
          <div class="bar-col <?= $d['is_today'] ? 'bar-col--active' : '' ?>" data-value="<?= $d['pct'] ?>" data-label="<?= htmlspecialchars($d['label']) ?>">
            <div class="bar <?= $d['pct'] >= 100 ? 'bar--filled' : '' ?>" style="height:<?= max(2, $d['pct']) ?>%;"></div>
            <span class="bar-label" <?= $d['is_today'] ? 'style="color:var(--color-primary);"' : '' ?>><?= $d['label'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="chart-legend">
          <div class="chart-legend__dot"></div>
          <span class="text-label-md text-on-surface-variant" style="font-size:12px;">Goal achieved (<?= $goalLabel ?>+)</span>
        </div>
      </div>

      <!-- Streak Calendar -->
      <div class="glass-card calendar-card">
        <div class="calendar-card__header">
          <h3 class="text-headline-md" style="color:var(--color-on-surface);">Streak Calendar</h3>
          <span class="text-label-md" style="color:var(--color-on-surface);"><?= date('F Y') ?></span>
        </div>
        <div class="cal-grid cal-grid--header">
          <span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span>
        </div>
        <div class="cal-grid cal-grid--days">
          <?php
          $firstDay    = (int)date('w', strtotime(date('Y-m-01'))); // 0=Sun
          $daysInMonth = (int)date('t');
          $today       = (int)date('j');
          // Empty cells before first day
          for ($i = 0; $i < $firstDay; $i++) echo '<span class="cal-day cal-day--faded"></span>';
          // Day cells
          for ($d = 1; $d <= $daysInMonth; $d++) {
              $dateStr = date('Y-m') . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
              $ml      = (int)($calData[$dateStr] ?? 0);
              $isFuture= $d > $today;
              $isToday = $d === $today;
              $metGoal = $ml >= $goalMl;
              if ($isFuture)    $cls = 'cal-day__dot--future';
              elseif ($isToday) $cls = $metGoal ? 'cal-day__dot--filled' : 'cal-day__dot--today';
              elseif ($metGoal) $cls = 'cal-day__dot--filled';
              elseif ($ml > 0)  $cls = 'cal-day__dot--missed'; // logged but didn't hit goal
              else              $cls = 'cal-day__dot--missed';
              echo "<span class=\"cal-day\"><div class=\"cal-day__dot $cls\">$d</div></span>";
          }
          ?>
        </div>
        <div class="streak-info">
          <div class="streak-info__left">
            <span class="material-symbols-outlined" style="color:var(--color-primary);font-variation-settings:'FILL' 1;">local_fire_department</span>
            <div>
              <p class="text-label-md" style="color:var(--color-on-surface);">Current Streak</p>
              <p style="font-size:10px;color:var(--color-on-surface-variant);"><?= $streak ?> day<?= $streak !== 1 ? 's' : '' ?> in a row</p>
            </div>
          </div>
          <span class="text-headline-md text-primary"><?= str_pad($streak, 2, '0', STR_PAD_LEFT) ?></span>
        </div>
      </div>
    </section>

    <!-- Daily Log Table -->
    <section class="glass-card table-card">
      <h3 class="text-headline-md mb-6" style="color:var(--color-on-surface);">Daily Log: Last 7 Days</h3>
      <div class="table-wrapper">
        <table class="history-table">
          <thead>
            <tr>
              <th>DATE</th>
              <th>TOTAL INTAKE</th>
              <th style="width:25%;">GOAL PROGRESS</th>
              <th>TOP SOURCE</th>
              <th style="text-align:right;">STATUS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($tableRows)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--color-on-surface-variant);padding:32px;">No data yet — start logging water!</td></tr>
            <?php else: ?>
            <?php foreach ($tableRows as $row):
              $ml   = (int)$row['total_ml'];
              $pct  = $goalMl > 0 ? min(100, round($ml / $goalMl * 100)) : 0;
              $lStr = number_format($ml / 1000, 1) . 'L';
              $isToday = $row['day'] === date('Y-m-d');
              $dateLabel = $isToday ? 'Today, ' . date('M j', strtotime($row['day'])) : date('M j', strtotime($row['day']));
              if ($isToday)   { $statusCls = 'status-badge--progress'; $status = 'In Progress'; $barCls = 'progress-fill--primary'; }
              elseif ($ml >= $goalMl * 1.1) { $statusCls = 'status-badge--exceeded'; $status = 'Exceeded';    $barCls = 'progress-fill--exceeded'; }
              elseif ($ml >= $goalMl)        { $statusCls = 'status-badge--met';      $status = 'Met Goal';    $barCls = 'progress-fill--success'; }
              else                           { $statusCls = 'status-badge--missed';   $status = 'Missed';      $barCls = 'progress-fill--error'; }
            ?>
            <tr>
              <td class="font-semibold" style="color:var(--color-on-surface);"><?= htmlspecialchars($dateLabel) ?></td>
              <td><?= $lStr ?></td>
              <td>
                <div class="progress-track">
                  <div class="progress-fill <?= $barCls ?>" style="width:<?= $pct ?>%;"></div>
                </div>
              </td>
              <td><?= htmlspecialchars($row['top_source'] ?? 'Water') ?></td>
              <td style="text-align:right;"><span class="status-badge <?= $statusCls ?>"><?= $status ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <footer class="app-footer">
    <div class="app-footer__brand">HydroFlow</div>
    <p class="app-footer__copy">© <?= date('Y') ?> HydroFlow. All rights reserved.</p>
  </footer>

  <script src="app.js"></script>
</body>
</html>
