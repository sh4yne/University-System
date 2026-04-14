<?php
// register.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = db()->prepare("SELECT id FROM studentslogin WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            db()->prepare("INSERT INTO studentslogin (name, email, password) VALUES (?, ?, ?)")
            ->execute([$name, $email, $hash]);
            $success = 'Account created! You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register — Pawford University</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/university/assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div style="text-align:center;margin-bottom:24px;">
      <img src="/university/assets/Logo.png" alt="Pawford Logo" style="width:90px;height:90px;border-radius:16px;object-fit:cover;display:block;margin:0 auto 12px;">
      <div class="login-title">Pawford University</div>
      <div class="login-sub">University Management System</div>
    </div>

    <?php if ($error):   ?><div class="alert alert-error">✕ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" class="login-form">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" placeholder="Juan Dela Cruz"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@university.edu"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm" class="form-control" placeholder="Re-enter password" required>
      </div>
      <button type="submit" class="login-submit">Create Account</button>
    </form>

    <div class="login-link">
      Already have an account? <a href="/university/login-student.php">Sign in</a>
    </div>
  </div>
</div>
</body>
</html>