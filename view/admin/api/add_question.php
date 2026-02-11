<?php
require_once __DIR__ . '/../../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../../controlers__(loģistika)/QuizController.php';

header('Content-Type: application/json');

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['user_id'];
$quiz_id = (int)($_POST['quiz_id'] ?? 0);
$question_text = trim($_POST['question_text'] ?? '');
$question_type = $_POST['question_type'] ?? 'multiple_choice';
$points = (int)($_POST['points'] ?? 1);
$answers_json = $_POST['answers'] ?? '[]';
$answers = json_decode($answers_json, true);

if ($quiz_id <= 0 || $question_text === '' || !is_array($answers) || count($answers) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$verify = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND admin_id = ?");
$verify->bind_param("ii", $quiz_id, $admin_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quiz']);
    exit();
}

$q_result = QuizController::add_question($quiz_id, $question_text, $question_type, $points);
if (empty($q_result['success'])) {
    echo json_encode($q_result);
    exit();
}

$question_id = (int)$q_result['question_id'];
foreach ($answers as $answer) {
    $text = trim($answer['text'] ?? '');
    $correct = !empty($answer['correct']) ? 1 : 0;
    if ($text !== '') {
        QuizController::add_answer($question_id, $text, $correct);
    }
}

echo json_encode(['success' => true]);
