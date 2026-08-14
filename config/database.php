<?php
// Database configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'issue_voucher_system');
define('DB_USER', 'root');      // Default XAMPP username
define('DB_PASS', '');          // Default XAMPP password (empty)

// Application configuration
define('APP_NAME', 'Issue Voucher System');
define('APP_URL', 'http://localhost/issue-voucher-system');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    // Optional: Uncomment to verify connection
    // echo "Database connected successfully";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>