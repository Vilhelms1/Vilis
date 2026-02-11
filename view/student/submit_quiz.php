<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: student_dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = $_POST['quiz_id'] ?? 0;
$time_taken = $_POST['time_taken'] ?? 0;

// Get quiz for class verification
$quiz_query = "SELECT class_id FROM quizzes WHERE id = ? AND is_active = 1";
$quiz_stmt = $conn->prepare($quiz_query);
$quiz_stmt->bind_param("i", $quiz_id);
$quiz_stmt->execute();
$quiz = $quiz_stmt->get_result()->fetch_assoc();

if (!$quiz) {
    header('Location: student_dashboard.php');
    exit();
}

// Verify enrollment
$enroll_check = "SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?";
$enroll_stmt = $conn->prepare($enroll_check);
$enroll_stmt->bind_param("ii", $quiz['class_id'], $user_id);
$enroll_stmt->execute();
if ($enroll_stmt->get_result()->num_rows === 0) {
    header('Location: student_dashboard.php');
    exit();
}

// Build answers array from POST data
$answers = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'answer_') === 0) {
        $question_id = str_replace('answer_', '', $key);
        $answers[$question_id] = $value;
    }
}

// Submit quiz
$result = QuizController::submit_quiz($quiz_id, $user_id, $answers, $time_taken);

if ($result['success']) {
    header('Location: quiz_result.php?result_id=' . $result['result_id']);
    exit();
} else {
    header('Location: class_details.php?id=' . $quiz['class_id'] . '&error=submission_failed');
    exit();
}
?>
