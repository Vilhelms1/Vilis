<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    header('Location: ../login-reg.php');
    exit();
}

$class_id = (int)($_GET['class_id'] ?? 0);
$quiz_id = (int)($_GET['quiz_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = AuthController::is_admin();

if ($class_id <= 0 || $quiz_id <= 0) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

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

$quiz_query = "SELECT id, title FROM quizzes WHERE id = ? AND class_id = ?";
$quiz_stmt = $conn->prepare($quiz_query);
$quiz_stmt->bind_param("ii", $quiz_id, $class_id);
$quiz_stmt->execute();
$quiz = $quiz_stmt->get_result()->fetch_assoc();

if (!$quiz) {
    header('Location: manage_quizzes.php?class_id=' . $class_id);
    exit();
}

$summary_query = "SELECT u.id, u.first_name, u.last_name, COUNT(qr.id) as attempts, MAX(qr.percentage) as best_score, AVG(qr.percentage) as avg_score
                  FROM quiz_results qr
                  INNER JOIN users u ON u.id = qr.user_id
                  WHERE qr.quiz_id = ?
                  GROUP BY u.id, u.first_name, u.last_name
                  ORDER BY u.first_name, u.last_name";
$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("i", $quiz_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$attempts_query = "SELECT u.id as user_id, u.first_name, u.last_name, qr.percentage, qr.submitted_at
                   FROM quiz_results qr
                   INNER JOIN users u ON u.id = qr.user_id
                   WHERE qr.quiz_id = ?
                   ORDER BY u.first_name, u.last_name, qr.submitted_at ASC";
$attempts_stmt = $conn->prepare($attempts_query);
$attempts_stmt->bind_param("i", $quiz_id);
$attempts_stmt->execute();
$attempts = $attempts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$attempt_counts = [];
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testa mēģinājumi - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="manage_quizzes.php?class_id=<?php echo $class_id; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Mēģinājumi</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header" style="margin-bottom: 1.5rem;">
            <h1 class="page-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <p class="page-subtitle">Kurš pildija testu un kādi bija rezultāti.</p>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">Kopsavilkums</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="table table-violet">
                    <thead>
                        <tr>
                            <th>Skolēns</th>
                            <th>Mēģinājumi</th>
                            <th>Labākais</th>
                            <th>Vidējais</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($summary)): ?>
                            <tr>
                                <td colspan="4" class="text-muted">Nav mēģinājumu.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($summary as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])); ?></td>
                                    <td><?php echo (int)$row['attempts']; ?></td>
                                    <td><?php echo round((float)$row['best_score'], 1); ?>%</td>
                                    <td><?php echo round((float)$row['avg_score'], 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Visi mēģinājumi</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="table table-violet">
                    <thead>
                        <tr>
                            <th>Skolēns</th>
                            <th>Mēģinājums</th>
                            <th>Rezultāts</th>
                            <th>Datums</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attempts)): ?>
                            <tr>
                                <td colspan="4" class="text-muted">Nav mēģinājumu.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attempts as $row): ?>
                                <?php
                                $uid = (int)$row['user_id'];
                                if (!isset($attempt_counts[$uid])) {
                                    $attempt_counts[$uid] = 0;
                                }
                                $attempt_counts[$uid] += 1;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])); ?></td>
                                    <td>#<?php echo $attempt_counts[$uid]; ?></td>
                                    <td><?php echo round((float)$row['percentage'], 1); ?>%</td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($row['submitted_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
