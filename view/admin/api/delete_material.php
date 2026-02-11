<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$material_id = (int)($input['material_id'] ?? 0);
$admin_id = $_SESSION['user_id'];

if ($material_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid material id']);
    exit();
}

$verify = $conn->prepare("SELECT cm.file_path FROM class_materials cm INNER JOIN classes c ON cm.class_id = c.id WHERE cm.id = ? AND c.admin_id = ?");
$verify->bind_param("ii", $material_id, $admin_id);
$verify->execute();
$row = $verify->get_result()->fetch_assoc();
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit();
}

$del = $conn->prepare("DELETE FROM class_materials WHERE id = ?");
$del->bind_param("i", $material_id);
$ok = $del->execute();

if ($ok && !empty($row['file_path'])) {
    $filePath = realpath(__DIR__ . '/../../../uploads') . DIRECTORY_SEPARATOR . $row['file_path'];
    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

echo json_encode(['success' => $ok]);
