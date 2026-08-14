<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$date_from = sanitize($_GET['date_from'] ?? '');
$date_to = sanitize($_GET['date_to'] ?? '');

try {
    $sql = "SELECT iv.*, a.device_name, a.serial_number, a.asset_type,
            u1.full_name as issued_by_name, u2.full_name as received_by_name,
            l1.location_name as from_location_name, l2.location_name as to_location_name
            FROM issue_vouchers iv
            JOIN assets a ON iv.asset_id = a.id
            JOIN users u1 ON iv.issued_by = u1.id
            JOIN users u2 ON iv.received_by = u2.id
            JOIN locations l1 ON iv.from_location = l1.id
            JOIN locations l2 ON iv.to_location = l2.id
            WHERE 1=1";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (iv.voucher_number LIKE ? OR a.device_name LIKE ? OR a.serial_number LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($status) {
        $sql .= " AND iv.status = ?";
        $params[] = $status;
    }
    
    if ($date_from) {
        $sql .= " AND DATE(iv.issue_date) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $sql .= " AND DATE(iv.issue_date) <= ?";
        $params[] = $date_to;
    }
    
    $sql .= " ORDER BY iv.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $vouchers = [];
    $error = "Error loading vouchers: " . $e->getMessage();
}

include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-list"></i> Issue Vouchers</h2>
    </div>
    <div class="col-md-4 text-end">
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Voucher
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search vouchers..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="issued" <?php echo $status === 'issued' ? 'selected' : ''; ?>>Issued</option>
                        <option value="returned" <?php echo $status === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" placeholder="From" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" placeholder="To" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Vouchers Table -->
<div class="card">
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($vouchers): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Voucher #</th>
                            <th>Device</th>
                            <th>Serial #</th>
                            <th>From → To</th>
                            <th>Issued By</th>
                            <th>Received By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $voucher): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?php echo $voucher['id']; ?>" class="fw-bold">
                                        <?php echo htmlspecialchars($voucher['voucher_number']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($voucher['device_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($voucher['serial_number']); ?></code></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($voucher['from_location_name']); ?></span>
                                    <i class="bi bi-arrow-right"></i>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($voucher['to_location_name']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($voucher['issued_by_name']); ?></td>
                                <td><?php echo htmlspecialchars($voucher['received_by_name']); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($voucher['issue_date'])); ?></td>
                                <td>
                                    <?php
                                    $badge_class = $voucher['status'] === 'issued' ? 'bg-warning' : 
                                                   ($voucher['status'] === 'returned' ? 'bg-success' : 'bg-danger');
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo strtoupper($voucher['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $voucher['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($voucher['status'] === 'issued'): ?>
                                            <a href="return.php?id=<?php echo $voucher['id']; ?>" class="btn btn-outline-success">
                                                <i class="bi bi-arrow-return-left"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center my-4">
                <i class="bi bi-inbox"></i> No vouchers found.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>