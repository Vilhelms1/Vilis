<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$class_id = $_GET['id'] ?? 0;

// Verify student is enrolled
$enroll_check = "SELECT * FROM class_enrollments WHERE class_id = ? AND user_id = ?";
$enroll_stmt = $conn->prepare($enroll_check);
$enroll_stmt->bind_param("ii", $class_id, $user_id);
$enroll_stmt->execute();
if ($enroll_stmt->get_result()->num_rows === 0) {
    header('Location: student_dashboard.php');
    exit();
}

// Get class info
$class_query = "SELECT * FROM classes WHERE id = ?";
$class_stmt = $conn->prepare($class_query);
$class_stmt->bind_param("i", $class_id);
$class_stmt->execute();
$class = $class_stmt->get_result()->fetch_assoc();

// Get quizzes
$quizzes = QuizController::get_class_quizzes($class_id);

// Get student's quiz results for this class
$results_query = "SELECT q.id, q.title, MAX(qr.percentage) as best_score, COUNT(qr.id) as attempts FROM quizzes q LEFT JOIN quiz_results qr ON q.id = qr.quiz_id AND qr.user_id = ? WHERE q.class_id = ? GROUP BY q.id";
$results_stmt = $conn->prepare($results_query);
$results_stmt->bind_param("ii", $user_id, $class_id);
$results_stmt->execute();
$student_results = [];
foreach ($results_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $result) {
    $student_results[$result['id']] = $result;
}

// Get class materials
$materials_query = "SELECT * FROM class_materials WHERE class_id = ? ORDER BY created_at DESC";
$materials_stmt = $conn->prepare($materials_query);
$materials_stmt->bind_param("i", $class_id);
$materials_stmt->execute();
$materials = $materials_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['name']); ?> - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="student_dashboard.php" class="nav-back">← Back</a>
            <div class="nav-brand"><?php echo htmlspecialchars($class['name']); ?></div>
            <a href="../process_logout.php" class="btn btn-small">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="tabs-container">
            <button class="tab-btn active" onclick="switchTab('quizzes')">📝 Quizzes</button>
            <button class="tab-btn" onclick="switchTab('materials')">📚 Materials</button>
            <button class="tab-btn" onclick="switchTab('progress')">📊 Progress</button>
        </div>
        
        <!-- Quizzes Tab -->
        <div id="quizzes" class="tab-content active">
            <h2>Available Quizzes</h2>
            <div class="dashboard-grid">
                <?php foreach ($quizzes as $quiz):
                    $result = $student_results[$quiz['id']] ?? null;
                ?>
                    <div class="card quiz-card">
                        <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 100)); ?></p>
                        
                        <div class="quiz-meta">
                            <span>⏱️ <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'No limit'; ?></span>
                            <span>❓ <?php echo (int)($quiz['question_count'] ?? 0); ?> questions</span>
                        </div>
                        
                        <?php if ($result): ?>
                            <div class="quiz-result">
                                <div class="score-badge <?php echo $result['best_score'] >= $quiz['passing_score'] ? 'passed' : 'failed'; ?>">
                                    <?php echo round($result['best_score'], 1); ?>%
                                </div>
                                <small><?php echo $result['attempts']; ?> attempts</small>
                            </div>
                        <?php endif; ?>
                        
                        <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-primary">
                            <?php echo $result ? 'Retake Quiz' : 'Start Quiz'; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Materials Tab -->
        <div id="materials" class="tab-content">
            <h2>Study Materials</h2>
            <?php if (empty($materials)): ?>
                <p>No materials uploaded yet.</p>
            <?php else: ?>
                <div class="materials-list">
                    <?php foreach ($materials as $material): ?>
                        <div class="material-item">
                            <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                            <a href="../../uploads/<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-secondary" download>Download</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Progress Tab -->
        <div id="progress" class="tab-content">
            <h2>Your Progress</h2>
            <p>View detailed progress in the leaderboard section.</p>
            <a href="../progress_leaderboard.php?class_id=<?php echo $class_id; ?>" class="btn btn-secondary">Open Progress & Leaderboard</a>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
