<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('admin');
$db = get_db();

$errors = [];

// Handle Delete
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $count = (int)$db->query("SELECT COUNT(*) FROM products WHERE category_id = {$delId}")->fetchColumn();
    if ($count > 0) {
        set_flash('error', "Cannot delete category: {$count} agricultural products are currently listed under it.");
    } else {
        $db->prepare("DELETE FROM categories WHERE category_id = ?")->execute([$delId]);
        set_flash('success', 'Category deleted successfully.');
    }
    header('Location: ' . url('admin/categories.php'));
    exit;
}

// Handle Add / Edit
$editCat = null;
if (isset($_GET['edit_id'])) {
    $stmtEdit = $db->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmtEdit->execute([(int)$_GET['edit_id']]);
    $editCat = $stmtEdit->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $catName = trim($_POST['category_name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'leaf');
    $imageUrl = trim($_POST['image'] ?? '');
    $catId = (int)($_POST['category_id'] ?? 0);

    if (empty($catName)) {
        $errors[] = 'Category name is required.';
    }
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $catName)));
    }

    if (empty($errors)) {
        if ($catId > 0) {
            $stmt = $db->prepare("UPDATE categories SET category_name = ?, slug = ?, description = ?, icon = ?, image = ? WHERE category_id = ?");
            $stmt->execute([$catName, $slug, $description, $icon, $imageUrl, $catId]);
            set_flash('success', "Category '{$catName}' updated successfully.");
        } else {
            $stmt = $db->prepare("INSERT INTO categories (category_name, slug, description, icon, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$catName, $slug, $description, $icon, $imageUrl]);
            set_flash('success', "New category '{$catName}' created successfully.");
        }
        header('Location: ' . url('admin/categories.php'));
        exit;
    }
}

$categories = $db->query("
    SELECT c.*, COUNT(p.product_id) as total_products
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name ASC
")->fetchAll();

$pageTitle = 'Category Management — Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Agricultural Categories Management</h1>
                <p>Configure product classification groups, icons, and descriptions</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin-left:20px;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 380px; gap:32px; align-items:flex-start;">
            
            <!-- Table of Categories -->
            <div class="card-panel">
                <div class="card-panel-header">
                    <span class="card-panel-title">Active Categories (<?= count($categories) ?>)</span>
                </div>

                <div class="table-responsive">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Slug</th>
                                <th>Description</th>
                                <th>Products</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--gray-900);"><?= e($cat['category_name']) ?></strong>
                                    </td>
                                    <td><code><?= e($cat['slug']) ?></code></td>
                                    <td style="max-width:240px; font-size:12.5px; color:var(--gray-600);">
                                        <?= e(mb_strimwidth($cat['description'] ?? '', 0, 70, '...')) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-primary"><?= (int)$cat['total_products'] ?> items</span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <a href="<?= url('admin/categories.php?edit_id=' . $cat['category_id']) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">
                                                Edit
                                            </a>
                                            <?php if ((int)$cat['total_products'] === 0): ?>
                                                <a href="<?= url('admin/categories.php?delete_id=' . $cat['category_id']) ?>" class="btn btn-secondary btn-sm" style="color:var(--danger-500); font-size:12px;" onclick="return confirm('Delete this category?');">
                                                    Delete
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" style="font-size:12px; opacity:0.5;" disabled title="Cannot delete category with assigned products">
                                                    Locked
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add / Edit Form Card -->
            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:24px; box-shadow:var(--shadow-sm); position:sticky; top:94px;">
                <h3 style="font-size:17px; font-weight:700; color:var(--gray-900); margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border-color);">
                    <?= $editCat ? 'Edit Category' : 'Add New Category' ?>
                </h3>

                <form action="" method="POST">
                    <?php if ($editCat): ?>
                        <input type="hidden" name="category_id" value="<?= $editCat['category_id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Category Name <span class="req">*</span></label>
                        <input type="text" name="category_name" class="form-control" placeholder="e.g. Organic Honey, Spices" value="<?= e($editCat['category_name'] ?? $_POST['category_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control" placeholder="auto-generated if blank" value="<?= e($editCat['slug'] ?? $_POST['slug'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Header Image URL</label>
                        <input type="url" name="image" class="form-control" placeholder="https://..." value="<?= e($editCat['image'] ?? $_POST['image'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" placeholder="Brief summary of crops and goods in this category..."><?= e($editCat['description'] ?? $_POST['description'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                        <?= $editCat ? 'Update Category' : 'Create Category' ?>
                    </button>

                    <?php if ($editCat): ?>
                        <div style="text-align:center; margin-top:10px;">
                            <a href="<?= url('admin/categories.php') ?>" style="font-size:13px; color:var(--gray-500);">Cancel Edit</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
