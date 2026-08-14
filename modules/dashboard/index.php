<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

// Get statistics
try {
    // Total assets
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM assets");
    $total_assets = $stmt->fetch()['total'];
    
    // Available assets
    $stmt = $pdo->query(
        "SELECT COUNT(*) as total FROM assets a 
         WHERE a.status = 'available' 
         AND NOT EXISTS (
             SELECT 1 FROM issue_vouchers iv 
             WHERE iv.asset_id = a.id AND iv.status = 'issued'
         )"
    );
    $available_assets = $stmt->fetch()['total'];
    
    // Issued assets
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM issue_vouchers WHERE status = 'issued'");
    $issued_assets = $stmt->fetch()['total'];
    
    // Total vouchers this month
    $stmt = $pdo->query(
        "SELECT COUNT(*) as total FROM issue_vouchers 
         WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
         AND YEAR(created_at) = YEAR(CURRENT_DATE())"
    );
    $monthly_vouchers = $stmt->fetch()['total'];
    
    // Recent vouchers
    $stmt = $pdo->query(
        "SELECT iv.*, a.device_name, a.serial_number, 
         u1.full_name as issued_by_name, u2.full_name as received_by_name,
         l1.location_name as from_location_name, l2.location_name as to_location_name
         FROM issue_vouchers iv
         JOIN assets a ON iv.asset_id = a.id
         JOIN users u1 ON iv.issued_by = u1.id
         JOIN users u2 ON iv.received_by = u2.id
         JOIN locations l1 ON iv.from_location = l1.id
         JOIN locations l2 ON iv.to_location = l2.id
         ORDER BY iv.created_at DESC LIMIT 10"
    );
    $recent_vouchers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $total_assets = $available_assets = $issued_assets = $monthly_vouchers = 0;
    $recent_vouchers = [];
}

include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Assets</h5>
                <h2><?php echo $total_assets; ?></h2>
                <small>Registered in system</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Available</h5>
                <h2><?php echo $available_assets; ?></h2>
                <small>Ready for issue</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <h5 class="card-title">Currently Issued</h5>
                <h2><?php echo $issued_assets; ?></h2>
                <small>In use</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Vouchers This Month</h5>
                <h2><?php echo $monthly_vouchers; ?></h2>
                <small><?php echo date('F Y'); ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Recent Vouchers -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Vouchers</h5>
    </div>
    <div class="card-body">
        <?php if ($recent_vouchers): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Voucher #</th>
                            <th>Device</th>
                            <th>Serial</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Issued By</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_vouchers as $voucher): ?>
                            <tr>
                                <td><a href="../vouchers/view.php?id=<?php echo $voucher['id']; ?>">
                                    <?php echo htmlspecialchars($voucher['voucher_number']); ?></a></td>
                                <td><?php echo htmlspecialchars($voucher['device_name']); ?></td>
                                <td><?php echo htmlspecialchars($voucher['serial_number']); ?></td>
                                <td><?php echo htmlspecialchars($voucher['from_location_name']); ?></td>
                                <td><?php echo htmlspecialchars($voucher['to_location_name']); ?></td>
                                <td><?php echo htmlspecialchars($voucher['issued_by_name']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = $voucher['status'] === 'issued' ? 'bg-warning' : 
                                                   ($voucher['status'] === 'returned' ? 'bg-success' : 'bg-danger');
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo strtoupper($voucher['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($voucher['issue_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">No recent vouchers found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>