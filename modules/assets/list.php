<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

$search = sanitize($_GET['search'] ?? '');
$asset_type = sanitize($_GET['asset_type'] ?? '');
$status = sanitize($_GET['status'] ?? '');

try {
    $sql = "SELECT * FROM assets WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (device_name LIKE ? OR serial_number LIKE ? OR model LIKE ? OR manufacturer LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    if ($asset_type) {
        $sql .= " AND asset_type = ?";
        $params[] = $asset_type;
    }
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY device_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("List assets error: " . $e->getMessage());
    $assets = [];
    $error = "Error loading assets.";
}

include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2><i class="bi bi-laptop"></i> Manage Assets</h2>
    </div>
    <div class="col-md-4 text-end">
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Asset
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search assets..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="asset_type">
                    <option value="">All Types</option>
                    <option value="Computer" <?php echo $asset_type === 'Computer' ? 'selected' : ''; ?>>Computer</option>
                    <option value="Laptop" <?php echo $asset_type === 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                    <option value="Printer" <?php echo $asset_type === 'Printer' ? 'selected' : ''; ?>>Printer</option>
                    <option value="Scanner" <?php echo $asset_type === 'Scanner' ? 'selected' : ''; ?>>Scanner</option>
                    <option value="Other" <?php echo $asset_type === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="issued" <?php echo $status === 'issued' ? 'selected' : ''; ?>>Issued</option>
                    <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assets Table -->
<div class="card">
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($assets): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Device Name</th>
                            <th>Serial #</th>
                            <th>Model</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($asset['asset_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($asset['device_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($asset['serial_number']); ?></code></td>
                                <td><?php echo htmlspecialchars($asset['model'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($asset['current_location'] ?? 'Not specified'); ?></td>
                                <td>
                                    <?php
                                    $badge_class = $asset['status'] === 'available' ? 'bg-success' : 
                                                   ($asset['status'] === 'issued' ? 'bg-warning' : 'bg-danger');
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo strtoupper($asset['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit.php?id=<?php echo $asset['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $asset['id']; ?>, '<?php echo htmlspecialchars($asset['device_name']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted text-center my-4">
                <i class="bi bi-inbox"></i> No assets found.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the asset: <strong id="deleteAssetName"></strong>?</p>
                <p class="text-danger"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="delete.php" id="deleteForm">
                    <input type="hidden" name="asset_id" id="deleteAssetId">
                    <button type="submit" class="btn btn-danger">Delete Asset</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteAssetId').value = id;
    document.getElementById('deleteAssetName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include_once '../../includes/footer.php'; ?>