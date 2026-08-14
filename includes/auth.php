<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// Log user activity
function logActivity($user_id, $action, $table_name = null, $record_id = null, $details = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_log (user_id, action, table_name, record_id, details, ip_address) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->execute([$user_id, $action, $table_name, $record_id, $details, $ip]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
?>