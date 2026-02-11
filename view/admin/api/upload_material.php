<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['user_id'];
$class_id = (int)($_POST['class_id'] ?? 0);

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid class id']);
    exit();
}

$verify = $conn->prepare("SELECT id FROM classes WHERE id = ? AND admin_id = ?");
$verify->bind_param("ii", $class_id, $admin_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid class']);
    exit();
}

if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
    exit();
}

$allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip'];
$originalName = $_FILES['document']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit();
}

$uploadsDir = realpath(__DIR__ . '/../../../uploads');
if ($uploadsDir === false) {
    echo json_encode(['success' => false, 'message' => 'Uploads folder missing']);
    exit();
}

$baseName = pathinfo($originalName, PATHINFO_FILENAME);
$safeBase = preg_replace('/[^a-zA-Z0-9-_ ]/', '', $baseName);
$safeBase = trim($safeBase) !== '' ? trim($safeBase) : 'material';

$fileName = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $fileName;

if (!move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

$title = $safeBase;
$file_type = $ext;

$ins = $conn->prepare("INSERT INTO class_materials (class_id, title, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?)");
$ins->bind_param("isssi", $class_id, $title, $fileName, $file_type, $admin_id);
$ok = $ins->execute();

if (!$ok) {
    @unlink($targetPath);
    echo json_encode(['success' => false, 'message' => 'Database insert failed']);
    exit();
}

echo json_encode(['success' => true]);
