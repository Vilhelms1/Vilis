<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/QuizController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = AuthController::is_admin();
$class_id = (int)($_POST['class_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$time_limit = (int)($_POST['time_limit'] ?? 0);
$passing_score = (int)($_POST['passing_score'] ?? 60);

if ($class_id <= 0 || $title === '') {
    echo json_encode(['success' => false, 'message' => 'Class and title are required']);
    exit();
}

$verify_query = $is_admin
    ? "SELECT id FROM classes WHERE id = ?"
    : "SELECT id FROM classes WHERE id = ? AND teacher_id = ?";
$verify = $conn->prepare($verify_query);
if ($is_admin) {
    $verify->bind_param("i", $class_id);
} else {
    $verify->bind_param("ii", $class_id, $user_id);
}
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid class']);
    exit();
}

$show_leaderboard = isset($_POST['show_leaderboard']) ? (int)(!empty($_POST['show_leaderboard'])) : 1;

$result = QuizController::create_quiz(
    $class_id,
    $title,
    $description,
    $user_id,
    $time_limit,
    $passing_score,
    $show_leaderboard
);

echo json_encode($result);
