<?php
// test.php - Connection test file
echo "<h1>XAMPP Connection Test</h1>";

// Test PHP
echo "<h3>PHP Version: " . phpversion() . "</h3>";

// Test MySQL connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=issue_voucher_system;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;'>✅ MySQL Connection successful!</p>";
    
    // Test if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . implode(", ", $tables) . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ MySQL Connection failed: " . $e->getMessage() . "</p>";
}

// Test database functions
if (function_exists('mysqli_connect')) {
    echo "<p style='color:green;'>✅ MySQLi extension is enabled</p>";
} else {
    echo "<p style='color:red;'>❌ MySQLi extension is not enabled</p>";
}

// Check if required extensions are loaded
$extensions = ['pdo_mysql', 'mysqli', 'gd', 'openssl'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color:green;'>✅ $ext extension is loaded</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ $ext extension is not loaded (optional for some features)</p>";
    }
}

phpinfo();
?>