<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
    
    if (!$asset_id) {
        header('Location: list.php?error=invalid_id');
        exit();
    }
    
    try {
        // Check if asset is currently issued
        if (!isAssetAvailable($asset_id)) {
            header('Location: list.php?error=asset_issued');
            exit();
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get asset details for logging
        $stmt = $pdo->prepare("SELECT device_name, serial_number FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();
        
        // Delete asset
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        
        // Log activity
        logActivity(
            $_SESSION['user_id'], 
            'Deleted asset', 
            'assets', 
            $asset_id,
            "Deleted asset: {$asset['device_name']} ({$asset['serial_number']})"
        );
        
        $pdo->commit();
        header('Location: list.php?success=deleted');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete asset error: " . $e->getMessage());
        header('Location: list.php?error=delete_failed');
    }
} else {
    header('Location: list.php');
}
exit();
?>