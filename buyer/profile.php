<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('buyer');
$user = current_user();
$db = get_db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($name) || empty($phone)) {
        $errors[] = 'Full name and contact phone are required.';
    }

    $profileImage = $user['profile_image'];
    if (!empty($_FILES['profile_photo']['name']) && empty($errors)) {
        $up = upload_image($_FILES['profile_photo'], 'avatars');
        if ($up['success']) {
            $profileImage = $up['filename'];
        } else {
            $errors[] = $up['error'];
        }
    }

    if (empty($errors)) {
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, address = ?, profile_image = ?, password = ? WHERE user_id = ?");
                $stmt->execute([$name, $phone, $address, $profileImage, $hash, $user['user_id']]);
            }
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, address = ?, profile_image = ? WHERE user_id = ?");
            $stmt->execute([$name, $phone, $address, $profileImage, $user['user_id']]);
        }

        if (empty($errors)) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['address'] = $address;
            $_SESSION['user']['profile_image'] = $profileImage;
            set_flash('success', 'Profile and delivery address updated successfully.');
            header('Location: ' . url('buyer/profile.php'));
            exit;
        }
    }
}

$pageTitle = 'Delivery Profile & Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/buyer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Delivery Profile & Account</h1>
                <p>Manage your delivery address and contact information</p>
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

        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:32px; max-width:680px; box-shadow:var(--shadow-sm);">
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:28px; padding-bottom:20px; border-bottom:1px solid var(--border-color);">
                    <img src="<?= e($user['profile_image'] ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80') ?>" alt="Profile" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:2px solid var(--primary-200);">
                    <div>
                        <label class="form-label" style="margin-bottom:4px;">Change Profile Photo</label>
                        <input type="file" name="profile_photo" class="form-control" accept="image/*" style="font-size:12px;">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" value="<?= e($user['email']) ?>" readonly style="background:var(--gray-50);">
                    <div class="form-hint">Email address cannot be changed.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Phone Number <span class="req">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?= e($user['phone']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Delivery Address</label>
                    <textarea name="address" class="form-control" placeholder="House/Flat, Street, Area, City..."><?= e($user['address']) ?></textarea>
                    <div class="form-hint">This address will be automatically populated during checkout.</div>
                </div>

                <div class="form-group" style="margin-top:24px; padding-top:20px; border-top:1px solid var(--border-color);">
                    <label class="form-label">Update Password (Optional)</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="margin-top:10px;">
                    Save Profile Changes
                </button>
            </form>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
