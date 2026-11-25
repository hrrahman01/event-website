<?php
/**
 * Admin login and logout.
 * Logout is a POST with a CSRF token, so another website cannot log an admin out.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
sendNoCacheHeaders();
header('X-Frame-Options: DENY');

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$token  = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;

/* ---------- Logout ---------- */
if ($method === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (verifyCsrf($token)) {
        logoutAdmin();
    }
    header('Location: login.php?loggedout=1');
    exit;
}

/* ---------- Already logged in ---------- */
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

/* ---------- Login ---------- */
$error    = '';
$username = '';

if ($method === 'POST') {
    $username = cleanText($_POST['username'] ?? '', 120);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // Per-account limit, plus a looser per-IP limit (offices share one IP)
    $userKey = rateLimitKey('login-user', strtolower($username));
    $ipKey   = rateLimitKey('login-ip');

    if (!verifyCsrf($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif (rateLimitExceeded($userKey, LOGIN_MAX_ATTEMPTS, LOGIN_WINDOW)
        || rateLimitExceeded($ipKey, LOGIN_MAX_ATTEMPTS * 4, LOGIN_WINDOW)) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } elseif ($username === '' || $password === '' || strlen($password) > 200) {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, full_name, role, password_hash FROM admins
             WHERE username = :username OR email = :email LIMIT 1'
        );
        $stmt->execute([':username' => $username, ':email' => $username]);
        $admin = $stmt->fetch();

        // A hash is always verified, so response time does not reveal whether the user exists
        $hash  = $admin['password_hash'] ?? password_hash('placeholder-password', PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash) && $admin !== false;

        if ($valid) {
            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE admins SET password_hash = :hash WHERE id = :id')
                    ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $admin['id']]);
            }
            rateLimitClear($userKey);
            loginAdmin($admin);
            header('Location: index.php');
            exit;
        }

        rateLimitHit($userKey);
        rateLimitHit($ipKey);
        logActivity(null, 'login_failed', 'admin', null, 'Failed login attempt from ' . clientIp());
        $error = 'Invalid username or password.';   // same message for every failure
    }
}

$loggedOut = isset($_GET['loggedout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin Login</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body class="loginBody">

  <!-- Particle background -->
  <div id="particles-js" class="loginParticles" aria-hidden="true"></div>

  <main class="loginWrap">
    <div class="loginBox">

      <div class="text-center mb-4">
        <span class="loginLogo d-inline-flex align-items-center justify-content-center rounded-4 bg-warning text-primary">
          <i class="fa-solid fa-atom" aria-hidden="true"></i>
        </span>
        <h1 class="loginTitle">Admin Panel</h1>
        <p class="loginSub mb-0">Sign in to manage the conference</p>
      </div>

      <div class="card loginCard border-0">
        <div class="card-body p-4 p-sm-5">

          <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small" role="alert"><?= e($error) ?></div>
          <?php elseif ($loggedOut): ?>
            <div class="alert alert-success py-2 small" role="status">You have been logged out.</div>
          <?php endif; ?>

          <form method="post" action="login.php" id="loginForm" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="mb-3">
              <label for="username" class="form-label fw-semibold">Username or email</label>
              <div class="input-group loginInput">
                <span class="input-group-text"><i class="fa-regular fa-user" aria-hidden="true"></i></span>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($username) ?>" maxlength="120" autocomplete="username" required autofocus>
              </div>
            </div>

            <div class="mb-4">
              <label for="password" class="form-label fw-semibold">Password</label>
              <div class="input-group loginInput">
                <span class="input-group-text"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                <input type="password" id="password" name="password" class="form-control"
                       maxlength="200" autocomplete="current-password" required>
                <button type="button" class="btn btn-outline-secondary" id="togglePassword" aria-label="Show password">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <button type="submit" id="loginBtn" class="btn btn-warning w-100 fw-bold py-2">
              <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
              <span class="btnLabel">Sign In</span>
            </button>
          </form>

        </div>
      </div>

      <p class="text-center small mt-4 mb-0">
        <a href="../index.html" class="loginBack"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Back to website</a>
      </p>
    </div>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"></script>
  <script>
    (function () {
      'use strict';

      // Particle background (skipped when the visitor prefers reduced motion)
      var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (window.particlesJS && !reduceMotion) {
        particlesJS('particles-js', {
          particles: {
            number: { value: 70, density: { enable: true, value_area: 900 } },
            color: { value: ['#ffffff', '#FFC928'] },
            shape: { type: 'circle' },
            opacity: { value: 0.5, random: true },
            size: { value: 3, random: true },
            line_linked: { enable: true, distance: 140, color: '#ffffff', opacity: 0.18, width: 1 },
            move: { enable: true, speed: 1.4, direction: 'none', out_mode: 'out' }
          },
          interactivity: {
            detect_on: 'window',
            events: { onhover: { enable: true, mode: 'grab' }, onclick: { enable: true, mode: 'push' }, resize: true },
            modes: { grab: { distance: 160, line_linked: { opacity: 0.45 } }, push: { particles_nb: 3 } }
          },
          retina_detect: true
        });
      }

      // Show / hide password
      var passwordInput = document.getElementById('password');
      var toggleBtn = document.getElementById('togglePassword');
      toggleBtn.addEventListener('click', function () {
        var show = passwordInput.type === 'password';
        passwordInput.type = show ? 'text' : 'password';
        toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggleBtn.firstElementChild.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
      });

      // Loading state on submit (also blocks double clicks)
      var form = document.getElementById('loginForm');
      var loginBtn = document.getElementById('loginBtn');
      form.addEventListener('submit', function () {
        if (!form.checkValidity()) return;
        loginBtn.disabled = true;
        loginBtn.querySelector('.spinner-border').classList.remove('d-none');
        loginBtn.querySelector('.btnLabel').textContent = 'Signing in...';
      });
    })();
  </script>
</body>
</html>
