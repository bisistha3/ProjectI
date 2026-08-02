<?php
/**
 * HydroFlow — Login Handler
 * Handles both GET (show form) and POST (process login).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validate.php';
require_once __DIR__ . '/includes/mailer.php';


// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.html');
    exit;
}

$errors = [];
$old = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $old['email'] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    // Validate
    $v = new Validator();
    $v->required('email', $email, 'Email')
      ->email('email', $email)
      ->required('password', $password, 'Password');

    if (!$v->passes()) {
        $errors = $v->errors();
    } else {
        // Look up user
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => trim($email)]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check if email is verified
                if ((int)$user['is_verified'] === 0) {
                    // Resend OTP and redirect to verification page
                    $otp  = generateOtp();
                    saveOtp($db, (int)$user['user_id'], $otp);
                    $sent = sendOtpEmail($user['email'], $user['full_name'], $otp);

                    $_SESSION['pending_verify_user_id'] = (int)$user['user_id'];
                    $_SESSION['pending_verify_email']   = $user['email'];
                    if (!$sent) $_SESSION['dev_otp_fallback'] = $otp;

                    setFlash('info', 'Please verify your email first. A new code has been sent.');
                    header('Location: verify-email.php');
                    exit;
                }

                loginUser($user);
                header('Location: dashboard.html');
                exit;
            } else {
                $errors['general'] = 'Invalid email or password.';
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
  <title>Login - HydroFlow</title>
  <meta name="description" content="Sign in to HydroFlow to track your daily hydration goals and stay healthy.">
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="auth-layout">

  <main class="auth-card" style="max-width: 448px;">
    <!-- Brand -->
    <div class="sidebar__brand mb-2">
      <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1; font-size: 30px;">water_drop</span>
      HydroFlow
    </div>

    <p class="text-body-md text-on-surface-variant mb-8 text-center">
      Sign in to track your hydration goals.
    </p>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert--error mb-4">
        <span class="material-symbols-outlined" style="font-size: 18px;">error</span>
        <?= htmlspecialchars($errors['general']) ?>
      </div>
    <?php endif; ?>

    <?php
      $flash = getFlash('success');
      if ($flash):
    ?>
      <div class="alert alert--success mb-4">
        <span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span>
        <?= htmlspecialchars($flash) ?>
      </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form id="login-form" class="w-full flex flex-col gap-5" method="POST" action="login.php" novalidate>
      <!-- Email -->
      <div class="flex flex-col gap-1">
        <label class="text-label-md text-on-surface" for="email">Email Address</label>
        <div class="input-group">
          <span class="material-symbols-outlined icon-left">mail</span>
          <input
            class="input-field input-field--icon <?= isset($errors['email']) ? 'input-field--error' : '' ?>"
            id="email"
            name="email"
            type="email"
            placeholder="you@example.com"
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
      <div class="flex flex-col gap-1">
        <div class="flex justify-between items-center">
          <label class="text-label-md text-on-surface" for="password">Password</label>
          <a class="text-label-md text-primary" href="#" style="text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Forgot Password?</a>
        </div>
        <div class="input-group">
          <span class="material-symbols-outlined icon-left">lock</span>
          <input
            class="input-field input-field--icon input-field--icon-right <?= isset($errors['password']) ? 'input-field--error' : '' ?>"
            id="password"
            name="password"
            type="password"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          >
          <button type="button" class="icon-right toggle-password" data-target="password" aria-label="Toggle password visibility">
            <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
          </button>
        </div>
        <?php if (isset($errors['password'])): ?>
          <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Sign In Button -->
      <button class="btn-primary w-full mt-2" type="submit" id="btn-signin">
        Sign in
        <span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>
      </button>
    </form>

    <!-- Footer -->
    <div class="mt-8 text-center">
      <p class="text-body-md text-on-surface-variant">
        Don't have an account?
        <a class="text-primary font-semibold" href="register.php" style="text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Sign Up</a>
      </p>
    </div>
  </main>

  <script src="app.js"></script>
</body>
</html>
