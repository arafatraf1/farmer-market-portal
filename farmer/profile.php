<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$db = get_db();

$stmtFarmer = $db->prepare("SELECT * FROM farmers WHERE user_id = ?");
$stmtFarmer->execute([$user['user_id']]);
$farmer = $stmtFarmer->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $farmName = trim($_POST['farm_name'] ?? '');
    $farmLocation = trim($_POST['farm_location'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($name) || empty($phone) || empty($farmName) || empty($farmLocation)) {
        $errors[] = 'Full name, phone, farm name, and location are required.';
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
        $db->beginTransaction();
        try {
            // Update user
            if (!empty($newPassword)) {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmtU = $db->prepare("UPDATE users SET name = ?, phone = ?, profile_image = ?, password = ? WHERE user_id = ?");
                $stmtU->execute([$name, $phone, $profileImage, $hash, $user['user_id']]);
            } else {
                $stmtU = $db->prepare("UPDATE users SET name = ?, phone = ?, profile_image = ? WHERE user_id = ?");
                $stmtU->execute([$name, $phone, $profileImage, $user['user_id']]);
            }

            // Update farmer
            $stmtF = $db->prepare("UPDATE farmers SET farm_name = ?, farm_location = ?, bio = ? WHERE farmer_id = ?");
            $stmtF->execute([$farmName, $farmLocation, $bio, $farmer['farmer_id']]);

            $db->commit();

            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['profile_image'] = $profileImage;

            set_flash('success', 'Farm profile updated successfully.');
            header('Location: ' . url('farmer/profile.php'));
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Farm Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/farmer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Edit Farm Profile & Details</h1>
                <p>Customize your public agricultural presence and farm location</p>
            </div>
            <a href="<?= url('farmer-profile.php?id=' . $farmer['farmer_id']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                View Public Profile &rarr;
            </a>
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

        <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:32px; max-width:740px; box-shadow:var(--shadow-sm);">
            <form action="" method="POST" enctype="multipart/form-data">
                
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:28px; padding-bottom:20px; border-bottom:1px solid var(--border-color);">
                    <img src="<?= e($user['profile_image'] ?: 'https://images.unsplash.com/photo-1595273670150-bd0c3c392e46?auto=format&fit=crop&w=150&q=80') ?>" alt="Farmer" style="width:74px; height:74px; border-radius:50%; object-fit:cover; border:2px solid var(--primary-200);">
                    <div>
                        <label class="form-label" style="margin-bottom:4px;">Change Farmer Profile Photo</label>
                        <input type="file" name="profile_photo" class="form-control" accept="image/*" style="font-size:12px;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Farmer Full Name <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Phone <span class="req">*</span></label>
                        <input type="tel" name="phone" class="form-control" value="<?= e($user['phone']) ?>" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Farm Name <span class="req">*</span></label>
                        <input type="text" name="farm_name" class="form-control" value="<?= e($farmer['farm_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Farm Location / Region <span class="req">*</span></label>
                        <input type="text" name="farm_location" class="form-control" value="<?= e($farmer['farm_location']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">About the Farm & Agricultural Background</label>
                    <textarea name="bio" class="form-control" style="min-height:120px;" placeholder="Detail your farming history, crop specializations, organic certifications, soil care..."><?= e($farmer['bio']) ?></textarea>
                </div>

                <div class="form-group" style="margin-top:24px; padding-top:20px; border-top:1px solid var(--border-color);">
                    <label class="form-label">Update Password (Optional)</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="margin-top:10px;">
                    Save Farm Profile
                </button>
            </form>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
