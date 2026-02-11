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

$news_items = [];
$news_query = "SELECT title, body, image_path, published_at FROM school_news WHERE is_active = 1 ORDER BY published_at DESC LIMIT 6";
$news_stmt = $conn->prepare($news_query);
if ($news_stmt) {
    $news_stmt->execute();
    $news_items = $news_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manas klases - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">🎓 ApgūstiVairāk</div>
            <div class="nav-actions">
                <span>Sveiki, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>Manas klases</h1>
        </div>
        
        <!-- Progress Summary -->
        <div class="progress-summary">
            <div class="stat-card">
                <h3><?php echo $progress['total_quizzes'] ?? 0; ?></h3>
                <p>Kopā testi</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $progress['passed_quizzes'] ?? 0; ?></h3>
                <p>Nokārtoti testi</p>
            </div>
            <div class="stat-card">
                <h3><?php echo round($progress['avg_score'] ?? 0, 1); ?>%</h3>
                <p>Vidējais vērtējums</p>
            </div>
        </div>
        
        <!-- News -->
        <div class="dashboard-header" style="margin-top: 2rem;">
            <h2>Jaunumi</h2>
        </div>
        <div class="dashboard-grid" style="margin-bottom: 2rem;">
            <?php if (empty($news_items)): ?>
                <div class="empty-state">
                    <p>Jaunumu pagaidām nav.</p>
                </div>
            <?php else: ?>
                <?php foreach ($news_items as $news): ?>
                    <?php $image_path = !empty($news['image_path']) ? BASE_URL . 'uploads/' . $news['image_path'] : BASE_URL . 'assets/image/picture.jpg'; ?>
                    <div class="news-card" style="background-image: url('<?php echo htmlspecialchars($image_path); ?>');">
                        <div class="news-overlay">
                            <h4><?php echo htmlspecialchars($news['title']); ?></h4>
                            <p><?php echo htmlspecialchars(substr($news['body'] ?? '', 0, 120)); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Classes Grid -->
        <div class="dashboard-grid">
            <?php if (empty($classes)): ?>
                <div class="empty-state">
                    <p>Tu vēl neesi pievienots nevienai klasei.</p>
                    <p>Sazinies ar adminu, lai pievienotos klasei.</p>
                </div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                    <div class="card class-card" style="display: grid; gap: 0.35rem;">
                        <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                        <p><?php echo htmlspecialchars($class['description']); ?></p>
                        <div class="card-actions">
                            <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-primary">Skatīt klasi</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
