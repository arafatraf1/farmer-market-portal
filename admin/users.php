<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$db = get_db();

// Toggle user status (suspend / activate)
if (isset($_GET['toggle_id'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $stmtCheck = $db->prepare("SELECT user_id, status, role FROM users WHERE user_id = ?");
    $stmtCheck->execute([$toggleId]);
    $targetUser = $stmtCheck->fetch();

    if ($targetUser && $targetUser['role'] !== 'admin') {
        $newStatus = ($targetUser['status'] === 'active') ? 'suspended' : 'active';
        $db->prepare("UPDATE users SET status = ? WHERE user_id = ?")->execute([$newStatus, $toggleId]);
        set_flash('success', "User account has been {$newStatus}.");
        header('Location: ' . url('admin/users.php'));
        exit;
    }
}

$roleFilter = trim($_GET['role'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT u.*, 
           f.farm_name, f.verification_status,
           b.completed_orders, b.cancelled_orders
    FROM users u
    LEFT JOIN farmers f ON u.user_id = f.user_id
    LEFT JOIN buyers b ON u.user_id = b.user_id
    WHERE 1=1
";
$params = [];

if ($roleFilter !== 'all') {
    $sql .= " AND u.role = :role";
    $params[':role'] = $roleFilter;
}

if ($statusFilter !== 'all') {
    $sql .= " AND u.status = :status";
    $params[':status'] = $statusFilter;
}

if ($search !== '') {
    $sql .= " AND (u.name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

$pageTitle = 'User Management — Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>User Management</h1>
                <p>Monitor platform consumers, agricultural sellers, and access privileges</p>
            </div>
        </div>

        <!-- Filter Form -->
        <form action="" method="GET" style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; background:#ffffff; padding:16px 20px; border-radius:12px; border:1px solid var(--border-color); align-items:center;">
            <input type="text" name="q" placeholder="Search name, email, phone..." value="<?= e($search) ?>" class="form-control" style="width:260px;">
            
            <select name="role" class="form-control" style="width:160px;">
                <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                <option value="buyer" <?= $roleFilter === 'buyer' ? 'selected' : '' ?>>Buyers Only</option>
                <option value="farmer" <?= $roleFilter === 'farmer' ? 'selected' : '' ?>>Farmers Only</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrators</option>
            </select>

            <select name="status" class="form-control" style="width:160px;">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended Only</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
            <a href="<?= url('admin/users.php') ?>" class="btn btn-secondary btn-sm">Reset</a>
        </form>

        <div class="card-panel">
            <div class="card-panel-header">
                <span class="card-panel-title">Platform Users (<?= count($usersList) ?>)</span>
            </div>

            <div class="table-responsive">
                <table class="portal-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Contact & Address</th>
                            <th>Role Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <td>
                                    <div class="table-item-cell">
                                        <img src="<?= e($u['profile_image'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80') ?>" alt="User" class="table-thumbnail" style="border-radius:50%;">
                                        <div>
                                            <strong style="color:var(--gray-900);"><?= e($u['name']) ?></strong>
                                            <div style="font-size:12px; color:var(--gray-500);"><?= e($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?= $u['role'] === 'farmer' ? 'badge-primary' : ($u['role'] === 'admin' ? 'badge-warning' : 'badge-info') ?>">
                                        <?= ucfirst(e($u['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div>📞 <?= e($u['phone']) ?></div>
                                    <div style="font-size:11.5px; color:var(--gray-500);"><?= e(mb_strimwidth($u['address'] ?? 'No address provided', 0, 40, '...')) ?></div>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'farmer'): ?>
                                        <div style="font-weight:600;"><?= e($u['farm_name']) ?></div>
                                        <?= get_verification_badge($u['verification_status']) ?>
                                    <?php elseif ($u['role'] === 'buyer'): ?>
                                        <div style="font-size:12px; color:var(--gray-600);">
                                            <?= (int)$u['completed_orders'] ?> Completed • <?= (int)$u['cancelled_orders'] ?> Cancelled
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:12px; color:var(--gray-400);">System Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= ucfirst(e($u['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['role'] !== 'admin'): ?>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <a href="<?= url('admin/users.php?toggle_id=' . $u['user_id']) ?>" class="btn btn-secondary btn-sm" style="color:var(--danger-500);" onclick="return confirm('Suspend this user account? They will be locked out from signing in.');">
                                                Suspend User
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= url('admin/users.php?toggle_id=' . $u['user_id']) ?>" class="btn btn-primary btn-sm" onclick="return confirm('Re-activate this user account?');">
                                                Activate User
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size:12px; color:var(--gray-400);">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
