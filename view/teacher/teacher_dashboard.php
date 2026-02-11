<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';

if (!AuthController::is_logged_in() || !AuthController::is_teacher()) {
    header('Location: ../login-reg.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? null;
    $payload = null;

    if ($action === null) {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (is_array($payload)) {
            $action = $payload['action'] ?? null;
        }
    }

    if ($action === 'create_class') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Class name is required']);
            exit();
        }

        $result = ClassController::create_class($name, $description, $teacher_id);
        echo json_encode($result);
        exit();
    }

    if ($action === 'delete_class') {
        $class_id = (int)($_POST['class_id'] ?? ($payload['class_id'] ?? 0));
        if ($class_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid class id']);
            exit();
        }

        $verify_query = "SELECT id FROM classes WHERE id = ? AND teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param('ii', $class_id, $teacher_id);
        $verify_stmt->execute();
        if ($verify_stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Klase nav atrasta vai nav tiesību dzēst.']);
            exit();
        }

        $delete_query = "DELETE FROM classes WHERE id = ? AND teacher_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('ii', $class_id, $teacher_id);
        $deleted = $delete_stmt->execute();

        if (!$deleted) {
            echo json_encode(['success' => false, 'message' => 'Neizdevās dzēst klasi.']);
            exit();
        }

        echo json_encode(['success' => $delete_stmt->affected_rows > 0, 'message' => $delete_stmt->affected_rows > 0 ? null : 'Klase nav atrasta.']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

$classes = ClassController::get_teacher_classes($teacher_id);

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
    <title>Skolotāja panelis - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">👨‍🏫 ApgūstiVairāk Skolotājs</div>
            <div class="nav-actions">
                <div class="nav-user">👤 <?php echo htmlspecialchars($_SESSION['first_name']); ?></div>
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Iziet</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in" style="margin-bottom: 2rem;">
            <h1 class="page-title">Manas mācību klases</h1>
            <p class="page-subtitle">Pārvaldi savus kursus, testus un materiālus</p>
        </div>

        <!-- Action Button -->
        <div class="flex gap-2" style="margin-bottom: 2rem;">
            <button class="btn" onclick="openModal('classModal')">
                ✏️ Izveidot jaunu klasi
            </button>
        </div>

        <!-- News -->
        <div class="page-header fade-in" style="margin: 2rem 0 1rem;">
            <h2 class="page-title" style="font-size: 1.5rem;">Jaunumi</h2>
        </div>
        <div class="grid gap-2" style="margin-bottom: 2rem;">
            <?php if (empty($news_items)): ?>
                <div class="card" style="padding: 1rem;">
                    <p style="margin: 0; color: var(--text-secondary);">Jaunumu pagaidām nav.</p>
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
        <div class="grid grid-2">
            <?php foreach ($classes as $class): ?>
                <div class="card slide-up">
                    <div class="card-header">
                        <h3 class="card-title" style="font-size: 1.175rem;"><?php echo htmlspecialchars($class['name']); ?></h3>
                        <span class="badge"><?php echo $class['student_count']; ?> 👥</span>
                    </div>
                    <div class="card-body">
                        <p style="color: var(--text-secondary); margin: 0;">
                            <?php echo htmlspecialchars($class['description'] ?? 'Nav apraksta'); ?>
                        </p>
                    </div>
                    <div class="card-footer class-actions" style="flex-wrap: wrap;">
                        <a href="../admin/class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-secondary" style="flex: 1; min-width: 100px;">Pārvaldīt</a>
                        <a href="../admin/manage_quizzes.php?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary" style="flex: 1; min-width: 100px;">Testi</a>
                    </div>
                    <div class="card-footer class-actions" style="flex-wrap: wrap;">
                        <a href="../admin/manage_materials.php?id=<?php echo $class['id']; ?>" class="btn btn-secondary" style="flex: 1; min-width: 100px;">Materiāli</a>
                        <a href="manage_assignments.php?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary" style="flex: 1; min-width: 100px;">Darbi</a>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-danger" onclick="deleteClass(<?php echo $class['id']; ?>)">❌ Dzēst</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Empty State -->
        <?php if (empty($classes)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📚</div>
                <h3 style="color: var(--text-primary); margin: 0 0 0.5rem;">Nav mācību klašu</h3>
                <p style="color: var(--text-secondary); margin: 0;">Sāc ar jaunu klases izveidi, lai pievienotos studējošajiem</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Class Modal -->
    <div id="classModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin: 0;">📚 Izveidot jaunu mācību klasi</h2>
            </div>
            <div class="modal-body">
                <form id="createClassForm">
                    <input type="hidden" name="action" value="create_class">
                    
                    <div class="form-group">
                        <label class="form-label">Klases nosaukums</label>
                        <input class="form-input" type="text" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Apraksts (pēc izvēles)</label>
                        <textarea class="form-textarea" name="description" placeholder="Apraksti, ko skolēni apgūs šajā klasē..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('classModal')">Atcelt</button>
                <button type="submit" form="createClassForm" class="btn">Izveidot klasi</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function deleteClass(classId) {
            if (confirm('Vai tiešām vēlies dzēst šo klasi?')) {
                fetch('teacher_dashboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_class', class_id: classId})
                }).then(r => r.json()).then(d => {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.message || 'Kļūda');
                    }
                });
            }
        }

        document.getElementById('createClassForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('teacher_dashboard.php', {method: 'POST', body: formData})
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.message || 'Kļūda');
                    }
                });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
