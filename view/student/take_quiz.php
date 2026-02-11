<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = $_GET['quiz_id'] ?? 0;

// Get quiz
$quiz = QuizController::get_quiz($quiz_id);

if (!$quiz) {
    header('Location: student_dashboard.php');
    exit();
}

// Verify student is enrolled in the class
$enroll_check = "SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?";
$enroll_stmt = $conn->prepare($enroll_check);
$enroll_stmt->bind_param("ii", $quiz['class_id'], $user_id);
$enroll_stmt->execute();
if ($enroll_stmt->get_result()->num_rows === 0) {
    header('Location: student_dashboard.php');
    exit();
}

// Shuffle questions
$questions = $quiz['questions'];
shuffle($questions);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body class="quiz-page" data-theme="light" data-lang="lv">
    <div class="quiz-header">
        <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
        <div class="quiz-timer" id="timer">
            <?php if ($quiz['time_limit'] > 0): ?>
                Time: <span id="minutes"><?php echo $quiz['time_limit']; ?></span>:<span id="seconds">00</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container quiz-container">
        <form id="quizForm" method="POST" action="submit_quiz.php">
            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
            <input type="hidden" name="time_taken" id="timeTaken">

            <div class="questions-container">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-wrapper">
                        <h3><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></h3>
                        <p class="question-meta"><?php echo $question['points']; ?> points</p>

                        <div class="answers-group">
                            <?php foreach ($question['answers'] as $answer): ?>
                                <label class="answer-option">
                                    <input type="radio" name="answer_<?php echo $question['id']; ?>" value="<?php echo $answer['id']; ?>" required>
                                    <span><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="quiz-navigation">
                <button type="submit" class="btn btn-primary">Submit Quiz</button>
            </div>
        </form>
    </div>
    
    <script>
        const startTime = Date.now();

        // Timer
        <?php if ($quiz['time_limit'] > 0): ?>
        let timeLeft = <?php echo $quiz['time_limit'] * 60; ?>;
        setInterval(() => {
            if (timeLeft <= 0) {
                document.getElementById('quizForm').submit();
                return;
            }
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('minutes').textContent = minutes;
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        }, 1000);
        <?php endif; ?>

        document.getElementById('quizForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Calculate time taken
            const timeTaken = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById('timeTaken').value = timeTaken;

            // Submit form
            this.submit();
        });
    </script>
</body>
</html>
