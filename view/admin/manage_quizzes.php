<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    header('Location: ../login-reg.php');
    exit();
}

$class_id = (int)($_GET['class_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = AuthController::is_admin();

if ($class_id <= 0) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

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

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($action === 'create_quiz') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $time_limit = (int)($_POST['time_limit'] ?? 0);
        $passing_score = (int)($_POST['passing_score'] ?? 60);
        $show_leaderboard = isset($_POST['show_leaderboard']) ? 1 : 1;

        if ($title === '') {
            $error_message = 'Testa nosaukums ir obligāts.';
        } elseif ($passing_score < 0 || $passing_score > 100) {
            $error_message = 'Nokārtošanas slieksnis jābūt no 0 līdz 100.';
        } else {
            $result = QuizController::create_quiz($class_id, $title, $description, $user_id, $time_limit, $passing_score, $show_leaderboard);
            if (!empty($result['success'])) {
                header('Location: edit_quiz.php?id=' . $result['quiz_id']);
                exit();
            }
            $error_message = $result['message'] ?? 'Kļūda veidojot testu.';
        }
    }

    if ($action === 'delete_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        if ($quiz_id <= 0) {
            $error_message = 'Nederīgs testa ID.';
        } else {
            $delete_query = $is_admin
                ? "DELETE FROM quizzes WHERE id = ? AND class_id = ?"
                : "DELETE FROM quizzes WHERE id = ? AND class_id = ? AND created_by = ?";
            $delete_stmt = $conn->prepare($delete_query);
            if ($is_admin) {
                $delete_stmt->bind_param('ii', $quiz_id, $class_id);
            } else {
                $delete_stmt->bind_param('iii', $quiz_id, $class_id, $user_id);
            }
            $delete_stmt->execute();

            if ($delete_stmt->affected_rows > 0) {
                header('Location: manage_quizzes.php?class_id=' . $class_id);
                exit();
            }
            $error_message = 'Neizdevās dzēst testu.';
        }
    }
}

$quizzes = QuizController::get_class_quizzes($class_id);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testi - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Testi - <?php echo htmlspecialchars($class['name']); ?></div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header" style="margin-bottom: 1.5rem;">
            <h1 class="page-title">Testi</h1>
            <p class="page-subtitle">Pārvaldi testus klasei <?php echo htmlspecialchars($class['name']); ?>.</p>
        </div>
        <?php if ($error_message): ?>
            <div class="card" style="margin-bottom: 1.5rem; border-color: rgba(239, 68, 68, 0.4);">
                <p class="text-muted" style="margin: 0; color: #dc2626;"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <div class="flex gap-2" style="margin-bottom: 2rem;">
            <button class="btn" onclick="openModal('createQuizModal')">+ Izveidot testu</button>
        </div>
        
        <div class="grid grid-2">
            <?php if (empty($quizzes)): ?>
                <div class="card" style="grid-column: 1 / -1;">
                    <p class="text-muted" style="margin: 0;">Šai klasei vēl nav izveidoti testi.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($quizzes as $quiz): ?>
                <div class="card quiz-card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <span class="badge"><?php echo (int)($quiz['question_count'] ?? 0); ?> jaut.</span>
                    </div>
                    <p class="text-muted"><?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 120)); ?></p>
                    <div class="quiz-meta" style="margin-top: 0.75rem;">
                        <span>⏱️ <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'Nav limita'; ?></span>
                        <span>👥 <?php echo (int)($quiz['attempts'] ?? 0); ?> mēģ.</span>
                    </div>
                    <div class="card-actions" style="margin-top: 1rem;">
                        <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-secondary">Rediģēt</a>
                        <form method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>" style="display: inline-flex;" onsubmit="return confirm('Dzēst šo testu? Šo darbību nevar atsaukt.');">
                            <input type="hidden" name="action" value="delete_quiz">
                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-small">Dzēst</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Create Quiz Modal -->
    <div id="createQuizModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">Izveidot jaunu testu</h2>
                <button type="button" class="modal-close" onclick="closeModal('createQuizModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createQuizForm" method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="action" value="create_quiz">
                    
                    <div class="form-group">
                        <label class="form-label" for="quiz_title">Testa nosaukums</label>
                        <input class="form-input" type="text" id="quiz_title" name="title" required placeholder="Testa nosaukums">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="quiz_description">Apraksts</label>
                        <textarea class="form-textarea" id="quiz_description" name="description" rows="3" placeholder="Testa apraksts"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="time_limit">Laika limits (min)</label>
                            <input class="form-input" type="number" id="time_limit" name="time_limit" value="0" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="passing_score">Nokārtošanas slieksnis (%)</label>
                            <input class="form-input" type="number" id="passing_score" name="passing_score" value="60" min="0" max="100">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createQuizModal')">Atcelt</button>
                <button type="submit" form="createQuizForm" class="btn">Izveidot testu</button>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        
        document.addEventListener('DOMContentLoaded', function() {
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.classList.remove('active');
                }
            };
        });
    </script>
</body>
</html>
