<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$question_id = (int)($input['question_id'] ?? 0);
$admin_id = $_SESSION['user_id'];

if ($question_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid question id']);
    exit();
}

$verify = $conn->prepare("SELECT q.id FROM questions q INNER JOIN quizzes z ON q.quiz_id = z.id WHERE q.id = ? AND z.admin_id = ?");
$verify->bind_param("ii", $question_id, $admin_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit();
}

$del = $conn->prepare("DELETE FROM questions WHERE id = ?");
$del->bind_param("i", $question_id);
$ok = $del->execute();

echo json_encode(['success' => $ok]);
