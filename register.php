<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$initialRole = in_array(trim($_GET['role'] ?? ''), ['buyer', 'farmer']) ? trim($_GET['role']) : 'buyer';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = in_array($_POST['role'] ?? '', ['buyer', 'farmer']) ? $_POST['role'] : 'buyer';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    // Farmer specific fields
    $farmName = trim($_POST['farm_name'] ?? '');
    $farmLocation = trim($_POST['farm_location'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $docType = in_array($_POST['doc_type'] ?? '', ['nid', 'birth_certificate']) ? $_POST['doc_type'] : 'nid';

    // Validations
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $errors[] = 'Please fill in all required user fields.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if ($role === 'farmer') {
        if (empty($farmName) || empty($farmLocation)) {
            $errors[] = 'Farmers must provide their Farm Name and Farm Location.';
        }
        if (empty($_FILES['verification_document']['name'])) {
            $errors[] = 'Farmers must upload an identity verification document (NID or Birth Certificate).';
        }
    }

    $db = get_db();

    // Check duplicate email
    $stmtCheck = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        $errors[] = 'An account with this email address already exists. Please log in.';
    }

    // Process Avatar (optional)
    $profileImage = null;
    if (!empty($_FILES['profile_photo']['name']) && empty($errors)) {
        $up = upload_image($_FILES['profile_photo'], 'avatars');
        if ($up['success']) {
            $profileImage = $up['filename'];
        }
    }

    // Process Secure Doc (farmer mandatory)
    $secureDocFilename = null;
    if ($role === 'farmer' && !empty($_FILES['verification_document']['name']) && empty($errors)) {
        $upDoc = upload_secure_document($_FILES['verification_document']);
        if ($upDoc['success']) {
            $secureDocFilename = $upDoc['filename'];
        } else {
            $errors[] = $upDoc['error'];
        }
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            // 1. Insert user
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $db->prepare("
                INSERT INTO users (name, email, phone, password, role, address, profile_image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmtUser->execute([
                $name,
                $email,
                $phone,
                $hashedPassword,
                $role,
                $address,
                $profileImage
            ]);
            $userId = (int)$db->lastInsertId();

            // 2. Insert role-specific profile
            if ($role === 'farmer') {
                $stmtFarmer = $db->prepare("
                    INSERT INTO farmers (user_id, farm_name, farm_location, bio, verification_status, verification_doc_type, verification_doc_path)
                    VALUES (?, ?, ?, ?, 'pending', ?, ?)
                ");
                $stmtFarmer->execute([
                    $userId,
                    $farmName,
                    $farmLocation,
                    $bio,
                    $docType,
                    $secureDocFilename
                ]);
            } else {
                $stmtBuyer = $db->prepare("INSERT INTO buyers (user_id) VALUES (?)");
                $stmtBuyer->execute([$userId]);
            }

            $db->commit();

            // Auto log-in
            $_SESSION['user'] = [
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'profile_image' => $profileImage,
                'phone' => $phone,
                'address' => $address
            ];

            if ($role === 'farmer') {
                set_flash('success', 'Farmer registration successful! Your identity document has been submitted to the Admin for verification review.');
                header('Location: ' . url('farmer/dashboard.php'));
            } else {
                set_flash('success', 'Welcome to Farmer Market Portal! Your buyer account is ready.');
                header('Location: ' . url('browse.php'));
            }
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create an Account — Farmer Market Portal';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 50px 20px 90px; max-width: 680px;">
    <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:20px; padding:40px; box-shadow:var(--shadow-md);">
        
        <div style="text-align:center; margin-bottom:28px;">
            <span class="verified-badge" style="font-size:12px; padding:4px 10px; margin-bottom:10px;">Direct Agricultural Network</span>
            <h1 style="font-size:26px; font-weight:800; color:var(--gray-900);">Join Farmer Market Portal</h1>
            <p style="color:var(--gray-500); font-size:14px; margin-top:4px;">Register as a local farmer or mindful buyer</p>
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

        <form action="" method="POST" enctype="multipart/form-data">
            
            <!-- Role Selector Toggle -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:24px;">
                <label style="cursor:pointer;">
                    <input type="radio" name="role" value="buyer" <?= $initialRole === 'buyer' ? 'checked' : '' ?> onchange="toggleRoleFields('buyer')" style="display:none;" id="roleBuyer">
                    <div id="roleBuyerCard" style="border:2px solid var(--primary-600); background:var(--primary-50); border-radius:12px; padding:16px; text-align:center; transition:var(--transition);">
                        <div style="font-size:22px; margin-bottom:4px;">🛒</div>
                        <strong style="display:block; font-size:15px; color:var(--gray-900);">Join as Buyer</strong>
                        <span style="font-size:12px; color:var(--gray-500);">Browse, order, & track fresh produce</span>
                    </div>
                </label>

                <label style="cursor:pointer;">
                    <input type="radio" name="role" value="farmer" <?= $initialRole === 'farmer' ? 'checked' : '' ?> onchange="toggleRoleFields('farmer')" style="display:none;" id="roleFarmer">
                    <div id="roleFarmerCard" style="border:2px solid var(--gray-200); background:#ffffff; border-radius:12px; padding:16px; text-align:center; transition:var(--transition);">
                        <div style="font-size:22px; margin-bottom:4px;">🌾</div>
                        <strong style="display:block; font-size:15px; color:var(--gray-900);">Join as Farmer</strong>
                        <span style="font-size:12px; color:var(--gray-500);">Verify identity & sell directly to buyers</span>
                    </div>
                </label>
            </div>

            <!-- Common Details Section -->
            <div style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray-400); margin-bottom:14px;">
                Personal Information
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Abdur Rahim" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number <span class="req">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="+880 1700-000000" value="<?= e($_POST['phone'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address <span class="req">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="you@domain.com" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label">Password <span class="req">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="req">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Address / Location</label>
                <input type="text" name="address" class="form-control" placeholder="Village / Area, District" value="<?= e($_POST['address'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Profile Photo (Optional)</label>
                <input type="file" name="profile_photo" class="form-control" accept="image/*">
            </div>

            <!-- Farmer-Specific Verification Section -->
            <div id="farmerFields" style="<?= $initialRole === 'farmer' ? 'display:block;' : 'display:none;' ?> margin-top:28px; padding-top:20px; border-top:1px solid var(--border-color);">
                <div style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--primary-700); margin-bottom:14px;">
                    🛡️ Farm & Identity Verification Details
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Farm / Business Name <span class="req">*</span></label>
                        <input type="text" name="farm_name" class="form-control" placeholder="e.g. Green Valley Organic Farm" value="<?= e($_POST['farm_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Farm Location / District <span class="req">*</span></label>
                        <input type="text" name="farm_location" class="form-control" placeholder="e.g. Savar, Dhaka" value="<?= e($_POST['farm_location'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Farming Background & Cultivation Bio</label>
                    <textarea name="bio" class="form-control" placeholder="Share your organic practices, crops cultivated, farm history..."><?= e($_POST['bio'] ?? '') ?></textarea>
                </div>

                <div style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:12px; padding:20px; margin-bottom:20px;">
                    <div style="font-weight:700; font-size:14px; color:var(--gray-900); margin-bottom:6px;">Farmer Identity Document Upload <span class="req">*</span></div>
                    <p style="font-size:12.5px; color:var(--gray-500); line-height:1.5; margin-bottom:14px;">
                        To maintain portal trust and prevent fake sellers, submit your government-issued document. This information is <strong>never displayed publicly</strong> and is reviewed only by authorized administrators.
                    </p>

                    <div class="form-group">
                        <label class="form-label">Accepted Document Type</label>
                        <select name="doc_type" class="form-control">
                            <option value="nid">National ID Card (NID)</option>
                            <option value="birth_certificate">Birth Certificate</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Document File (JPG, PNG, or PDF — Max 8MB)</label>
                        <input type="file" name="verification_document" class="form-control" accept="image/jpeg,image/png,application/pdf" data-preview="docPreview">
                        <div id="docPreview" class="image-upload-preview" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width:100%; margin-top:20px;">
                Complete Registration
            </button>
        </form>

        <div style="margin-top:20px; text-align:center; font-size:13.5px; color:var(--gray-600);">
            Already have an account? <a href="<?= url('login.php') ?>" style="font-weight:700; color:var(--primary-700);">Sign in here</a>
        </div>

    </div>
</div>

<script>
function toggleRoleFields(role) {
    const farmerFields = document.getElementById('farmerFields');
    const buyerCard = document.getElementById('roleBuyerCard');
    const farmerCard = document.getElementById('roleFarmerCard');

    if (role === 'farmer') {
        farmerFields.style.display = 'block';
        farmerCard.style.borderColor = 'var(--primary-600)';
        farmerCard.style.background = 'var(--primary-50)';
        buyerCard.style.borderColor = 'var(--gray-200)';
        buyerCard.style.background = '#ffffff';
    } else {
        farmerFields.style.display = 'none';
        buyerCard.style.borderColor = 'var(--primary-600)';
        buyerCard.style.background = 'var(--primary-50)';
        farmerCard.style.borderColor = 'var(--gray-200)';
        farmerCard.style.background = '#ffffff';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
