<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';

$is_admin = AuthController::is_admin();
$is_teacher = AuthController::is_teacher();

if (!AuthController::is_logged_in() || (!$is_admin && !$is_teacher)) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$class_id = (int)($_GET['class_id'] ?? 0);

if ($class_id <= 0) {
    header('Location: ../teacher/teacher_dashboard.php');
    exit();
}

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

    if ($action === 'create_assignment') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_at = trim($_POST['due_at'] ?? '');
        $allow_submissions = isset($_POST['allow_submissions']) ? 1 : 0;

        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit();
        }

        $insert_query = "INSERT INTO class_assignments (class_id, title, description, due_at, allow_submissions, created_by) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param('isssii', $class_id, $title, $description, $due_at, $allow_submissions, $user_id);

        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit();
    }

    if ($action === 'delete_assignment') {
        $assignment_id = (int)($_POST['assignment_id'] ?? ($payload['assignment_id'] ?? 0));
        if ($assignment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid assignment id']);
            exit();
        }

        $delete_query = "DELETE FROM class_assignments WHERE id = ? AND class_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('ii', $assignment_id, $class_id);
        $delete_stmt->execute();

        echo json_encode(['success' => $delete_stmt->affected_rows > 0]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

$verify_query = $is_admin
    ? "SELECT * FROM classes WHERE id = ?"
    : "SELECT * FROM classes WHERE id = ? AND teacher_id = ?";
$verify_stmt = $conn->prepare($verify_query);
if ($is_admin) {
    $verify_stmt->bind_param('i', $class_id);
} else {
    $verify_stmt->bind_param('ii', $class_id, $user_id);
}
$verify_stmt->execute();
$class = $verify_stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: ../teacher/teacher_dashboard.php');
    exit();
}

$assignments_query = "SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) as submission_count FROM class_assignments a WHERE a.class_id = ? ORDER BY a.created_at DESC";
$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->bind_param('i', $class_id);
$assignments_stmt->execute();
$assignments = $assignments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Darbu pārvaldība - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="../teacher/teacher_dashboard.php" class="nav-back">← Back</a>
            <div class="nav-brand"><?php echo htmlspecialchars($class['name']); ?> - Darbi</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header" style="margin-bottom: 1.5rem;">
            <h1 class="page-title">Uzdevumi</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($class['name']); ?> · Izveido un pārvaldi klases uzdevumus</p>
        </div>
        <div class="flex gap-2" style="margin-bottom: 2rem;">
            <button class="btn" onclick="openModal('assignmentModal')">+ Jauns uzdevums</button>
        </div>

        <?php if (empty($assignments)): ?>
            <div class="empty-state">Šai klasei vēl nav uzdevumu.</div>
        <?php else: ?>
            <div class="dashboard-grid">
                <?php foreach ($assignments as $assignment): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <span class="badge">Iesniegumi: <?php echo $assignment['submission_count']; ?></span>
                        </div>
                        <p class="text-muted"><?php echo htmlspecialchars($assignment['description'] ?? ''); ?></p>
                        <p class="text-muted">Termiņš: <?php echo $assignment['due_at'] ? date('d.m.Y H:i', strtotime($assignment['due_at'])) : 'Nav norādīts'; ?></p>
                        <div class="card-actions">
                            <a href="assignment_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn btn-secondary">Iesniegumi</a>
                            <button class="btn btn-danger btn-small" onclick="deleteAssignment(<?php echo $assignment['id']; ?>)">Dzēst</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="assignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">Izveidot uzdevumu</h2>
                <button type="button" class="modal-close" onclick="closeModal('assignmentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignmentForm">
                    <input type="hidden" name="action" value="create_assignment">
                    <div class="form-group">
                        <label class="form-label" for="assignment_title">Nosaukums</label>
                        <input class="form-input" type="text" id="assignment_title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="assignment_description">Apraksts</label>
                        <textarea class="form-textarea" id="assignment_description" name="description" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="assignment_due">Termiņš</label>
                        <input class="form-input" type="datetime-local" id="assignment_due" name="due_at">
                    </div>
                    <label class="form-check">
                        <input type="checkbox" name="allow_submissions" checked>
                        <span>Atļaut iesniegumus</span>
                    </label>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignmentModal')">Atcelt</button>
                <button type="submit" form="assignmentForm" class="btn">Saglabāt</button>
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

        function deleteAssignment(assignmentId) {
            if (confirm('Dzēst šo uzdevumu?')) {
                fetch('manage_assignments.php?class_id=<?php echo $class_id; ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_assignment', assignment_id: assignmentId})
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                });
            }
        }

        document.getElementById('assignmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('manage_assignments.php?class_id=<?php echo $class_id; ?>', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (d.success) location.reload();
                else alert(d.message || 'Kļūda');
            });
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
