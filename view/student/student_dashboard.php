<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$classes = ClassController::get_student_classes($user_id);

// Get user progress
$progress_query = "SELECT COUNT(*) as total_quizzes, SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_quizzes, AVG(percentage) as avg_score FROM quiz_results WHERE user_id = ?";
$progress_stmt = $conn->prepare($progress_query);
$progress_stmt->bind_param("i", $user_id);
$progress_stmt->execute();
$progress = $progress_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">🎓 ApgūstiVairāk</div>
            <div class="nav-menu">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>My Classes</h1>
        </div>
        
        <!-- Progress Summary -->
        <div class="progress-summary">
            <div class="stat-card">
                <h3><?php echo $progress['total_quizzes'] ?? 0; ?></h3>
                <p>Total Quizzes</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $progress['passed_quizzes'] ?? 0; ?></h3>
                <p>Quizzes Passed</p>
            </div>
            <div class="stat-card">
                <h3><?php echo round($progress['avg_score'] ?? 0, 1); ?>%</h3>
                <p>Average Score</p>
            </div>
        </div>
        
        <!-- Classes Grid -->
        <div class="dashboard-grid">
            <?php if (empty($classes)): ?>
                <div class="empty-state">
                    <p>You haven't enrolled in any classes yet.</p>
                    <p>Contact your admin to join a class.</p>
                </div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                    <div class="card class-card">
                        <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                        <p><?php echo htmlspecialchars($class['description']); ?></p>
                        <div class="card-actions">
                            <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-primary">View Class</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
