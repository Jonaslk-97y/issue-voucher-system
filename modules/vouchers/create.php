<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

$error = '';
$success = '';

// Get available assets and users
try {
    $assets = getAvailableAssets();
    $users = getAllUsers($_SESSION['user_id']);
    $locations = getAllLocations();
} catch (PDOException $e) {
    $error = "Error loading data: " . $e->getMessage();
    $assets = $users = $locations = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $asset_id = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
        $received_by = filter_input(INPUT_POST, 'received_by', FILTER_VALIDATE_INT);
        $from_location = filter_input(INPUT_POST, 'from_location', FILTER_VALIDATE_INT);
        $to_location = filter_input(INPUT_POST, 'to_location', FILTER_VALIDATE_INT);
        $issue_date = sanitize($_POST['issue_date'] ?? date('Y-m-d'));
        $expected_return_date = sanitize($_POST['expected_return_date'] ?? '');
        $purpose = sanitize($_POST['purpose'] ?? '');
        $account = sanitize($_POST['account'] ?? '');
        $requisition_number = sanitize($_POST['requisition_number'] ?? '');
        $authority_reference = sanitize($_POST['authority_reference'] ?? '');
        $item_condition = sanitize($_POST['item_condition'] ?? '');
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;
        $item_price = filter_input(INPUT_POST, 'item_price', FILTER_VALIDATE_FLOAT) ?: 0;
        $total_price = $quantity * $item_price;
        
        // Validation
        if (!$asset_id || !$received_by || !$from_location || !$to_location) {
            throw new Exception('Please fill in all required fields.');
        }
        
        if ($from_location == $to_location) {
            throw new Exception('From and To locations must be different.');
        }
        
        if ($received_by == $_SESSION['user_id']) {
            throw new Exception('You cannot issue to yourself.');
        }
        
        // Verify asset is still available
        if (!isAssetAvailable($asset_id)) {
            throw new Exception('This asset is no longer available.');
        }
        
        // Generate voucher number
        $voucher_number = generateVoucherNumber();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Create voucher
        $stmt = $pdo->prepare(
            "INSERT INTO issue_vouchers 
             (voucher_number, account, requisition_number, authority_reference, asset_id, issued_by, received_by, 
              from_location, to_location, item_condition, quantity, item_price, total_price,
              issue_date, expected_return_date, purpose, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued')"
        );
        $stmt->execute([
            $voucher_number, $account, $requisition_number, $authority_reference, $asset_id, $_SESSION['user_id'], $received_by,
            $from_location, $to_location, $item_condition, $quantity, $item_price, $total_price,
            $issue_date, $expected_return_date, $purpose
        ]);
        $voucher_id = $pdo->lastInsertId();
        
        // Update asset status
        $stmt = $pdo->prepare("UPDATE assets SET status = 'issued', current_location = (SELECT location_name FROM locations WHERE id = ?) WHERE id = ?");
        $stmt->execute([$to_location, $asset_id]);
        
        // Create digital copies (matching the 3-copy system)
        createVoucherCopies($voucher_id, $_SESSION['user_id'], $received_by);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'Created voucher', 'issue_vouchers', $voucher_id, "Voucher: {$voucher_number}");
        
        $pdo->commit();
        $success = "Voucher created successfully! Voucher #: {$voucher_number}";
        
        // Clear form data
        $_POST = [];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

include_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Issue Voucher</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <a href="list.php" class="alert-link">View all vouchers</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="asset_id" class="form-label">Asset/Device <span class="text-danger">*</span></label>
                            <select class="form-select" id="asset_id" name="asset_id" required>
                                <option value="">Select Device</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo $asset['id']; ?>">
                                        <?php echo htmlspecialchars($asset['device_name'] . ' (' . $asset['serial_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($assets)): ?>
                                <div class="text-warning small mt-1">
                                    <i class="bi bi-exclamation-triangle"></i> No available assets. 
                                    <a href="../assets/add.php">Add new asset</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="received_by" class="form-label">Receiving Person <span class="text-danger">*</span></label>
                            <select class="form-select" id="received_by" name="received_by" required>
                                <option value="">Select Person</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="account" class="form-label">Account</label>
                            <input type="text" class="form-control" id="account" name="account">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="requisition_number" class="form-label">Requisition Number</label>
                            <input type="text" class="form-control" id="requisition_number" name="requisition_number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="authority_reference" class="form-label">Authority Reference</label>
                            <input type="text" class="form-control" id="authority_reference" name="authority_reference">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="item_condition" class="form-label">Condition</label>
                            <select class="form-select" id="item_condition" name="item_condition">
                                <option value="">Select</option>
                                <option value="New">New</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="item_price" class="form-label">Item Price</label>
                            <input type="number" step="0.01" class="form-control" id="item_price" name="item_price" value="0.00" min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Total Price</label>
                            <input type="text" class="form-control" id="total_price_display" value="0.00" readonly>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="from_location" class="form-label">From Location <span class="text-danger">*</span></label>
                            <select class="form-select" id="from_location" name="from_location" required>
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>">
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="to_location" class="form-label">To Location <span class="text-danger">*</span></label>
                            <select class="form-select" id="to_location" name="to_location" required>
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>">
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="issue_date" class="form-label">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="expected_return_date" class="form-label">Expected Return Date</label>
                            <input type="date" class="form-control" id="expected_return_date" name="expected_return_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose/Reason</label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" 
                                  placeholder="Why is this device being moved?"></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="list.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Create Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('quantity').addEventListener('input', updateTotal);
document.getElementById('item_price').addEventListener('input', updateTotal);
function updateTotal() {
    const qty = parseFloat(document.getElementById('quantity').value) || 0;
    const price = parseFloat(document.getElementById('item_price').value) || 0;
    document.getElementById('total_price_display').value = (qty * price).toFixed(2);
}
</script>

<?php include_once '../../includes/footer.php'; ?>