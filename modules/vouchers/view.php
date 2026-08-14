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
    // Get voucher details with all related information
    $stmt = $pdo->prepare(
        "SELECT iv.*, 
         a.device_name, a.serial_number, a.asset_type, a.model, a.manufacturer,
         u1.full_name as issued_by_name, u1.email as issued_by_email,
         u2.full_name as received_by_name, u2.email as received_by_email,
         l1.location_name as from_location_name, l1.building as from_building, l1.floor as from_floor, l1.room as from_room,
         l2.location_name as to_location_name, l2.building as to_building, l2.floor as to_floor, l2.room as to_room
         FROM issue_vouchers iv
         JOIN assets a ON iv.asset_id = a.id
         JOIN users u1 ON iv.issued_by = u1.id
         JOIN users u2 ON iv.received_by = u2.id
         JOIN locations l1 ON iv.from_location = l1.id
         JOIN locations l2 ON iv.to_location = l2.id
         WHERE iv.id = ?"
    );
    $stmt->execute([$voucher_id]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        header('Location: list.php');
        exit();
    }

    // Get copy holders (digital copies)
    $stmt = $pdo->prepare(
        "SELECT vc.*, u.full_name, u.email 
         FROM voucher_copies vc
         JOIN users u ON vc.holder_id = u.id
         WHERE vc.voucher_id = ?"
    );
    $stmt->execute([$voucher_id]);
    $copies = $stmt->fetchAll();

    // Get audit trail for this voucher
    $stmt = $pdo->prepare(
        "SELECT * FROM audit_log 
         WHERE table_name = 'issue_vouchers' AND record_id = ?
         ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$voucher_id]);
    $audit_trail = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("View voucher error: " . $e->getMessage());
    header('Location: list.php');
    exit();
}

include_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="bi bi-file-text"></i> Voucher Details
                <small class="text-muted">#<?php echo htmlspecialchars($voucher['voucher_number']); ?></small>
            </h2>
            <div>
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <?php if ($voucher['status'] === 'issued'): ?>
                    <a href="return.php?id=<?php echo $voucher['id']; ?>" class="btn btn-success">
                        <i class="bi bi-arrow-return-left"></i> Process Return
                    </a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Voucher Information -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Voucher Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Voucher Number:</strong></td>
                                <td><code><?php echo htmlspecialchars($voucher['voucher_number']); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <?php
                                    $badge_class = $voucher['status'] === 'issued' ? 'bg-warning' : 
                                                   ($voucher['status'] === 'returned' ? 'bg-success' : 'bg-danger');
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo strtoupper($voucher['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Issue Date:</strong></td>
                                <td><?php echo date('d-m-Y', strtotime($voucher['issue_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Expected Return:</strong></td>
                                <td>
                                    <?php 
                                    if ($voucher['expected_return_date']) {
                                        echo date('d-m-Y', strtotime($voucher['expected_return_date']));
                                    } else {
                                        echo '<span class="text-muted">Not specified</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php if ($voucher['actual_return_date']): ?>
                            <tr>
                                <td><strong>Actual Return:</strong></td>
                                <td><?php echo date('d-m-Y', strtotime($voucher['actual_return_date'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Issued By:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['issued_by_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Issued Email:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['issued_by_email']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Received By:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['received_by_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Received Email:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['received_by_email']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($voucher['purpose']): ?>
                <div class="mt-3">
                    <strong>Purpose/Reason:</strong>
                    <p class="mt-1"><?php echo nl2br(htmlspecialchars($voucher['purpose'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Asset Information -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-laptop"></i> Asset Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Device Name:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['device_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Asset Type:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['asset_type']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Serial Number:</strong></td>
                                <td><code><?php echo htmlspecialchars($voucher['serial_number']); ?></code></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Model:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['model'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Manufacturer:</strong></td>
                                <td><?php echo htmlspecialchars($voucher['manufacturer'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Information -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Movement Information</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-5">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-muted">From</h6>
                            <h5><?php echo htmlspecialchars($voucher['from_location_name']); ?></h5>
                            <small class="text-muted">
                                <?php 
                                $from_parts = [];
                                if ($voucher['from_building']) $from_parts[] = $voucher['from_building'];
                                if ($voucher['from_floor']) $from_parts[] = 'Floor ' . $voucher['from_floor'];
                                if ($voucher['from_room']) $from_parts[] = 'Room ' . $voucher['from_room'];
                                echo implode(', ', $from_parts);
                                ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-center justify-content-center">
                        <i class="bi bi-arrow-right-circle fs-1 text-primary"></i>
                    </div>
                    <div class="col-md-5">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-muted">To</h6>
                            <h5><?php echo htmlspecialchars($voucher['to_location_name']); ?></h5>
                            <small class="text-muted">
                                <?php 
                                $to_parts = [];
                                if ($voucher['to_building']) $to_parts[] = $voucher['to_building'];
                                if ($voucher['to_floor']) $to_parts[] = 'Floor ' . $voucher['to_floor'];
                                if ($voucher['to_room']) $to_parts[] = 'Room ' . $voucher['to_room'];
                                echo implode(', ', $to_parts);
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Information -->
    <div class="col-md-4">
        <!-- Digital Copies -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-files"></i> Digital Copies</h5>
            </div>
            <div class="card-body">
                <?php if ($copies): ?>
                    <div class="list-group">
                        <?php foreach ($copies as $copy): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-<?php 
                                        echo $copy['copy_type'] === 'original' ? 'danger' : 
                                             ($copy['copy_type'] === 'office_copy' ? 'info' : 'success');
                                    ?> me-2">
                                        <?php echo strtoupper(str_replace('_', ' ', $copy['copy_type'])); ?>
                                    </span>
                                    <?php echo htmlspecialchars($copy['full_name']); ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('d-m-Y', strtotime($copy['held_at'])); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No copies found</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Audit Trail -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Audit Trail</h5>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                <?php if ($audit_trail): ?>
                    <div class="timeline">
                        <?php foreach ($audit_trail as $log): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <?php echo date('d-m-Y H:i', strtotime($log['created_at'])); ?>
                                </small>
                                <div><?php echo htmlspecialchars($log['action']); ?></div>
                                <?php if ($log['details']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['details']); ?></small>
                                <?php endif; ?>
                                <hr>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No audit records</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>