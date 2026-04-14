<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /university/dashboard-students.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {

        // CONNECT TO YOUR TABLE
        $stmt = db()->prepare("SELECT * FROM studentslogin WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // VERIFY PASSWORD
        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
            'name'       => $user['name'],
             'email'      => $user['email'],
             'student_id' => $user['student_id']
            ];

            header('Location: /university/dashboard-students.php');
            exit;
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

<link rel="stylesheet" href="/university/assets/style-student.css">

<style>
.pw-wrap{position:relative;}
.pw-wrap .form-control{padding-right:44px;}
.pw-toggle{
  position:absolute;right:12px;top:50%;
  transform:translateY(-50%);
  background:none;border:none;cursor:pointer;
  color:#94a3b8;padding:4px;
}
.pw-toggle:hover{color:#475569;}
</style>
</head>

<body>

<div class="login-wrap">
  <div class="login-card">

    <div style="text-align:center;margin-bottom:24px;">
      <img src="/university/assets/logo.png"
           style="width:90px;height:90px;border-radius:16px;object-fit:cover;margin-bottom:12px;">
      <div class="login-title">Pawford University</div>
      <div class="login-sub">University Management System</div>
      <div class="login-sub">
        <a href="login.php">Switch to Admin Portal</a>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">✕ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="login-form">

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@university.edu" required>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="pw-wrap">
          <input type="password" id="pw-input" name="password"
                 class="form-control" placeholder="••••••••" required>

          <button type="button" class="pw-toggle" onclick="togglePw()">
            👁
          </button>
        </div>
      </div>

      <button type="submit" class="login-submit">Sign In</button>

    </form>

    <div class="login-link">
      Don't have an account?
      <a href="/university/register-student.php">Register</a>
    </div>

  </div>
</div>

<script>
function togglePw(){
  var i=document.getElementById('pw-input');
  if(i.type==='password'){
    i.type='text';
  }else{
    i.type='password';
  }
}
</script>

</body>
</html>