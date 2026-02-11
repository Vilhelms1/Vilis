<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/classController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Class name is required']);
    exit();
}

$result = ClassController::create_class($name, $description, $admin_id);

echo json_encode($result);
