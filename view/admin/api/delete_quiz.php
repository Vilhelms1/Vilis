<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$quiz_id = (int)($input['quiz_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = AuthController::is_admin();

if ($quiz_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quiz id']);
    exit();
}

$verify_query = $is_admin
    ? "SELECT id FROM quizzes WHERE id = ? AND is_active = 1"
    : "SELECT q.id FROM quizzes q INNER JOIN classes c ON q.class_id = c.id WHERE q.id = ? AND c.teacher_id = ? AND q.is_active = 1";
$verify = $conn->prepare($verify_query);
if ($is_admin) {
    $verify->bind_param("i", $quiz_id);
} else {
    $verify->bind_param("ii", $quiz_id, $user_id);
}
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit();
}

$delete_query = $is_admin
    ? "UPDATE quizzes SET is_active = 0 WHERE id = ?"
    : "UPDATE quizzes q INNER JOIN classes c ON q.class_id = c.id SET q.is_active = 0 WHERE q.id = ? AND c.teacher_id = ?";
$del = $conn->prepare($delete_query);
if ($is_admin) {
    $del->bind_param("i", $quiz_id);
} else {
    $del->bind_param("ii", $quiz_id, $user_id);
}
$ok = $del->execute();

echo json_encode(['success' => $ok]);
