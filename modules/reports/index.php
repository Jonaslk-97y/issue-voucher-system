<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

$report_type = sanitize($_GET['report_type'] ?? 'vouchers');
$date_from = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$date_to = sanitize($_GET['date_to'] ?? date('Y-m-d'));

try {
    // Report data for different types
    switch ($report_type) {
        case 'vouchers':
            $stmt = $pdo->prepare(
                "SELECT DATE(issue_date) as date, COUNT(*) as count, 
                 SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
                 SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued
                 FROM issue_vouchers 
                 WHERE DATE(issue_date) BETWEEN ? AND ?
                 GROUP BY DATE(issue_date)
                 ORDER BY date DESC"
            );
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'assets':
            $stmt = $pdo->prepare(
                "SELECT asset_type, COUNT(*) as count, 
                 SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                 SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued
                 FROM assets
                 GROUP BY asset_type"
            );
            $stmt->execute();
            $report_data = $stmt->fetchAll();
            break;
            
        case 'locations':
            $stmt = $pdo->prepare(
                "SELECT l.location_name, 
                 COUNT(iv.id) as total_movements,
                 SUM(CASE WHEN iv.status = 'issued' THEN 1 ELSE 0 END) as current_assets
                 FROM locations l
                 LEFT JOIN issue_vouchers iv ON l.id = iv.to_location
                 WHERE DATE(iv.issue_date) BETWEEN ? AND ? OR iv.issue_date IS NULL
                 GROUP BY l.id
                 ORDER BY total_movements DESC"
            );
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'users':
            $stmt = $pdo->prepare(
                "SELECT u.full_name, u.email, u.role,
                 COUNT(iv.id) as vouchers_issued,
                 SUM(CASE WHEN iv.status = 'returned' THEN 1 ELSE 0 END) as returns_processed
                 FROM users u
                 LEFT JOIN issue_vouchers iv ON u.id = iv.issued_by
                 WHERE DATE(iv.issue_date) BETWEEN ? AND ? OR iv.issue_date IS NULL
                 GROUP BY u.id
                 ORDER BY vouchers_issued DESC"
            );
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll();
            break;
            
        default:
            $report_data = [];
    }
    
    // Summary statistics
    $stmt = $pdo->query(
        "SELECT COUNT(*) as total_vouchers,
         SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as active_vouchers,
         SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned_vouchers
         FROM issue_vouchers"
    );
    $summary = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Report error: " . $e->getMessage());
    $report_data = [];
    $summary = ['total_vouchers' => 0, 'active_vouchers' => 0, 'returned_vouchers' => 0];
}

include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-bar-chart"></i> Reports & Analytics</h2>
    </div>
    <div class="col-md-4 text-end">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Total Vouchers</h6>
                <h2><?php echo $summary['total_vouchers']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6>Active Vouchers</h6>
                <h2><?php echo $summary['active_vouchers']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Returned Vouchers</h6>
                <h2><?php echo $summary['returned_vouchers']; ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Report Type</label>
                <select class="form-select" name="report_type">
                    <option value="vouchers" <?php echo $report_type === 'vouchers' ? 'selected' : ''; ?>>Vouchers by Date</option>
                    <option value="assets" <?php echo $report_type === 'assets' ? 'selected' : ''; ?>>Assets by Type</option>
                    <option value="locations" <?php echo $report_type === 'locations' ? 'selected' : ''; ?>>Location Movements</option>
                    <option value="users" <?php echo $report_type === 'users' ? 'selected' : ''; ?>>User Activity</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Results -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-table"></i> 
            <?php echo ucfirst($report_type); ?> Report
            <small class="text-muted"><?php echo date('d-m-Y', strtotime($date_from)); ?> to <?php echo date('d-m-Y', strtotime($date_to)); ?></small>
        </h5>
    </div>
    <div class="card-body">
        <?php if ($report_data): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <?php if ($report_type === 'vouchers'): ?>
                                <th>Date</th>
                                <th>Total Vouchers</th>
                                <th>Issued</th>
                                <th>Returned</th>
                                <th>Return Rate</th>
                            <?php elseif ($report_type === 'assets'): ?>
                                <th>Asset Type</th>
                                <th>Total</th>
                                <th>Available</th>
                                <th>Issued</th>
                                <th>Usage Rate</th>
                            <?php elseif ($report_type === 'locations'): ?>
                                <th>Location</th>
                                <th>Total Movements</th>
                                <th>Current Assets</th>
                            <?php else: ?>
                                <th>User</th>
                                <th>Role</th>
                                <th>Vouchers Issued</th>
                                <th>Returns Processed</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <?php if ($report_type === 'vouchers'): ?>
                                    <td><?php echo date('d-m-Y', strtotime($row['date'])); ?></td>
                                    <td><?php echo $row['count']; ?></td>
                                    <td><?php echo $row['issued']; ?></td>
                                    <td><?php echo $row['returned']; ?></td>
                                    <td>
                                        <?php 
                                        $rate = $row['count'] > 0 ? round(($row['returned'] / $row['count']) * 100) : 0;
                                        echo $rate . '%';
                                        ?>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%"></div>
                                        </div>
                                    </td>
                                <?php elseif ($report_type === 'assets'): ?>
                                    <td><?php echo htmlspecialchars($row['asset_type']); ?></td>
                                    <td><?php echo $row['count']; ?></td>
                                    <td><?php echo $row['available']; ?></td>
                                    <td><?php echo $row['issued']; ?></td>
                                    <td>
                                        <?php 
                                        $rate = $row['count'] > 0 ? round(($row['issued'] / $row['count']) * 100) : 0;
                                        echo $rate . '%';
                                        ?>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $rate; ?>%"></div>
                                        </div>
                                    </td>
                                <?php elseif ($report_type === 'locations'): ?>
                                    <td><?php echo htmlspecialchars($row['location_name']); ?></td>
                                    <td><?php echo $row['total_movements']; ?></td>
                                    <td><?php echo $row['current_assets']; ?></td>
                                <?php else: ?>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($row['role']); ?></span></td>
                                    <td><?php echo $row['vouchers_issued']; ?></td>
                                    <td><?php echo $row['returns_processed']; ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center my-4">
                <i class="bi bi-inbox"></i> No data available for the selected criteria.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>