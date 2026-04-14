<?php
// includes/layout.php
function layoutHead(string $title): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> — Pawford University</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Source+Sans+3:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/university/assets/style.css">
</head>
<body>
<?php }

function layoutSidebar(string $active): void {
    $user = currentUser();
    $nav = [
        ['href'=>'dashboard.php',   'icon'=>'⊞', 'label'=>'Dashboard'],
        ['href'=>'students.php',    'icon'=>'👥', 'label'=>'Students'],
        ['href'=>'faculty.php',     'icon'=>'🎓', 'label'=>'Faculty'],
        ['href'=>'courses.php',     'icon'=>'📚', 'label'=>'Courses'],
        ['href'=>'sections.php',    'icon'=>'🗓', 'label'=>'Class Sections'],
        ['href'=>'enrollments.php', 'icon'=>'📋', 'label'=>'Enrollments'],
    ];
    if (isAdmin()) {
        $nav[] = ['href'=>'users.php', 'icon'=>'🔑', 'label'=>'Users'];
    }
?>
<div class="layout">
<aside class="sidebar">
  <div class="sidebar-brand">
    <img src="/university/assets/logo.png" alt="Pawford Logo" class="brand-logo">
    <div>
      <div class="brand-name">Pawford</div>
      <div class="brand-sub">University System</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($nav as $item): ?>
    <a href="/university/<?= $item['href'] ?>"
       class="nav-link <?= $active === $item['href'] ? 'active' : '' ?>">
      <span class="nav-icon"><?= $item['icon'] ?></span>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?></div>
    <div class="user-info">
      <div class="user-name"><?= sanitize($user['name'] ?? '') ?></div>
      <div class="user-role"><?= ucfirst($user['role'] ?? '') ?></div>
    </div>
    <a href="/university/logout.php" class="logout-btn" title="Logout">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
    </a>
  </div>
</aside>

<main class="main-content">
<?php }

function layoutEnd(): void { ?>
</main>
</div>
<script src="/university/assets/app.js"></script>
</body>
</html>
<?php }

function flashBanner(): void {
    $success = flash('success');
    $error   = flash('error');
    if ($success) echo '<div class="alert alert-success">✓ '.$success.'</div>';
    if ($error)   echo '<div class="alert alert-error">✕ '.$error.'</div>';
}