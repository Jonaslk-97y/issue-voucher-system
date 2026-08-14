<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

$asset_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$asset_id) {
    header('Location: list.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
    
    if (!$asset) {
        header('Location: list.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Edit asset error: " . $e->getMessage());
    header('Location: list.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $asset_type = sanitize($_POST['asset_type']);
        $device_name = sanitize($_POST['device_name']);
        $serial_number = strtoupper(sanitize($_POST['serial_number']));
        $model = sanitize($_POST['model']);
        $manufacturer = sanitize($_POST['manufacturer']);
        $purchase_date = sanitize($_POST['purchase_date']);
        $warranty_end = sanitize($_POST['warranty_end']);
        $current_location = sanitize($_POST['current_location']);
        $status = sanitize($_POST['status']);
        
        // Validation
        if (!$asset_type || !$device_name || !$serial_number) {
            throw new Exception('Device name, serial number, and asset type are required.');
        }
        
        if (!validateSerialNumber($serial_number)) {
            throw new Exception('Serial number must be 5-50 characters (letters, numbers, hyphens only).');
        }
        
        // Check if serial number already exists (excluding current asset)
        $stmt = $pdo->prepare("SELECT id FROM assets WHERE serial_number = ? AND id != ?");
        $stmt->execute([$serial_number, $asset_id]);
        if ($stmt->fetch()) {
            throw new Exception('Serial number already exists in the system.');
        }
        
        // Update asset
        $stmt = $pdo->prepare(
            "UPDATE assets SET 
             asset_type = ?, device_name = ?, serial_number = ?, model = ?, 
             manufacturer = ?, purchase_date = ?, warranty_end = ?, 
             current_location = ?, status = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([
            $asset_type, $device_name, $serial_number, $model, $manufacturer,
            $purchase_date, $warranty_end, $current_location, $status, $asset_id
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'Updated asset', 'assets', $asset_id, "Asset: {$device_name} ({$serial_number})");
        
        $success = "Asset updated successfully!";
        
        // Refresh asset data
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$locations = getAllLocations();

include_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Edit Asset</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <a href="list.php" class="alert-link">View all assets</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="asset_type" class="form-label">Asset Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="asset_type" name="asset_type" required>
                                <option value="Computer" <?php echo $asset['asset_type'] === 'Computer' ? 'selected' : ''; ?>>Computer</option>
                                <option value="Laptop" <?php echo $asset['asset_type'] === 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                                <option value="Printer" <?php echo $asset['asset_type'] === 'Printer' ? 'selected' : ''; ?>>Printer</option>
                                <option value="Scanner" <?php echo $asset['asset_type'] === 'Scanner' ? 'selected' : ''; ?>>Scanner</option>
                                <option value="Other" <?php echo $asset['asset_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="device_name" class="form-label">Device Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="device_name" name="device_name" 
                                   value="<?php echo htmlspecialchars($asset['device_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="serial_number" class="form-label">Serial Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                   value="<?php echo htmlspecialchars($asset['serial_number']); ?>" required>
                            <small class="text-muted">5-50 characters (letters, numbers, hyphens)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model" 
                                   value="<?php echo htmlspecialchars($asset['model'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="manufacturer" class="form-label">Manufacturer</label>
                            <input type="text" class="form-control" id="manufacturer" name="manufacturer" 
                                   value="<?php echo htmlspecialchars($asset['manufacturer'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="current_location" class="form-label">Current Location</label>
                            <select class="form-select" id="current_location" name="current_location">
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo htmlspecialchars($location['location_name']); ?>"
                                        <?php echo $asset['current_location'] === $location['location_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="purchase_date" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date"
                                   value="<?php echo htmlspecialchars($asset['purchase_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="warranty_end" class="form-label">Warranty End Date</label>
                            <input type="date" class="form-control" id="warranty_end" name="warranty_end"
                                   value="<?php echo htmlspecialchars($asset['warranty_end'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="available" <?php echo $asset['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="issued" <?php echo $asset['status'] === 'issued' ? 'selected' : ''; ?>>Issued</option>
                                <option value="maintenance" <?php echo $asset['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="list.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Asset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>