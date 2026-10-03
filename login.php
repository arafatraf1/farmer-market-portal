<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

if (is_logged_in()) {
    $role = user_role();
    $target = match($role) {
        'farmer' => 'farmer/dashboard.php',
        'admin' => 'admin/dashboard.php',
        default => 'buyer/dashboard.php'
    };
    header('Location: ' . url($target));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please enter both email and password.';
    } else {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email address or password.';
        } elseif ($user['status'] === 'suspended') {
            $errors[] = 'Your account has been suspended by the platform administrator. Please contact support.';
        } else {
            // Login success
            $_SESSION['user'] = [
                'user_id' => (int)$user['user_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'profile_image' => $user['profile_image'],
                'phone' => $user['phone'],
                'address' => $user['address']
            ];

            set_flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

            // Determine redirect
            $redirect = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);

            if ($redirect) {
                header('Location: ' . $redirect);
            } else {
                $target = match($user['role']) {
                    'farmer' => 'farmer/dashboard.php',
                    'admin' => 'admin/dashboard.php',
                    default => 'buyer/dashboard.php'
                };
                header('Location: ' . url($target));
            }
            exit;
        }
    }
}

$pageTitle = 'Sign In — Farmer Market Portal';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding: 60px 20px 90px; max-width: 520px;">
    <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:20px; padding:40px; box-shadow:var(--shadow-md);">
        
        <div style="text-align:center; margin-bottom:28px;">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:52px; height:52px; background:var(--primary-100); color:var(--primary-700); border-radius:50%; margin-bottom:12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
            </div>
            <h1 style="font-size:24px; font-weight:800; color:var(--gray-900);">Sign In to Portal</h1>
            <p style="color:var(--gray-500); font-size:14px; margin-top:4px;">Access your farmer or buyer account</p>
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

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label" for="loginEmail">Email Address</label>
                <input type="email" id="loginEmail" name="email" class="form-control" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <label class="form-label" for="loginPass" style="margin-bottom:0;">Password</label>
                </div>
                <input type="password" id="loginPass" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width:100%; margin-top:10px;">
                Sign In
            </button>
        </form>

        <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--border-color); text-align:center; font-size:13.5px; color:var(--gray-600);">
            Don't have an account yet? <a href="<?= url('register.php') ?>" style="font-weight:700; color:var(--primary-700);">Create one now</a>
        </div>

        <!-- 1-Click Quick Demo Login Helper -->
        <div style="margin-top:28px; background:var(--gray-50); border:1px dashed var(--gray-300); border-radius:12px; padding:16px;">
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--gray-500); margin-bottom:10px; text-align:center;">
                ⚡ 1-Click Quick Demo Sign-In
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="setDemo('admin@farmermarket.com', 'admin123');" style="font-size:11.5px;">
                    👑 Admin
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setDemo('farmer.rahim@farmermarket.com', 'farmer123');" style="font-size:11.5px; color:#15803d;">
                    🌱 Verified Farmer
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setDemo('farmer.karim@farmermarket.com', 'farmer123');" style="font-size:11.5px; color:#b45309;">
                    ⏳ Pending Farmer
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setDemo('buyer.anita@farmermarket.com', 'buyer123');" style="font-size:11.5px; color:#2563eb;">
                    🛒 Buyer Anita
                </button>
            </div>
        </div>

    </div>
</div>

<script>
function setDemo(email, pass) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPass').value = pass;
    document.forms[0].submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
