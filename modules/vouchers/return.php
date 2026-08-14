<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

$voucher_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$voucher_id) {
    header('Location: list.php');
    exit();
}

try {
    // Get voucher details
    $stmt = $pdo->prepare(
        "SELECT iv.*, a.id as asset_id, a.device_name, a.serial_number,
         l1.location_name as from_location, l2.location_name as to_location
         FROM issue_vouchers iv
         JOIN assets a ON iv.asset_id = a.id
         JOIN locations l1 ON iv.from_location = l1.id
         JOIN locations l2 ON iv.to_location = l2.id
         WHERE iv.id = ? AND iv.status = 'issued'"
    );
    $stmt->execute([$voucher_id]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        header('Location: list.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Return voucher error: " . $e->getMessage());
    header('Location: list.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $return_date = sanitize($_POST['return_date'] ?? date('Y-m-d'));
        $condition = sanitize($_POST['condition'] ?? 'Good');
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update voucher with return date
        $stmt = $pdo->prepare(
            "UPDATE issue_vouchers 
             SET status = 'returned', actual_return_date = ? 
             WHERE id = ?"
        );
        $stmt->execute([$return_date, $voucher_id]);
        
        // Update asset status to available
        $stmt = $pdo->prepare(
            "UPDATE assets 
             SET status = 'available', 
                 current_location = (SELECT location_name FROM locations WHERE id = ?) 
             WHERE id = ?"
        );
        $stmt->execute([$voucher['from_location'], $voucher['asset_id']]);
        
        // Log activity
        logActivity(
            $_SESSION['user_id'], 
            'Returned asset', 
            'issue_vouchers', 
            $voucher_id,
            "Asset returned: {$voucher['device_name']} ({$voucher['serial_number']}) - Condition: {$condition}"
        );
        
        $pdo->commit();
        $success = "Asset returned successfully!";
        
        // Refresh voucher data
        $stmt = $pdo->prepare(
            "SELECT iv.*, a.id as asset_id, a.device_name, a.serial_number,
             l1.location_name as from_location, l2.location_name as to_location
             FROM issue_vouchers iv
             JOIN assets a ON iv.asset_id = a.id
             JOIN locations l1 ON iv.from_location = l1.id
             JOIN locations l2 ON iv.to_location = l2.id
             WHERE iv.id = ?"
        );
        $stmt->execute([$voucher_id]);
        $voucher = $stmt->fetch();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error processing return: " . $e->getMessage();
    }
}

include_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-arrow-return-left"></i> Process Asset Return</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <a href="view.php?id=<?php echo $voucher_id; ?>" class="alert-link">View voucher details</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                    <!-- Voucher Summary -->
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Voucher #:</strong> <?php echo htmlspecialchars($voucher['voucher_number']); ?><br>
                                <strong>Device:</strong> <?php echo htmlspecialchars($voucher['device_name']); ?><br>
                                <strong>Serial #:</strong> <?php echo htmlspecialchars($voucher['serial_number']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Issued Date:</strong> <?php echo date('d-m-Y', strtotime($voucher['issue_date'])); ?><br>
                                <strong>From:</strong> <?php echo htmlspecialchars($voucher['from_location']); ?><br>
                                <strong>To:</strong> <?php echo htmlspecialchars($voucher['to_location']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="return_date" class="form-label">Return Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="return_date" name="return_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="condition" class="form-label">Condition <span class="text-danger">*</span></label>
                                <select class="form-select" id="condition" name="condition" required>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Needs Repair">Needs Repair</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Return Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Any additional notes about the return..."></textarea>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="view.php?id=<?php echo $voucher_id; ?>" class="btn btn-secondary me-md-2">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Confirm Return
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>