<?php
/**
 * HydroFlow — Settings Page (server-side rendered, standard POST form)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$success = '';
$errors  = [];

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = trim($_POST['full_name']  ?? '');
    $weight    = (float)($_POST['weight']  ?? 0);
    $height    = (float)($_POST['height']  ?? 0);
    $age       = (int)  ($_POST['age']     ?? 0);
    $goalMlIn  = (int)  ($_POST['daily_goal_ml'] ?? 2500);

    // Basic validation
    if (strlen($fullName) < 2) $errors[] = 'Name must be at least 2 characters.';
    if ($weight < 10 || $weight > 500) $errors[] = 'Weight must be between 10 and 500 kg.';
    if ($height < 50 || $height > 300) $errors[] = 'Height must be between 50 and 300 cm.';
    if ($age < 1 || $age > 120) $errors[] = 'Age must be between 1 and 120.';
    if ($goalMlIn < 500 || $goalMlIn > 10000) $errors[] = 'Daily goal must be between 500ml and 10L.';

    if (empty($errors)) {
        $db->prepare('UPDATE users SET full_name=?, weight=?, height=?, age=?, daily_goal_ml=? WHERE user_id=?')
           ->execute([$fullName, $weight, $height, $age, $goalMlIn, $userId]);

        // Update session name
        $_SESSION['full_name'] = $fullName;
        $success = 'Settings saved successfully!';
    }
}

// ── Load current user data ────────────────────────────────────────────────────
$u = $db->prepare('SELECT full_name, email, age, weight, height, daily_goal_ml FROM users WHERE user_id=?');
$u->execute([$userId]);
$user      = $u->fetch();
$fullName  = htmlspecialchars($user['full_name']  ?? '', ENT_QUOTES, 'UTF-8');
$email     = htmlspecialchars($user['email']      ?? '', ENT_QUOTES, 'UTF-8');
$age       = (int)($user['age']      ?? 0);
$weight    = (float)($user['weight'] ?? 0);
$height    = (float)($user['height'] ?? 0);
$goalMl    = (int)($user['daily_goal_ml'] ?? 2500);
$goalLabel = number_format($goalMl / 1000, 1) . 'L';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings - HydroFlow</title>
  <meta name="description" content="Customize your HydroFlow profile, adjust hydration goals, and manage your preferences.">
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="app-layout" style="display: flex;">

  <button class="hamburger" id="hamburger" aria-label="Toggle navigation">
    <span class="material-symbols-outlined">menu</span>
  </button>
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
      <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">water_drop</span>
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
      <a href="history.php" class="nav-item"><span class="material-symbols-outlined">history</span>History</a>
      <a href="settings.php" class="nav-item nav-item--active"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">settings</span>Settings</a>
    </nav>
    <div class="sidebar__footer">
      <a href="logout.php" class="nav-item" style="color:var(--color-error);">
        <span class="material-symbols-outlined">logout</span>Logout
      </a>
    </div>
  </aside>

  <header class="topnav" id="topnav">
    <div class="topnav__actions" style="margin-left:auto;">
      <span class="topnav__greeting">Hello, <?= $fullName ?>!</span>
      <a href="logout.php" class="topnav__icon-btn" aria-label="Logout" title="Logout">
        <span class="material-symbols-outlined">logout</span>
      </a>
    </div>
  </header>

  <main class="app-content">
    <header class="mb-8">
      <h1 class="text-headline-lg-responsive" style="color:var(--color-on-surface);">Settings &amp; Goals</h1>
    </header>

    <?php if ($success): ?>
    <div class="alert alert--success" style="margin-bottom:24px;">
      <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert--error" style="margin-bottom:24px;">
      <span class="material-symbols-outlined" style="font-size:18px;">error</span>
      <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="settings.php" id="settings-form">
      <div class="grid-settings">

        <!-- Left Column -->
        <div class="flex flex-col gap-6">

          <!-- Daily Goal Calculator Card -->
          <section class="glass-card settings-section" style="border-radius:16px;">
            <div class="settings-section__header">
              <span class="material-symbols-outlined text-primary">calculate</span>
              <h2 class="text-headline-md" style="color:var(--color-on-surface);">Daily Hydration Goal</h2>
            </div>
            <div class="grid-2 mb-6">
              <div>
                <label class="text-label-md text-on-surface-variant" style="display:block;margin-bottom:8px;" for="setting-weight">Weight (kg)</label>
                <input class="input-field" id="setting-weight" name="weight" type="number" step="0.1" min="10" max="500" value="<?= $weight ?>">
              </div>
              <div>
                <label class="text-label-md text-on-surface-variant" style="display:block;margin-bottom:8px;" for="setting-activity">Activity Level</label>
                <select class="input-field" id="setting-activity">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium (Active)</option>
                  <option value="high">High</option>
                </select>
              </div>
            </div>
            <!-- Goal display + hidden field -->
            <div style="background:linear-gradient(135deg,var(--color-primary),var(--color-primary-container));border-radius:12px;padding:20px;display:flex;align-items:center;justify-content:space-between;color:white;">
              <div>
                <p class="text-label-md" style="color:rgba(255,255,255,0.8);margin-bottom:4px;">Your Daily Goal</p>
                <p class="text-headline-lg" style="color:white;" id="recommended-goal"><?= $goalLabel ?> / Day</p>
              </div>
              <div style="width:56px;height:56px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <span class="material-symbols-outlined" style="font-size:28px;font-variation-settings:'FILL' 1;">water_drop</span>
              </div>
            </div>
            <input type="hidden" name="daily_goal_ml" id="daily-goal-ml" value="<?= $goalMl ?>">
          </section>

          <!-- Profile Information Card -->
          <section class="glass-card settings-section" style="border-radius:16px;">
            <div class="settings-section__header">
              <span class="material-symbols-outlined text-primary">person</span>
              <h2 class="text-headline-md" style="color:var(--color-on-surface);">Profile Information</h2>
            </div>
            <div class="flex flex-col gap-4">
              <div>
                <label class="text-label-md text-on-surface-variant" style="display:block;margin-bottom:8px;" for="full-name">Full Name</label>
                <input class="input-field" id="full-name" name="full_name" type="text" value="<?= $fullName ?>">
              </div>
              <div>
                <label class="text-label-md text-on-surface-variant" style="display:block;margin-bottom:8px;" for="profile-email">Email</label>
                <input class="input-field" id="profile-email" type="email" value="<?= $email ?>" readonly style="opacity:0.6;cursor:not-allowed;" title="Email cannot be changed">
              </div>
              <div class="grid-2">
                <div>
                  <label class="text-label-md text-on-surface-variant" style="display:block;margin-bottom:8px;" for="profile-age">Age</label>
                  <input class="input-field" id="profile-age" name="age" type="number" min="1" max="120" value="<?= $age ?>">
                </div>
                <div>
                  <label class="text-label-md text-on-surface-variant" style="display:block;margin-bottom:8px;" for="profile-height">Height (cm)</label>
                  <input class="input-field" id="profile-height" name="height" type="number" step="0.1" min="50" max="300" value="<?= $height ?>">
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- Right Column -->
        <div class="flex flex-col gap-6">

          <!-- Account Security Card -->
          <section class="glass-card settings-section" style="border-radius:16px;">
            <div class="settings-section__header">
              <span class="material-symbols-outlined text-primary">lock</span>
              <h2 class="text-headline-md" style="color:var(--color-on-surface);">Account</h2>
            </div>
            <div>
              <div class="settings-row">
                <div class="settings-row__info">
                  <span class="settings-row__label">Email Address</span>
                  <span class="settings-row__desc"><?= $email ?></span>
                </div>
              </div>
              <div class="settings-row">
                <div class="settings-row__info">
                  <span class="settings-row__label">Member Since</span>
                  <span class="settings-row__desc">HydroFlow account</span>
                </div>
              </div>
              <div class="settings-row" style="padding-top:16px;border-top:1px solid var(--color-surface-container-high);">
                <div class="settings-row__info">
                  <span class="settings-row__label" style="color:var(--color-error);">Sign Out</span>
                  <span class="settings-row__desc">Log out of your account</span>
                </div>
                <a href="logout.php" class="btn-outline-danger" style="font-size:13px;padding:8px 16px;text-decoration:none;">
                  <span class="material-symbols-outlined" style="font-size:16px;">logout</span>
                  Logout
                </a>
              </div>
            </div>
          </section>

          <!-- Appearance Card -->
          <section class="glass-card settings-section" style="border-radius:16px;">
            <div class="settings-section__header">
              <span class="material-symbols-outlined text-primary">palette</span>
              <h2 class="text-headline-md" style="color:var(--color-on-surface);">Appearance</h2>
            </div>
            <div>
              <div class="settings-row">
                <div class="settings-row__info">
                  <span class="settings-row__label">Unit System</span>
                  <span class="settings-row__desc">Measurement preference</span>
                </div>
                <select class="input-field" style="width:auto;min-width:120px;">
                  <option selected>Metric (ml/L)</option>
                  <option>Imperial (oz)</option>
                </select>
              </div>
            </div>
          </section>

          <!-- Action Buttons -->
          <div class="flex gap-4" style="flex-wrap:wrap;">
            <button class="btn-primary flex-1" type="submit" id="btn-save-settings">
              <span class="material-symbols-outlined" style="font-size:18px;">save</span>
              Save Changes
            </button>
          </div>
        </div>

      </div>
    </form>
  </main>

  <script src="app.js"></script>
  <script>
  // Live goal calculator — updates hidden input + display
  (function() {
    const wInput   = document.getElementById('setting-weight');
    const aSelect  = document.getElementById('setting-activity');
    const display  = document.getElementById('recommended-goal');
    const hidden   = document.getElementById('daily-goal-ml');

    function calc() {
      const w = parseFloat(wInput.value) || 70;
      const multiplier = { low: 30, medium: 35, high: 40 }[aSelect.value] || 35;
      const ml = Math.round(w * multiplier);
      hidden.value = ml;
      display.textContent = (ml / 1000).toFixed(1) + 'L / Day';
    }
    wInput.addEventListener('input', calc);
    aSelect.addEventListener('change', calc);
  })();
  </script>
</body>
</html>
