<?php
/**
 * Secure Document Streamer (Admin Only)
 * Streams confidential NID or Birth Certificate files strictly to authenticated administrators.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Strict Admin role enforcement
require_role('admin');

$farmerId = (int)($_GET['farmer_id'] ?? 0);
if ($farmerId <= 0) {
    http_response_code(400);
    die('Invalid farmer specified.');
}

$db = get_db();
$stmt = $db->prepare("SELECT farm_name, verification_doc_type, verification_doc_path FROM farmers WHERE farmer_id = ?");
$stmt->execute([$farmerId]);
$doc = $stmt->fetch();

if (!$doc || empty($doc['verification_doc_path'])) {
    http_response_code(404);
    die('No verification document found for this farmer.');
}

$filename = basename($doc['verification_doc_path']);
$filePath = SECURE_DOC_PATH . DIRECTORY_SEPARATOR . $filename;

if (!file_exists($filePath)) {
    http_response_code(404);
    die('Document file does not exist on the secure server storage.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
