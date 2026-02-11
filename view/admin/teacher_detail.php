<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    header('Location: ../login-reg.php');
    exit();
}

$teacher_id = (int)($_GET['id'] ?? 0);
if ($teacher_id <= 0) {
    header('Location: admin_dashboard.php');
    exit();
}

$teacher_query = "SELECT id, first_name, last_name, email FROM users WHERE id = ? AND role = 'teacher'";
$teacher_stmt = $conn->prepare($teacher_query);
$teacher_stmt->bind_param('i', $teacher_id);
$teacher_stmt->execute();
$teacher = $teacher_stmt->get_result()->fetch_assoc();

if (!$teacher) {
    header('Location: admin_dashboard.php');
    exit();
}

$classes_query = "SELECT c.*, (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = c.id) as student_count FROM classes c WHERE c.teacher_id = ? ORDER BY c.created_at DESC";
$classes_stmt = $conn->prepare($classes_query);
$classes_stmt->bind_param('i', $teacher_id);
$classes_stmt->execute();
$classes = $classes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skolotaja klases - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="admin_dashboard.php" class="nav-back">← Back</a>
            <div class="nav-brand">Skolotajs: <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1>Klases</h1>
            <span class="badge"><?php echo count($classes); ?> klases</span>
        </div>

        <div class="dashboard-grid">
            <?php foreach ($classes as $class): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                    <p><?php echo htmlspecialchars($class['description'] ?? ''); ?></p>
                    <span class="badge"><?php echo $class['student_count']; ?> studenti</span>
                    <div class="card-actions">
                        <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-secondary">Skatīt klasi</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
