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
$enroll_check = "SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?";
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

$assignments_query = "SELECT a.*, s.id as submission_id, s.grade, s.submitted_at FROM class_assignments a LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ? WHERE a.class_id = ? ORDER BY a.created_at DESC";
$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->bind_param("ii", $user_id, $class_id);
$assignments_stmt->execute();
$assignments = $assignments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get leaderboard - student rankings
$leaderboard_query = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        COUNT(DISTINCT qr.id) as quiz_attempts,
        AVG(qr.percentage) as avg_score,
        SUM(CASE WHEN qr.passed = 1 THEN 1 ELSE 0 END) as quizzes_passed,
        SUM(s.grade) as total_grade,
        COUNT(DISTINCT s.id) as assignments_submitted
    FROM users u
    JOIN class_enrollments ce ON u.id = ce.student_id
    LEFT JOIN quiz_results qr ON u.id = qr.user_id AND qr.quiz_id IN (
        SELECT id FROM quizzes WHERE class_id = ?
    )
    LEFT JOIN assignment_submissions s ON u.id = s.student_id AND s.assignment_id IN (
        SELECT id FROM class_assignments WHERE class_id = ?
    )
    WHERE ce.class_id = ? AND u.role = 'student'
    GROUP BY u.id, u.first_name, u.last_name
    ORDER BY AVG(qr.percentage) DESC
";
$leaderboard_stmt = $conn->prepare($leaderboard_query);
$leaderboard_stmt->bind_param("iii", $class_id, $class_id, $class_id);
$leaderboard_stmt->execute();
$leaderboard = $leaderboard_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-menu">
                <a href="student_dashboard.php" class="nav-link" style="font-weight: 600;">← Atpakaļ</a>
            </div>
            <div class="nav-brand"><?php echo htmlspecialchars($class['name']); ?></div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Iziet</a>
            </div>
        </div>
    </nav>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in" style="margin-bottom: 2rem;">
            <h1 class="page-title"><?php echo htmlspecialchars($class['name']); ?></h1>
            <p class="page-subtitle">Apgūsti prasmes, seko līdzi progresam un salīdzini ar citiem </p>
        </div>

        <!-- Tabs -->
          <div class="tabs" style="margin-bottom: 2rem;">
              <button class="tab-btn active" data-tab="quizzes" onclick="switchTab('quizzes', this)">📝 Testi</button>
              <button class="tab-btn" data-tab="materials" onclick="switchTab('materials', this)">📚 Materiāli</button>
              <button class="tab-btn" data-tab="assignments" onclick="switchTab('assignments', this)">✏️ Darbi</button>
              <button class="tab-btn" data-tab="leaderboard" onclick="switchTab('leaderboard', this)">🏆 Līderu saraksti</button>
              <a class="tab-btn" href="../admin/progress_leaderboard.php?class_id=<?php echo $class_id; ?>">📊 Statistika</a>
          </div>

        <!-- Testi Tab -->
        <div id="quizzes" class="tab-content active">
            <div class="section-title">Pieejamie testi</div>
            <?php if (empty($quizzes)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">📝</div>
                    <p style="color: var(--text-secondary); margin: 0;">Šajā klasē vēl nav testu</p>
                </div>
            <?php else: ?>
                <div class="grid grid-2">
                    <?php foreach ($quizzes as $quiz):
                        $result = $student_results[$quiz['id']] ?? null;
                    ?>
                        <div class="card slide-up">
                            <div class="card-header">
                                <h3 class="card-title" style="font-size: 1.1rem;">📝 <?php echo htmlspecialchars($quiz['title']); ?></h3>
                            </div>
                            <div class="card-body">
                                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                    <?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 100)); ?>
                                </p>
                                
                                <div class="flex gap-2" style="margin-bottom: 1rem; flex-wrap: wrap;">
                                    <span class="badge">⏱️ <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'Nav limita'; ?></span>
                                    <span class="badge">❓ <?php echo (int)($quiz['question_count'] ?? 0); ?> jautājumi</span>
                                </div>
                                
                                <?php if ($result && $result['best_score']): ?>
                                    <div style="padding: 1rem; border-radius: 0.5rem; background: rgba(16, 185, 129, 0.1); margin-bottom: 1rem;">
                                        <p style="margin: 0 0 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Tavs labākais rezultāts</p>
                                        <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #10b981;">
                                            <?php echo round($result['best_score'], 1); ?>%
                                        </p>
                                        <p style="margin: 0.5rem 0 0; color: var(--text-tertiary); font-size: 0.85rem;">
                                            <?php echo $result['attempts']; ?> mēģinājumi
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn" style="width: 100%;">
                                    <?php echo $result ? '↻ Atkārtot testu' : '▶️ Sākt testu'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Materiāli Tab -->
        <div id="materials" class="tab-content">
            <div class="section-title">📚 Mācību materiāli</div>
            <?php if (empty($materials)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">📂</div>
                    <p style="color: var(--text-secondary); margin: 0;">Materiāli vēl nav pievienoti</p>
                </div>
            <?php else: ?>
                <div class="grid gap-2">
                    <?php foreach ($materials as $material): ?>
                        <div class="card slide-up">
                            <div class="card-header">
                                <h3 class="card-title" style="font-size: 1.1rem;">📄 <?php echo htmlspecialchars($material['title']); ?></h3>
                            </div>
                            <div class="card-body">
                                <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;">
                                    📅 <?php echo date('d.m.Y', strtotime($material['created_at'])); ?>
                                </p>
                            </div>
                            <div class="card-footer">
                                <a href="../../uploads/<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-secondary" style="width: 100%;" download>
                                    ⬇️ Lejupielādēt
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Darbi Tab -->
        <div id="assignments" class="tab-content">
            <div class="section-title">✏️ Darbi iesniegšanai</div>
            <?php if (empty($assignments)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">✓</div>
                    <p style="color: var(--text-secondary); margin: 0;">Darbi vēl nav pievienoti</p>
                </div>
            <?php else: ?>
                <div class="grid grid-2">
                    <?php foreach ($assignments as $assignment): ?>
                        <div class="card slide-up">
                            <div class="card-header">
                                <h3 class="card-title" style="font-size: 1.1rem;">✏️ <?php echo htmlspecialchars($assignment['title']); ?></h3>
                                <?php if ($assignment['submission_id']): ?>
                                    <span class="badge badge-success">✓ Iesniegts</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">⏳ Gaida</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                    <?php echo htmlspecialchars($assignment['description'] ?? ''); ?>
                                </p>
                                <p style="color: var(--text-tertiary); font-size: 0.9rem; margin-bottom: 0.5rem;">
                                    📅 Termiņš: <?php echo $assignment['due_at'] ? date('d.m.Y H:i', strtotime($assignment['due_at'])) : 'Nav norādīts'; ?>
                                </p>
                                <?php if ($assignment['submission_id'] && $assignment['grade'] !== null): ?>
                                    <p style="color: #10b981; font-size: 0.9rem; margin: 0;">
                                        ⭐ Atzīme: <strong><?php echo (int)$assignment['grade']; ?></strong>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <a href="submit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn" style="width: 100%;">
                                    📤 Iesniegt darbu
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Leaderboard Tab -->
        <div id="leaderboard" class="tab-content">
            <div class="section-title">🏆 Līderu saraksti</div>
            
            <?php if (empty($leaderboard)): ?>
                <div class="card" style="text-align: center; padding: 3rem;">
                    <p style="color: var(--text-secondary); margin: 0;">Nav pieejamu datu</p>
                </div>
            <?php else: ?>
                <div class="card slide-up">
                    <div class="card-header">
                        <h3 class="card-title">🏆 Labākie skolēni</h3>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>👤 Nosaukums</th>
                                    <th>📊 Vidējais rezultāts</th>
                                    <th>✅ Sekmīgi testi</th>
                                    <th>📝 Testus mēģinājumi</th>
                                    <th>✏️ Darbi iesniegti</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; ?>
                                <?php foreach ($leaderboard as $student): ?>
                                    <tr style="<?php echo $student['id'] == $user_id ? 'background: rgba(99, 102, 241, 0.1); font-weight: 600;' : ''; ?>">
                                        <td>
                                            <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: <?php echo $rank == 1 ? '#fbbf24' : ($rank == 2 ? '#c8d6e5' : ($rank == 3 ? '#cd7672' : 'rgba(99, 102, 241, 0.1)')); ?>; color: <?php echo $rank <= 3 ? 'white' : 'var(--text-primary)'; ?>; font-weight: 700;">
                                                <?php echo $rank; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                <?php if ($student['id'] == $user_id): ?>
                                                    <span class="badge">Tu</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; color: <?php echo $student['avg_score'] >= 75 ? '#10b981' : ($student['avg_score'] >= 50 ? '#f59e0b' : '#ef4444'); ?>;">
                                                <?php echo $student['avg_score'] ? round($student['avg_score'], 1) . '%' : '—'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo (int)($student['quizzes_passed'] ?? 0); ?></td>
                                        <td><?php echo (int)($student['quiz_attempts'] ?? 0); ?></td>
                                        <td><?php echo (int)($student['assignments_submitted'] ?? 0); ?></td>
                                    </tr>
                                    <?php $rank++; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(t => {
                t.classList.remove('active');
            });
            
            // Remove active from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
            });
            
            // Show selected tab content
            const targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Mark clicked button as active
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
    </script>
</body>
</html>
