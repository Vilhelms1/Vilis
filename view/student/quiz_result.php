<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$result_id = $_GET['result_id'] ?? 0;

// Get quiz result
$result_query = "SELECT qr.*, q.title, q.passing_score, q.class_id FROM quiz_results qr INNER JOIN quizzes q ON qr.quiz_id = q.id WHERE qr.id = ? AND qr.user_id = ?";
$result_stmt = $conn->prepare($result_query);
$result_stmt->bind_param("ii", $result_id, $user_id);
$result_stmt->execute();
$quiz_result = $result_stmt->get_result()->fetch_assoc();

if (!$quiz_result) {
    header('Location: student_dashboard.php');
    exit();
}

// Get student answers
$answers_query = "SELECT sa.*, q.question_text, a.answer_text, qa.answer_text as correct_answer FROM student_answers sa INNER JOIN questions q ON sa.question_id = q.id INNER JOIN answers a ON sa.answer_id = a.id LEFT JOIN answers qa ON qa.question_id = q.id AND qa.is_correct = 1 WHERE sa.result_id = ? ORDER BY q.id";
$answers_stmt = $conn->prepare($answers_query);
$answers_stmt->bind_param("i", $result_id);
$answers_stmt->execute();
$answers = $answers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">Quiz Results</div>
            <a href="../process_logout.php" class="btn btn-small">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="result-summary">
            <h1><?php echo htmlspecialchars($quiz_result['title']); ?></h1>
            
            <div class="score-display">
                <div class="large-score <?php echo $quiz_result['passed'] ? 'passed' : 'failed'; ?>">
                    <?php echo round($quiz_result['percentage'], 1); ?>%
                </div>
                <h2><?php echo $quiz_result['passed'] ? '✓ PASSED' : '✗ FAILED'; ?></h2>
                <p>Passing score: <?php echo $quiz_result['passing_score']; ?>%</p>
            </div>
            
            <div class="result-stats">
                <div class="stat">
                    <span>Score:</span>
                    <strong><?php echo $quiz_result['score']; ?> / <?php echo $quiz_result['total_points']; ?> points</strong>
                </div>
                <div class="stat">
                    <span>Time Taken:</span>
                    <strong><?php echo floor($quiz_result['time_taken'] / 60); ?> min <?php echo $quiz_result['time_taken'] % 60; ?> sec</strong>
                </div>
                <div class="stat">
                    <span>Submitted:</span>
                    <strong><?php echo date('M d, Y H:i', strtotime($quiz_result['submitted_at'])); ?></strong>
                </div>
            </div>
        </div>
        
        <div class="answers-review">
            <h2>Detailed Review</h2>
            <?php foreach ($answers as $answer): ?>
                <div class="answer-review <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <h4><?php echo htmlspecialchars($answer['question_text']); ?></h4>
                    <div class="review-content">
                        <p>Your answer: <strong><?php echo htmlspecialchars($answer['answer_text']); ?></strong></p>
                        <?php if (!$answer['is_correct']): ?>
                            <p>Correct answer: <strong><?php echo htmlspecialchars($answer['correct_answer']); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <span class="review-badge"><?php echo $answer['is_correct'] ? '✓ Correct' : '✗ Incorrect'; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="action-buttons">
            <a href="class_detail.php?id=<?php echo $quiz_result['class_id']; ?>" class="btn btn-primary">Back to Class</a>
        </div>
    </div>
</body>
</html>
