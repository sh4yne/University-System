<?php
session_start();

// If user is already logged in, redirect to appropriate dashboard
if (!empty($_SESSION['user_id'])) {
    // Check if it's a student or admin
    if (isset($_SESSION['user']['student_id'])) {
        header('Location: /university/dashboard-students.php');
    } else {
        header('Location: /university/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Welcome — Pawford University</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Source Sans 3', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.container {
    max-width: 1000px;
    width: 100%;
}

.welcome-card {
    background: white;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
}

.header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 48px 32px;
    text-align: center;
}

.logo {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    object-fit: cover;
    margin: 0 auto 20px;
    display: block;
    border: 4px solid rgba(255,255,255,0.2);
}

.main-title {
    font-family: 'Playfair Display', serif;
    font-size: 42px;
    font-weight: 600;
    margin-bottom: 12px;
}

.subtitle {
    font-size: 18px;
    opacity: 0.95;
    font-weight: 400;
}

.content {
    padding: 48px 32px;
}

.welcome-text {
    text-align: center;
    margin-bottom: 48px;
}

.welcome-text h2 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    color: #1e293b;
    margin-bottom: 12px;
}

.welcome-text p {
    font-size: 16px;
    color: #64748b;
    line-height: 1.6;
}

.portal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.portal-card {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 32px 24px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
}

.portal-card:hover {
    border-color: #667eea;
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(102, 126, 234, 0.2);
}

.portal-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
}

.portal-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    color: #1e293b;
    margin-bottom: 8px;
}

.portal-card p {
    font-size: 14px;
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 20px;
}

.portal-btn {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 28px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.portal-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.footer {
    text-align: center;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
}

.footer p {
    font-size: 14px;
    color: #94a3b8;
}

@media (max-width: 640px) {
    .main-title {
        font-size: 32px;
    }
    
    .header {
        padding: 32px 24px;
    }
    
    .content {
        padding: 32px 24px;
    }
    
    .portal-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<div class="container">
    <div class="welcome-card">
        <div class="header">
            <img src="/university/assets/logo.png" alt="Pawford University Logo" class="logo">
            <h1 class="main-title">Pawford University</h1>
            <p class="subtitle">University Management System</p>
        </div>

        <div class="content">
            <div class="welcome-text">
                <h2>Welcome to Our Portal</h2>
                <p>Please select your portal to continue</p>
            </div>

            <div class="portal-grid">
                <a href="/university/login.php" class="portal-card">
                    <div class="portal-icon">🎓</div>
                    <h3>Admin Portal</h3>
                    <p>Access the administrative dashboard to manage courses, faculty, students, and enrollments</p>
                    <span class="portal-btn">Login as Admin</span>
                </a>

                <a href="/university/login-student.php" class="portal-card">
                    <div class="portal-icon">📚</div>
                    <h3>Student Portal</h3>
                    <p>Access your student dashboard to view courses, enrollments, and academic information</p>
                    <span class="portal-btn">Login as Student</span>
                </a>
            </div>

            <div class="footer">
                <p>&copy; <?= date('Y') ?> Pawford University. All rights reserved.</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
