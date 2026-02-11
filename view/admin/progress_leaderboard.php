<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? 0;

// Get class info and verify enrollment
$class_query = "SELECT c.* FROM classes c INNER JOIN class_enrollments ce ON c.id = ce.class_id WHERE c.id = ? AND ce.student_id = ?";
$class_stmt = $conn->prepare($class_query);
$class_stmt->bind_param("ii", $class_id, $user_id);
$class_stmt->execute();
$class = $class_stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: student_dashboard.php');
    exit();
}

// Get student's detailed progress
$progress_query = "SELECT q.id, q.title, COUNT(qr.id) as attempts, MAX(qr.percentage) as best_score, AVG(qr.percentage) as avg_score, MAX(qr.submitted_at) as last_attempt FROM quizzes q LEFT JOIN quiz_results qr ON q.id = qr.quiz_id AND qr.user_id = ? WHERE q.class_id = ? GROUP BY q.id";
$progress_stmt = $conn->prepare($progress_query);
$progress_stmt->bind_param("ii", $user_id, $class_id);
$progress_stmt->execute();
$quiz_progress = $progress_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get leaderboard - top students in this class
$leaderboard_query = "SELECT u.id, u.first_name, u.last_name, COUNT(qr.id) as total_attempts, ROUND(AVG(qr.percentage), 2) as avg_score, SUM(CASE WHEN qr.passed = 1 THEN 1 ELSE 0 END) as passed_count FROM users u INNER JOIN class_enrollments ce ON u.id = ce.student_id LEFT JOIN quiz_results qr ON u.id = qr.user_id AND qr.quiz_id IN (SELECT id FROM quizzes WHERE class_id = ? AND show_leaderboard = 1) WHERE ce.class_id = ? GROUP BY u.id ORDER BY avg_score DESC LIMIT 10";
$leaderboard_stmt = $conn->prepare($leaderboard_query);
$leaderboard_stmt->bind_param("ii", $class_id, $class_id);
$leaderboard_stmt->execute();
$leaderboard = $leaderboard_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get improvement data (last 5 attempts)
$improvement_query = "SELECT qr.percentage, qr.submitted_at FROM quiz_results qr INNER JOIN quizzes q ON qr.quiz_id = q.id WHERE qr.user_id = ? AND q.class_id = ? ORDER BY qr.submitted_at DESC LIMIT 10";
$improvement_stmt = $conn->prepare($improvement_query);
$improvement_stmt->bind_param("ii", $user_id, $class_id);
$improvement_stmt->execute();
$improvements = array_reverse($improvement_stmt->get_result()->fetch_all(MYSQLI_ASSOC));
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress & Leaderboard - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="../student/class_details.php?id=<?php echo $class_id; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Progress & Leaderboard</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="tabs-container">
            <button class="tab-btn active" data-tab="progress" onclick="switchTab('progress', this)">📊 My Progress</button>
            <button class="tab-btn" data-tab="leaderboard" onclick="switchTab('leaderboard', this)">🏆 Leaderboard</button>
        </div>
        
        <!-- Progress Tab -->
        <div id="progress" class="tab-content active">
            <h2>Your Progress in <?php echo htmlspecialchars($class['name']); ?></h2>
            
            <!-- Improvement Chart -->
            <div class="card" style="margin-bottom: 30px;">
                <h3>Statistika</h3>
                <canvas id="improvementChart"></canvas>
            </div>
            
            <!-- Quiz-by-Quiz Progress -->
            <h3>Quiz Progress Details</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Quiz Name</th>
                            <th>Attempts</th>
                            <th>Best Score</th>
                            <th>Average Score</th>
                            <th>Last Attempt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quiz_progress as $quiz): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                <td><?php echo $quiz['attempts'] ?? 0; ?></td>
                                <td>
                                    <span class="badge <?php echo ($quiz['best_score'] ?? 0) >= 60 ? 'success' : ''; ?>">
                                        <?php echo round($quiz['best_score'] ?? 0, 1); ?>%
                                    </span>
                                </td>
                                <td><?php echo round($quiz['avg_score'] ?? 0, 1); ?>%</td>
                                <td>
                                    <?php 
                                    if ($quiz['last_attempt']) {
                                        echo date('M d, Y', strtotime($quiz['last_attempt']));
                                    } else {
                                        echo 'Not attempted';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Leaderboard Tab -->
        <div id="leaderboard" class="tab-content">
            <h2>Class Leaderboard</h2>
            <div class="leaderboard-container">
                <?php foreach ($leaderboard as $index => $student): ?>
                    <div class="leaderboard-item rank-<?php echo $index + 1; ?>">
                        <div class="leaderboard-rank">
                            <?php if ($index === 0): ?>
                                <span class="rank-badge gold">🥇</span>
                            <?php elseif ($index === 1): ?>
                                <span class="rank-badge silver">🥈</span>
                            <?php elseif ($index === 2): ?>
                                <span class="rank-badge bronze">🥉</span>
                            <?php else: ?>
                                <span class="rank-number">#<?php echo $index + 1; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="leaderboard-info">
                            <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                            <div class="leaderboard-stats">
                                <span>Avg Score: <strong><?php echo round($student['avg_score'] ?? 0, 1); ?>%</strong></span>
                                <span>Passed: <strong><?php echo $student['passed_count'] ?? 0; ?></strong></span>
                                <span>Attempts: <strong><?php echo $student['total_attempts'] ?? 0; ?></strong></span>
                            </div>
                        </div>
                        
                        <div class="leaderboard-score">
                            <div class="score-circle" style="--percentage: <?php echo $student['avg_score'] ?? 0; ?>">
                                <span><?php echo round($student['avg_score'] ?? 0, 0); ?>%</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
            
            // Redraw chart if on progress tab
            if (tabName === 'progress' && window.improvementChart) {
                setTimeout(() => window.improvementChart.resize(), 100);
            }
        }
        
        // Improvement Chart
        const improvementCtx = document.getElementById('improvementChart')?.getContext('2d');
        if (improvementCtx) {
            const improvements = <?php echo json_encode($improvements); ?>;
            window.improvementChart = new Chart(improvementCtx, {
                type: 'line',
                data: {
                    labels: improvements.map(i => new Date(i.submitted_at).toLocaleDateString()),
                    datasets: [{
                        label: 'Score (%)',
                        data: improvements.map(i => i.percentage),
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, 0.12)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: '#0f766e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                suffix: '%'
                            }
                        }
                    }
                }
            });
        }
    </script>
    
</body>
</html>
