<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$db = get_db();

// Process actions (Approve, Reject, Re-open)
$action = $_GET['action'] ?? '';
$farmerId = (int)($_GET['id'] ?? 0);
$notes = trim($_GET['notes'] ?? '');

if ($farmerId > 0 && in_array($action, ['approve', 'reject', 'pending'], true)) {
    $newStatus = match($action) {
        'approve' => 'verified',
        'reject' => 'rejected',
        default => 'pending'
    };

    $stmtUpdate = $db->prepare("UPDATE farmers SET verification_status = ?, admin_notes = ? WHERE farmer_id = ?");
    $stmtUpdate->execute([$newStatus, $notes ?: null, $farmerId]);

    set_flash('success', 'Farmer verification status updated to ' . ucfirst($newStatus) . '.');
    header('Location: ' . url('admin/verification.php'));
    exit;
}

$tab = trim($_GET['tab'] ?? 'pending');

$sql = "
    SELECT f.*, u.name as farmer_name, u.email, u.phone, u.profile_image, u.created_at as joined_date
    FROM farmers f
    JOIN users u ON f.user_id = u.user_id
";

if ($tab === 'pending') {
    $sql .= " WHERE f.verification_status = 'pending'";
} elseif ($tab === 'verified') {
    $sql .= " WHERE f.verification_status = 'verified'";
} elseif ($tab === 'rejected') {
    $sql .= " WHERE f.verification_status = 'rejected'";
}

$sql .= " ORDER BY f.created_at DESC";
$farmers = $db->query($sql)->fetchAll();

// Counts
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM farmers WHERE verification_status = 'pending'")->fetchColumn();
$verifiedCount = (int)$db->query("SELECT COUNT(*) FROM farmers WHERE verification_status = 'verified'")->fetchColumn();
$rejectedCount = (int)$db->query("SELECT COUNT(*) FROM farmers WHERE verification_status = 'rejected'")->fetchColumn();

$pageTitle = 'Farmer Identity Verification Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Farmer Verification Management</h1>
                <p>Verify submitted official government identification (NID / Birth Certificate) to grant the Verified Farmer badge</p>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div style="display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap;">
            <a href="<?= url('admin/verification.php?tab=pending') ?>" class="btn <?= $tab === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                Pending Review (<?= $pendingCount ?>)
            </a>
            <a href="<?= url('admin/verification.php?tab=verified') ?>" class="btn <?= $tab === 'verified' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                Verified Farmers (<?= $verifiedCount ?>)
            </a>
            <a href="<?= url('admin/verification.php?tab=rejected') ?>" class="btn <?= $tab === 'rejected' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                Rejected (<?= $rejectedCount ?>)
            </a>
            <a href="<?= url('admin/verification.php?tab=all') ?>" class="btn <?= $tab === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                All Farmers
            </a>
        </div>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Farmers Queue (<?= count($farmers) ?>)</span>
            </div>

            <?php if (empty($farmers)): ?>
                <div style="padding:50px; text-align:center; color:var(--gray-500);">
                    No farmers found in this verification queue category.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Farmer / Farm Name</th>
                                <th>Contact & Location</th>
                                <th>Document Type</th>
                                <th>Inspect Document</th>
                                <th>Current Status</th>
                                <th>Admin Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($farmers as $f): ?>
                                <tr>
                                    <td>
                                        <div class="table-item-cell">
                                            <img src="<?= e($f['profile_image'] ?: 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=80&q=80') ?>" alt="Farmer" class="table-thumbnail" style="border-radius:50%;">
                                            <div>
                                                <strong style="color:var(--gray-900);"><?= e($f['farmer_name']) ?></strong>
                                                <div style="font-weight:600; color:var(--primary-800);"><?= e($f['farm_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>📞 <?= e($f['phone']) ?></div>
                                        <div style="font-size:12px; color:var(--gray-500);"><?= e($f['email']) ?></div>
                                        <div style="font-size:11.5px; color:var(--gray-400);">📍 <?= e($f['farm_location']) ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-primary">
                                            <?= strtoupper(e($f['verification_doc_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($f['verification_doc_path'])): ?>
                                            <a href="<?= url('admin/view-doc.php?farmer_id=' . $f['farmer_id']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:12px;">
                                                🔍 View Secure Document
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size:12px; color:var(--gray-400);">No Doc Uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= get_verification_badge($f['verification_status']) ?>
                                        <?php if ($f['admin_notes']): ?>
                                            <div style="font-size:11px; color:var(--danger-500); margin-top:2px;">
                                                Reason: <?= e($f['admin_notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <?php if ($f['verification_status'] !== 'verified'): ?>
                                                <a href="<?= url('admin/verification.php?action=approve&id=' . $f['farmer_id']) ?>" class="btn btn-primary btn-sm" style="font-size:12px;" onclick="return confirm('Approve this farmer as Verified?');">
                                                    ✓ Approve
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($f['verification_status'] !== 'rejected'): ?>
                                                <button type="button" class="btn btn-secondary btn-sm" style="color:var(--danger-500); font-size:12px;" onclick="const r=prompt('Enter reason for rejection:'); if(r) window.location.href='<?= url('admin/verification.php?action=reject&id=' . $f['farmer_id']) ?>&notes='+encodeURIComponent(r);">
                                                    &times; Reject
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($f['verification_status'] !== 'pending'): ?>
                                                <a href="<?= url('admin/verification.php?action=pending&id=' . $f['farmer_id']) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;" title="Reset to pending audit">
                                                    Reset
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
