<?php
/**
 * Global Helper Functions
 */
require_once __DIR__ . '/config.php';

// Safe HTML Escaping
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// URL Helper
function url(string $path = ''): string {
    $cleanPath = ltrim($path, '/');
    return BASE_URL . '/' . $cleanPath;
}

// User Authentication Helpers
function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function require_login(?string $redirect = null): void {
    if (!is_logged_in()) {
        $target = $redirect ?: $_SERVER['REQUEST_URI'];
        $_SESSION['redirect_after_login'] = $target;
        set_flash('error', 'Please log in to continue.');
        header('Location: ' . url('login.php'));
        exit;
    }
}

function require_role(array|string $roles): void {
    require_login();
    $roles = (array)$roles;
    if (!in_array(user_role(), $roles, true)) {
        set_flash('error', 'You do not have authorization to access this section.');
        header('Location: ' . url('index.php'));
        exit;
    }
}

// Flash Message Helpers
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Currency / Price Formatter (Bangladeshi Taka BDT)
function format_price(float|int|string|null $amount): string {
    $num = (float)($amount ?? 0);
    return '৳' . number_format($num, 2);
}

// Freshness & Expiry Calculation
function get_freshness_status(?string $expiry_date): array {
    if (!$expiry_date) {
        return [
            'days_left' => 999,
            'status' => 'unknown',
            'label' => 'Fresh Farm Produce',
            'class' => 'freshness-fresh',
            'is_expired' => false,
            'is_warning' => false
        ];
    }

    $today = new DateTimeImmutable('today');
    $expiry = new DateTimeImmutable($expiry_date);
    $diff = $today->diff($expiry);
    $days = (int)$diff->format('%r%a');

    if ($days < 0) {
        return [
            'days_left' => $days,
            'status' => 'expired',
            'label' => 'Expired (' . abs($days) . ' days ago)',
            'class' => 'freshness-expired',
            'is_expired' => true,
            'is_warning' => false
        ];
    } elseif ($days === 0) {
        return [
            'days_left' => 0,
            'status' => 'expiring_today',
            'label' => 'Expires Today!',
            'class' => 'freshness-warning',
            'is_expired' => false,
            'is_warning' => true
        ];
    } elseif ($days <= 3) {
        return [
            'days_left' => $days,
            'status' => 'expiring_soon',
            'label' => "Expires in {$days} " . ($days === 1 ? 'day' : 'days'),
            'class' => 'freshness-warning',
            'is_expired' => false,
            'is_warning' => true
        ];
    } else {
        return [
            'days_left' => $days,
            'status' => 'fresh',
            'label' => "Fresh • {$days} days left",
            'class' => 'freshness-fresh',
            'is_expired' => false,
            'is_warning' => false
        ];
    }
}

// Status Badges
function get_order_badge(string $status): string {
    $labels = [
        'pending' => ['Pending', 'badge-pending'],
        'accepted' => ['Accepted', 'badge-info'],
        'preparing' => ['Preparing Produce', 'badge-info'],
        'ready_for_delivery' => ['Ready for Delivery', 'badge-primary'],
        'out_for_delivery' => ['Out for Delivery', 'badge-warning'],
        'delivered' => ['Delivered', 'badge-accent'],
        'completed' => ['Completed', 'badge-success'],
        'cancelled' => ['Cancelled', 'badge-danger'],
        'rejected' => ['Rejected', 'badge-danger']
    ];

    $info = $labels[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'badge-secondary'];
    return '<span class="status-badge ' . $info[1] . '">' . e($info[0]) . '</span>';
}

function get_verification_badge(string $status): string {
    switch ($status) {
        case 'verified':
            return '<span class="verified-badge" title="Identity Verified by Admin"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Verified Farmer</span>';
        case 'pending':
            return '<span class="status-badge badge-warning" title="Verification Pending Review">Pending Verification</span>';
        case 'rejected':
            return '<span class="status-badge badge-danger" title="Verification Rejected">Verification Rejected</span>';
        default:
            return '<span class="status-badge badge-secondary">Unverified</span>';
    }
}

function get_buyer_trust_info(int $completed, int $cancelled): array {
    $total = $completed + $cancelled;
    if ($total === 0) {
        return [
            'score' => 100,
            'rate_text' => 'New Buyer',
            'badge_class' => 'badge-info',
            'description' => 'First-time buyer on portal'
        ];
    }
    $rate = round(($completed / $total) * 100);
    if ($rate >= 90) {
        $badge = 'badge-success';
        $desc = 'Highly Reliable Buyer (' . $completed . ' completed)';
    } elseif ($rate >= 75) {
        $badge = 'badge-primary';
        $desc = 'Reliable Buyer (' . $completed . ' completed)';
    } else {
        $badge = 'badge-warning';
        $desc = 'Moderate (' . $cancelled . ' cancellations)';
    }

    return [
        'score' => $rate,
        'rate_text' => $rate . '% Completion',
        'badge_class' => $badge,
        'description' => $desc
    ];
}

// Image & Secure File Upload Functions
function upload_image(array $file, string $subfolder = 'products'): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }

    $maxBytes = 5 * 1024 * 1024; // 5MB limit
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'Image exceeds maximum size of 5MB.'];
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'Invalid image format. Allowed formats: JPG, PNG, WEBP.'];
    }

    $ext = match ($mime) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    $targetDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . $subfolder;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }

    return ['success' => true, 'filename' => 'storage/uploads/' . $subfolder . '/' . $filename];
}

function upload_secure_document(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Document upload failed. Code: ' . $file['error']];
    }

    $maxBytes = 8 * 1024 * 1024; // 8MB limit
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'Verification document must not exceed 8MB.'];
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'Document must be a JPG, PNG, or PDF file.'];
    }

    $ext = match ($mime) {
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        default => 'jpg'
    };

    if (!is_dir(SECURE_DOC_PATH)) {
        mkdir(SECURE_DOC_PATH, 0700, true);
    }

    // Protect secure_docs with an .htaccess if served via Apache
    $htaccess = SECURE_DOC_PATH . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }

    $filename = 'doc_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = SECURE_DOC_PATH . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to store verification document securely.'];
    }

    return ['success' => true, 'filename' => $filename];
}

// Shopping Cart Helpers
function get_cart(): array {
    return $_SESSION['cart'] ?? [];
}

function get_cart_count(): int {
    $cart = get_cart();
    $count = 0;
    foreach ($cart as $item) {
        $count += (int)($item['quantity'] ?? 0);
    }
    return $count;
}

function get_cart_total(): float {
    $cart = get_cart();
    $total = 0.0;
    foreach ($cart as $item) {
        $total += ((float)$item['price']) * ((int)$item['quantity']);
    }
    return $total;
}

// Star rating display
function render_stars(float $rating, int $count = null): string {
    $html = '<div class="star-rating" title="' . number_format($rating, 1) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<span class="star filled">★</span>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<span class="star half">★</span>';
        } else {
            $html .= '<span class="star empty">☆</span>';
        }
    }
    $html .= ' <span class="rating-num">' . number_format($rating, 1) . '</span>';
    if ($count !== null) {
        $html .= ' <span class="review-count">(' . $count . ')</span>';
    }
    $html .= '</div>';
    return $html;
}
