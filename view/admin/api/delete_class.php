<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$class_id = (int)($input['class_id'] ?? 0);
$admin_id = $_SESSION['user_id'];

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid class id']);
    exit();
}

$verify = $conn->prepare("SELECT id FROM classes WHERE id = ? AND admin_id = ?");
$verify->bind_param("ii", $class_id, $admin_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit();
}

$del = $conn->prepare("DELETE FROM classes WHERE id = ? AND admin_id = ?");
$del->bind_param("ii", $class_id, $admin_id);
$ok = $del->execute();

echo json_encode(['success' => $ok]);
