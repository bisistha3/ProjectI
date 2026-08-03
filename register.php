<?php
/**
 * HydroFlow â€” Register Handler
 * Handles both GET (show form) and POST (create account + send OTP).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/validate.php';
require_once __DIR__ . '/includes/mailer.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = [
    'name'   => '',
    'email'  => '',
    'gender' => 'male',
    'age'    => '',
    'weight' => '',
    'height' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = $_POST['name'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $gender   = $_POST['gender'] ?? 'male';
    $age      = $_POST['age'] ?? '';
    $weight   = $_POST['weight'] ?? '';
    $height   = $_POST['height'] ?? '';

    // Preserve old values for repopulating form on error
    $old = [
        'name'   => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'email'  => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
        'gender' => $gender === 'female' ? 'female' : 'male',
        'age'    => htmlspecialchars($age, ENT_QUOTES, 'UTF-8'),
        'weight' => htmlspecialchars($weight, ENT_QUOTES, 'UTF-8'),
        'height' => htmlspecialchars($height, ENT_QUOTES, 'UTF-8'),
    ];

    // Validate all fields
    $v = new Validator();
    $v->required('name', $name, 'Full Name')
      ->minLength('name', $name, 2, 'Full Name')
      ->maxLength('name', $name, 100, 'Full Name');

    $v->required('email', $email, 'Email')
      ->email('email', $email)
      ->emailDomain('email', $email);

    $v->required('password', $password, 'Password')
      ->password('password', $password);

    $v->inList('gender', $gender, ['male', 'female'], 'Gender');

    $v->required('age', $age, 'Age')
      ->numericRange('age', $age, 1, 120, 'Age');

    $v->required('weight', $weight, 'Weight')
      ->numericRange('weight', $weight, 1, 300, 'Weight');

    $v->required('height', $height, 'Height')
      ->numericRange('height', $height, 50, 250, 'Height');

    if (!$v->passes()) {
        $errors = $v->errors();
    } else {
        try {
            $db = getDB();

            // Check if email already exists
            $check = $db->prepare('SELECT user_id, is_verified FROM users WHERE email = :email');
            $check->execute([':email' => trim($email)]);
            $existing = $check->fetch();

            if ($existing) {
                if ((int)$existing['is_verified'] === 1) {
                    // Fully verified account already exists
                    $errors['email'] = 'An account with this email already exists.';
                } else {
                    // Unverified account â€” resend a fresh OTP
                    $userId = (int)$existing['user_id'];
                    $otp    = generateOtp();
                    saveOtp($db, $userId, $otp);
                    $sent   = sendOtpEmail(trim($email), trim($name), $otp);

                    $_SESSION['pending_verify_user_id'] = $userId;
                    $_SESSION['pending_verify_email']   = trim($email);
                    if (!$sent) $_SESSION['dev_otp_fallback'] = $otp;

                    setFlash('info', 'A new verification code has been sent to your email.');
                    header('Location: verify-email.php');
                    exit;
                }
            } else {
                // New account â€” insert as UNVERIFIED
                $stmt = $db->prepare('
                    INSERT INTO users (full_name, email, password, gender, age, weight, height, is_verified)
                    VALUES (:name, :email, :password, :gender, :age, :weight, :height, 0)
                ');
                $stmt->execute([
                    ':name'     => trim($name),
                    ':email'    => trim($email),
                    ':password' => $password,
                    ':gender'   => $gender,
                    ':age'      => (int)$age,
                    ':weight'   => (float)$weight,
                    ':height'   => (float)$height,
                ]);

                $userId = (int)$db->lastInsertId();

                // Generate OTP, save to DB, send email
                $otp  = generateOtp();
                saveOtp($db, $userId, $otp);
                $sent = sendOtpEmail(trim($email), trim($name), $otp);

                // Store pending verification in session
                $_SESSION['pending_verify_user_id'] = $userId;
                $_SESSION['pending_verify_email']   = trim($email);

                if (!$sent) {
                    // Email not configured â€” show code on verify page (dev mode)
                    $_SESSION['dev_otp_fallback'] = $otp;
                }

                header('Location: verify-email.php');
                exit;
            }
        } catch (Exception $e) {
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - HydroFlow</title>
  <meta name="description" content="Create your HydroFlow account and personalize your daily hydration goals.">
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="auth-layout">

  <main class="auth-card auth-card--wide">
    <!-- Decorative blob -->
    <div class="decor-blob"></div>

    <!-- Header -->
    <header class="text-center">
      <h1 class="text-headline-lg-responsive text-primary mb-2">Create Account</h1>
      <p class="text-body-md text-on-surface-variant">Complete your profile to personalize your daily goals.</p>
    </header>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error">
        <span class="material-symbols-outlined" style="font-size: 18px;">error</span>
        <?= htmlspecialchars($errors['general']) ?>
      </div>
    <?php endif; ?>

    <!-- Registration Form -->
    <form id="register-form" class="flex flex-col gap-6" method="POST" action="register.php" novalidate>

      <!-- Account Information Section -->
      <section class="flex flex-col gap-4">
        <h2 class="section-label">Account Information</h2>

        <!-- Full Name -->
        <div>
          <div class="input-group">
            <div class="icon-left" style="pointer-events: none;">
              <span class="material-symbols-outlined" style="font-size: 20px;">person</span>
            </div>
            <input
              class="input-field input-field--icon <?= isset($errors['name']) ? 'input-field--error' : '' ?>"
              id="name"
              name="name"
              type="text"
              placeholder="Full Name"
              value="<?= $old['name'] ?>"
              required
            >
          </div>
          <?php if (isset($errors['name'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['name']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Email -->
        <div>
          <div class="input-group">
            <div class="icon-left" style="pointer-events: none;">
              <span class="material-symbols-outlined" style="font-size: 20px;">mail</span>
            </div>
            <input
              class="input-field input-field--icon <?= isset($errors['email']) ? 'input-field--error' : '' ?>"
              id="reg-email"
              name="email"
              type="email"
              placeholder="Email Address"
              autocomplete="email"
              value="<?= $old['email'] ?>"
              required
            >
          </div>
          <?php if (isset($errors['email'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['email']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div>
          <div class="input-group">
            <div class="icon-left" style="pointer-events: none;">
              <span class="material-symbols-outlined" style="font-size: 20px;">lock</span>
            </div>
            <input
              class="input-field input-field--icon input-field--icon-right <?= isset($errors['password']) ? 'input-field--error' : '' ?>"
              id="reg-password"
              name="password"
              type="password"
              placeholder="Password (min 8 chars, 1 upper, 1 lower, 1 digit)"
              required
            >
            <button type="button" class="icon-right toggle-password" data-target="reg-password" aria-label="Toggle password visibility">
              <span class="material-symbols-outlined" style="font-size: 20px;">visibility_off</span>
            </button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
          <?php endif; ?>
        </div>
      </section>

      <!-- Body Metrics Section -->
      <section class="flex flex-col gap-4 pt-4" style="border-top: 1px solid rgba(190, 199, 212, 0.3);">
        <div class="flex flex-col gap-1">
          <h2 class="section-label">Body Metrics</h2>
          <p class="text-on-surface-variant" style="font-size: 10px; font-weight: 600; letter-spacing: 0.05em;">Used to calculate your precise daily water intake.</p>
        </div>

        <!-- Gender Toggle -->
        <div class="flex flex-col gap-2">
          <label class="text-label-md text-on-surface">Gender</label>
          <div class="gender-toggle" id="gender-toggle">
            <div class="gender-toggle__slider" id="gender-slider" <?= $old['gender'] === 'female' ? 'style="transform:translateX(100%)"' : '' ?>></div>
            <label class="<?= $old['gender'] === 'male' ? 'active' : '' ?>" id="label-male">
              <input type="radio" name="gender" value="male" <?= $old['gender'] === 'male' ? 'checked' : '' ?> class="sr-only">
              <span>Male</span>
            </label>
            <label class="<?= $old['gender'] === 'female' ? 'active' : '' ?>" id="label-female">
              <input type="radio" name="gender" value="female" <?= $old['gender'] === 'female' ? 'checked' : '' ?> class="sr-only">
              <span>Female</span>
            </label>
          </div>
        </div>

        <!-- Age, Weight, Height Grid -->
        <div class="grid-3 mt-2">
          <!-- Age -->
          <div class="flex flex-col gap-2">
            <label class="text-label-md text-on-surface" for="age">Age</label>
            <input
              class="input-field <?= isset($errors['age']) ? 'input-field--error' : '' ?>"
              id="age"
              name="age"
              type="number"
              min="1"
              max="120"
              placeholder="Years"
              value="<?= $old['age'] ?>"
              style="text-align: center;"
            >
            <?php if (isset($errors['age'])): ?>
              <span class="field-error"><?= htmlspecialchars($errors['age']) ?></span>
            <?php endif; ?>
          </div>
          <!-- Weight -->
          <div class="flex flex-col gap-2">
            <label class="text-label-md text-on-surface" for="weight">
              Weight <span class="text-on-surface-variant" style="font-weight: 400;">kg</span>
            </label>
            <input
              class="input-field <?= isset($errors['weight']) ? 'input-field--error' : '' ?>"
              id="weight"
              name="weight"
              type="number"
              min="1"
              max="300"
              placeholder="0"
              value="<?= $old['weight'] ?>"
              style="text-align: center;"
            >
            <?php if (isset($errors['weight'])): ?>
              <span class="field-error"><?= htmlspecialchars($errors['weight']) ?></span>
            <?php endif; ?>
          </div>
          <!-- Height -->
          <div class="flex flex-col gap-2">
            <label class="text-label-md text-on-surface" for="height">
              Height <span class="text-on-surface-variant" style="font-weight: 400;">cm</span>
            </label>
            <input
              class="input-field <?= isset($errors['height']) ? 'input-field--error' : '' ?>"
              id="height"
              name="height"
              type="number"
              min="50"
              max="250"
              placeholder="0"
              value="<?= $old['height'] ?>"
              style="text-align: center;"
            >
            <?php if (isset($errors['height'])): ?>
              <span class="field-error"><?= htmlspecialchars($errors['height']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- Actions -->
      <div class="flex flex-col gap-4 mt-2">
        <button class="btn-gradient w-full" type="submit">Create Account</button>
        <p class="text-center text-body-md text-on-surface-variant">
          Already have an account?
          <a class="text-primary font-bold" href="login.php" style="text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Log In</a>
        </p>
      </div>
    </form>
  </main>

  <script src="app.js"></script>
</body>
</html>

