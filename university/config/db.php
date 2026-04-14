<?php
// config/db.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'university_db');
define('DB_USER', 'root');       // ← change to your MySQL username
define('DB_PASS', '');           // ← change to your MySQL password

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        try {
            $pdo = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES   => false]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#c0392b;background:#fdf0ef;border:1px solid #e74c3c;margin:40px;border-radius:8px;"><h2>Database Connection Failed</h2><p>'.$e->getMessage().'</p><p>Check your credentials in <code>config/db.php</code></p></div>');
        }
    }
    return $pdo;
}