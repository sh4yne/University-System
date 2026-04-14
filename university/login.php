<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /university/dashboard.php'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($email && $password) {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user']    = ['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
            header('Location: /university/dashboard.php'); exit;
        }
        $error = 'Invalid email or password.';
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Pawford University</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/university/assets/style.css">
<style>
.pw-wrap{position:relative;}
.pw-wrap .form-control{padding-right:44px;}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px;display:flex;align-items:center;}
.pw-toggle:hover{color:#475569;}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div style="text-align:center;margin-bottom:24px;">
      <img src="/university/assets/logo.png" alt="Pawford Logo" style="width:90px;height:90px;border-radius:16px;object-fit:cover;display:block;margin:0 auto 12px;">
      <div class="login-title">Pawford University</div>
      <div class="login-sub">University Management System</div>
      <div class="login-sub">
        <a href="login-student.php">Switch to Student Portal</a>
      </div>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-error">✕ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="login-form">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@university.edu" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="pw-wrap">
          <input type="password" id="pw-input" name="password" class="form-control" placeholder="••••••••" required>
          <button type="button" class="pw-toggle" onclick="togglePw()">
            <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="login-submit">Sign In</button>
    </form>
    <div class="login-link">Don't have an account? <a href="/university/register.php">Register</a></div>
  </div>
</div>
<script>
function togglePw(){
  var i=document.getElementById('pw-input');
  var e=document.getElementById('eye-icon');
  if(i.type==='password'){
    i.type='text';
    e.innerHTML='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
  }else{
    i.type='password';
    e.innerHTML='<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}
</script>
</body>
</html>