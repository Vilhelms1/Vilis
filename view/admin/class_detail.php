<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    header('Location: ../login-reg.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$class_id = $_GET['id'] ?? 0;

// Verify admin owns class
$class_query = "SELECT * FROM classes WHERE id = ? AND admin_id = ?";
$class_stmt = $conn->prepare($class_query);
$class_stmt->bind_param("ii", $class_id, $admin_id);
$class_stmt->execute();
$class = $class_stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: admin_dashboard.php');
    exit();
}

// Get quizzes
$quizzes = QuizController::get_class_quizzes($class_id);

// Get class students
$students = ClassController::get_class_students($class_id);

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
            <a href="admin_dashboard.php" class="nav-back">← Back</a>
            <div class="nav-brand"><?php echo htmlspecialchars($class['name']); ?></div>
            <a href="../process_logout.php" class="btn btn-small">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="tabs-container">
            <button class="tab-btn active" onclick="switchTab('quizzes')"> Quizzes</button>
            <button class="tab-btn" onclick="switchTab('materials')"> Materials</button>
            <button class="tab-btn" onclick="switchTab('students')"> Students</button>
        </div>
        
        <!-- Quizzes Tab -->
        <div id="quizzes" class="tab-content active">
            <div class="dashboard-header">
                <h2>Class Quizzes</h2>
                <a href="manage_quizzes.php?class_id=<?php echo $class_id; ?>" class="btn btn-primary">Manage Quizzes</a>
            </div>
            <div class="dashboard-grid">
                <?php foreach ($quizzes as $quiz):
                ?>
                    <div class="card quiz-card">
                        <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 100)); ?></p>
                        
                        <div class="quiz-meta">
                            <span> <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'No limit'; ?></span>
                            <span> <?php echo (int)($quiz['question_count'] ?? 0); ?> questions</span>
                        </div>
                        
                        <div class="text-muted">Attempts: <?php echo $quiz['attempts']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Materials Tab -->
        <div id="materials" class="tab-content">
            <div class="dashboard-header">
                <h2>Study Materials</h2>
                <a href="manage_materials.php?id=<?php echo $class_id; ?>" class="btn btn-primary">Manage Materials</a>
            </div>
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
        <div id="students" class="tab-content">
            <h2>Enrolled Students</h2>
            <?php if (empty($students)): ?>
                <p>No students enrolled yet.</p>
            <?php else: ?>
                <div class="card">
                    <ul>
                        <?php foreach ($students as $student): ?>
                            <li>
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                (<?php echo htmlspecialchars($student['email']); ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
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
