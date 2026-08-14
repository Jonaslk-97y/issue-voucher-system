<?php
// Generate unique voucher number
function generateVoucherNumber() {
    return 'IV' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Validate serial number format
function validateSerialNumber($serial) {
    return preg_match('/^[A-Za-z0-9\-]{5,50}$/', $serial);
}

// Get asset by ID
function getAssetById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get user by ID
function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, full_name, email, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get location by ID
function getLocationById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Check if asset is available
function isAssetAvailable($asset_id) {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as count FROM issue_vouchers 
         WHERE asset_id = ? AND status = 'issued'"
    );
    $stmt->execute([$asset_id]);
    $result = $stmt->fetch();
    return $result['count'] == 0;
}

// Get all available assets
function getAvailableAssets() {
    global $pdo;
    $stmt = $pdo->query(
        "SELECT a.* FROM assets a 
         WHERE a.status = 'available' 
         AND NOT EXISTS (
             SELECT 1 FROM issue_vouchers iv 
             WHERE iv.asset_id = a.id AND iv.status = 'issued'
         )
         ORDER BY a.device_name"
    );
    return $stmt->fetchAll();
}

// Get all users except current
function getAllUsers($exclude_id = null) {
    global $pdo;
    $sql = "SELECT id, username, full_name, email, role FROM users WHERE is_active = 1";
    if ($exclude_id) {
        $sql .= " AND id != ?";
    }
    $sql .= " ORDER BY full_name";
    $stmt = $pdo->prepare($sql);
    if ($exclude_id) {
        $stmt->execute([$exclude_id]);
    } else {
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

// Get all locations
function getAllLocations() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM locations ORDER BY location_name");
    return $stmt->fetchAll();
}

// Create voucher copies
function createVoucherCopies($voucher_id, $issued_by, $received_by) {
    global $pdo;
    
    // Original copy - goes to IT Senior
    $stmt = $pdo->prepare(
        "INSERT INTO voucher_copies (voucher_id, copy_type, holder_id) VALUES (?, 'original', ?)"
    );
    $stmt->execute([$voucher_id, $issued_by]);
    
    // Office copy - stays at issuing office
    $stmt = $pdo->prepare(
        "INSERT INTO voucher_copies (voucher_id, copy_type, holder_id) VALUES (?, 'office_copy', ?)"
    );
    $stmt->execute([$voucher_id, $issued_by]);
    
    // Receiver copy - goes to receiving party
    $stmt = $pdo->prepare(
        "INSERT INTO voucher_copies (voucher_id, copy_type, holder_id) VALUES (?, 'receiver_copy', ?)"
    );
    $stmt->execute([$voucher_id, $received_by]);
}

// Display flash message
function showMessage($type, $message) {
    $alert_class = $type === 'success' ? 'alert-success' : 
                   ($type === 'error' ? 'alert-danger' : 'alert-info');
    echo "<div class='alert {$alert_class} alert-dismissible fade show' role='alert'>
            {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
}
?>