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
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <div class="nav-brand">Quiz Results</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="result-summary">
            <h1><?php echo htmlspecialchars($quiz_result['title']); ?></h1>
            
            <div class="score-display">
                <div class="large-score <?php echo $quiz_result['passed'] ? 'passed' : 'failed'; ?>">
                    <?php echo round($quiz_result['percentage'], 1); ?>%
                </div>
                <h2><?php echo $quiz_result['passed'] ? '✓ Nokārtots' : '✗ Nenokārtots'; ?></h2>
                <p>Nokārtošanas slieksnis: <?php echo $quiz_result['passing_score']; ?>%</p>
                <?php if (!empty($quiz_result['grade'])): ?>
                    <p>Atzīme: <?php echo (int)$quiz_result['grade']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="result-stats">
                <div class="stat">
                    <span>Punkti:</span>
                    <strong><?php echo $quiz_result['score']; ?> / <?php echo $quiz_result['total_points']; ?> punkti</strong>
                </div>
                <div class="stat">
                    <span>Laiks:</span>
                    <strong><?php echo floor($quiz_result['time_taken'] / 60); ?> min <?php echo $quiz_result['time_taken'] % 60; ?> sek</strong>
                </div>
                <div class="stat">
                    <span>Iesniegts:</span>
                    <strong><?php echo date('d.m.Y H:i', strtotime($quiz_result['submitted_at'])); ?></strong>
                </div>
            </div>
        </div>
        
        <div class="answers-review">
            <h2>Detalizēts pārskats</h2>
            <?php foreach ($answers as $answer): ?>
                <div class="answer-review <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <h4><?php echo htmlspecialchars($answer['question_text']); ?></h4>
                    <div class="review-content">
                        <p>Tava atbilde: <strong><?php echo htmlspecialchars($answer['answer_text']); ?></strong></p>
                        <?php if (!$answer['is_correct']): ?>
                            <p>Pareizā atbilde: <strong><?php echo htmlspecialchars($answer['correct_answer']); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <span class="review-badge"><?php echo $answer['is_correct'] ? '✓ Pareizi' : '✗ Nepareizi'; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="action-buttons">
            <a href="class_details.php?id=<?php echo $quiz_result['class_id']; ?>" class="btn btn-primary">Atpakaļ uz klasi</a>
        </div>
    </div>
</body>
</html>
