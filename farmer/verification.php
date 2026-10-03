<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

require_role('farmer');
$user = current_user();
$db = get_db();

$stmt = $db->prepare("SELECT * FROM farmers WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$farmer = $stmt->fetch();

$errors = [];

// Re-upload verification document handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docType = in_array($_POST['doc_type'] ?? '', ['nid', 'birth_certificate']) ? $_POST['doc_type'] : 'nid';

    if (empty($_FILES['verification_document']['name'])) {
        $errors[] = 'Please choose a valid NID or Birth Certificate document file.';
    } else {
        $up = upload_secure_document($_FILES['verification_document']);
        if ($up['success']) {
            $stmtUpdate = $db->prepare("
                UPDATE farmers 
                SET verification_status = 'pending', verification_doc_type = ?, verification_doc_path = ?, admin_notes = NULL
                WHERE farmer_id = ?
            ");
            $stmtUpdate->execute([$docType, $up['filename'], $farmer['farmer_id']]);
            set_flash('success', 'Your verification document was updated successfully and is now pending admin audit.');
            header('Location: ' . url('farmer/verification.php'));
            exit;
        } else {
            $errors[] = $up['error'];
        }
    }
}

$pageTitle = 'Identity Verification Status';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/farmer_nav.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Farmer Identity Verification</h1>
                <p>Build platform credibility and unlock the Verified Farmer badge</p>
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

        <!-- Current Verification Status Card -->
        <?php if ($farmer['verification_status'] === 'verified'): ?>
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:16px; padding:32px; margin-bottom:30px; display:flex; align-items:center; gap:20px;">
                <div style="width:64px; height:64px; background:#15803d; color:#ffffff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:32px;">
                    ✓
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                        <h2 style="font-size:22px; color:#14532d; font-weight:800;">Identity Verified Farmer</h2>
                        <?= get_verification_badge('verified') ?>
                    </div>
                    <p style="color:#166534; font-size:14.5px; line-height:1.6;">
                        Congratulations! Your submitted documentation has been audited and approved by administrators. The <strong>Verified Farmer Badge</strong> is now prominently displayed across all your product listings and public farm profile.
                    </p>
                </div>
            </div>
        <?php elseif ($farmer['verification_status'] === 'pending'): ?>
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:16px; padding:32px; margin-bottom:30px; display:flex; align-items:center; gap:20px;">
                <div style="width:64px; height:64px; background:#d97706; color:#ffffff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px;">
                    ⏳
                </div>
                <div>
                    <h2 style="font-size:20px; color:#92400e; font-weight:800; margin-bottom:6px;">Verification Pending Review</h2>
                    <p style="color:#b45309; font-size:14px; line-height:1.6;">
                        Your submitted <strong><?= strtoupper(e($farmer['verification_doc_type'])) ?></strong> is in our review queue. Platform administrators verify documents to protect consumer trust and fair agricultural trade.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:16px; padding:32px; margin-bottom:30px; display:flex; align-items:center; gap:20px;">
                <div style="width:64px; height:64px; background:#dc2626; color:#ffffff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px;">
                    ✕
                </div>
                <div>
                    <h2 style="font-size:20px; color:#991b1b; font-weight:800; margin-bottom:6px;">Verification Rejected</h2>
                    <p style="color:#b91c1c; font-size:14px; line-height:1.6; margin-bottom:10px;">
                        The administrator rejected the previous document. Reason: <em><?= e($farmer['admin_notes'] ?: 'Document was illegible or did not match the registrant identity.') ?></em>
                    </p>
                    <p style="color:#7f1d1d; font-size:13px;">Please upload a new, clear photograph or scan below.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Re-upload Form (if not verified) -->
        <?php if ($farmer['verification_status'] !== 'verified'): ?>
            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:16px; padding:32px; max-width:680px; box-shadow:var(--shadow-sm); margin-bottom:30px;">
                <h3 style="font-size:18px; font-weight:700; color:var(--gray-900); margin-bottom:14px;">Upload / Update Identification Document</h3>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Identification Document Type <span class="req">*</span></label>
                        <select name="doc_type" class="form-control" required>
                            <option value="nid" <?= ($farmer['verification_doc_type'] === 'nid') ? 'selected' : '' ?>>National ID (NID)</option>
                            <option value="birth_certificate" <?= ($farmer['verification_doc_type'] === 'birth_certificate') ? 'selected' : '' ?>>Birth Certificate</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Document File (JPG, PNG, or PDF — Max 8MB) <span class="req">*</span></label>
                        <input type="file" name="verification_document" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
                    </div>

                    <div style="background:var(--gray-50); padding:12px 16px; border-radius:8px; font-size:12.5px; color:var(--gray-600); margin-bottom:20px;">
                        🔒 <strong>Privacy Assurance:</strong> Identification documents are encrypted and accessible exclusively by authorized platform administrators. They will NEVER be displayed on your public profile or shared with buyers.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        Submit Document for Verification
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
