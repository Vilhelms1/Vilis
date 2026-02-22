<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = AuthController::is_admin();
$class_id = $_GET['id'] ?? 0;

// Verify teacher owns class or admin access
$class_query = $is_admin
    ? "SELECT * FROM classes WHERE id = ?"
    : "SELECT * FROM classes WHERE id = ? AND teacher_id = ?";
$class_stmt = $conn->prepare($class_query);
if ($is_admin) {
    $class_stmt->bind_param("i", $class_id);
} else {
    $class_stmt->bind_param("ii", $class_id, $user_id);
}
$class_stmt->execute();
$class = $class_stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

// Get quizzes
$quizzes = QuizController::get_class_quizzes($class_id, true);

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
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['name']); ?> - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'; ?>" class="nav-back">← Back</a>
            <div class="nav-brand"><?php echo htmlspecialchars($class['name']); ?></div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header" style="margin-bottom: 1.5rem;">
            <h1 class="page-title"><?php echo htmlspecialchars($class['name']); ?></h1>
            <p class="page-subtitle">
                <?php echo htmlspecialchars($class['description'] ?: 'Pārvaldi šīs klases testus, materiālus un studentus.'); ?>
            </p>
        </div>

        <div class="tabs">
            <button class="tab-btn active" data-tab="quizzes" onclick="switchTab('quizzes', this)">Testi</button>
            <button class="tab-btn" data-tab="materials" onclick="switchTab('materials', this)">Materiāli</button>
            <button class="tab-btn" data-tab="students" onclick="switchTab('students', this)">Studenti</button>
        </div>
        
        <!-- Quizzes Tab -->
        <div id="quizzes" class="tab-content active">
            <div class="dashboard-header">
                <h2>Testi</h2>
                <a href="manage_quizzes.php?class_id=<?php echo $class_id; ?>" class="btn btn-primary">Pārvaldīt testus</a>
            </div>
            <?php if (empty($quizzes)): ?>
                <div class="empty-state">Šai klasei vēl nav izveidoti testi.</div>
            <?php else: ?>
                <div class="dashboard-grid">
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="card quiz-card">
                            <div class="card-header">
                                <h3 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                <span class="badge"><?php echo (int)($quiz['question_count'] ?? 0); ?> jaut.</span>
                            </div>
                            <p class="text-muted"><?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 110)); ?></p>
                            
                            <div class="quiz-meta" style="margin-top: 0.5rem;">
                                <span>⏱️ <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'Nav limita'; ?></span>
                                <span>👥 <?php echo (int)($quiz['attempts'] ?? 0); ?> mēģ.</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Materials Tab -->
        <div id="materials" class="tab-content">
            <div class="dashboard-header">
                <h2>Materiāli</h2>
                <a href="manage_materials.php?id=<?php echo $class_id; ?>" class="btn btn-primary">Pārvaldīt materiālus</a>
            </div>
            <?php if (empty($materials)): ?>
                <div class="empty-state">Materiāli vēl nav pievienoti.</div>
            <?php else: ?>
                <div class="materials-list">
                    <?php foreach ($materials as $material): ?>
                        <div class="material-item">
                            <div class="material-header">
                                <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                                <span class="text-muted"><?php echo date('d.m.Y', strtotime($material['created_at'])); ?></span>
                            </div>
                            <div class="material-actions">
                                <a href="../../uploads/<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-secondary" download>Lejupielādēt</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Progress Tab -->
        <div id="students" class="tab-content">
            <div class="dashboard-header">
                <h2>Studenti</h2>
                <a href="manage_class.php?id=<?php echo $class_id; ?>" class="btn btn-secondary">+ Pievienot studentu</a>
            </div>
            <?php if (empty($students)): ?>
                <div class="empty-state">Šai klasei vēl nav pievienoti studenti.</div>
            <?php else: ?>
                <div class="card">
                    <ul class="students-list">
                        <?php foreach ($students as $student): ?>
                            <li>
                                <div>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    <div class="text-muted" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($student['email']); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Uses global switchTab from app.js
    </script>
</body>
</html>
