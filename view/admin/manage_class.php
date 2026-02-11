<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = AuthController::is_admin();

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

    if ($action === 'enroll_student') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $student_query = trim($_POST['student_query'] ?? '');

        if ($class_id <= 0 || $student_query === '') {
            echo json_encode(['success' => false, 'message' => 'Class and student are required']);
            exit();
        }

        $verify_query = $is_admin
            ? "SELECT id FROM classes WHERE id = ?"
            : "SELECT id FROM classes WHERE id = ? AND teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        if ($is_admin) {
            $verify_stmt->bind_param("i", $class_id);
        } else {
            $verify_stmt->bind_param("ii", $class_id, $user_id);
        }
        $verify_stmt->execute();

        if ($verify_stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            exit();
        }

        $student = null;
        if (strpos($student_query, '@') !== false) {
            $user_query = "SELECT id FROM users WHERE email = ? AND role = 'student' AND is_active = 1";
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param("s", $student_query);
            $user_stmt->execute();
            $student = $user_stmt->get_result()->fetch_assoc();
        } else {
            $parts = preg_split('/\s+/', $student_query, 2);
            $first_name = $parts[0] ?? '';
            $last_name = $parts[1] ?? '';

            $user_query = "SELECT id FROM users WHERE first_name = ? AND last_name = ? AND role = 'student' AND is_active = 1";
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param("ss", $first_name, $last_name);
            $user_stmt->execute();
            $result = $user_stmt->get_result();

            if ($result->num_rows > 1) {
                echo json_encode(['success' => false, 'message' => 'Atrasti vairāki studenti ar šo vārdu, lūdzu lieto e-pastu']);
                exit();
            }

            $student = $result->fetch_assoc();
        }

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Students nav atrasts']);
            exit();
        }

        $result = ClassController::enroll_student($class_id, (int)$student['id']);
        echo json_encode($result);
        exit();
    }

    if ($action === 'remove_student') {
        $class_id = (int)($_POST['class_id'] ?? ($payload['class_id'] ?? 0));
        $student_id = (int)($_POST['user_id'] ?? ($payload['user_id'] ?? 0));

        if ($class_id <= 0 || $student_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit();
        }

        $verify_query = $is_admin
            ? "SELECT id FROM classes WHERE id = ?"
            : "SELECT id FROM classes WHERE id = ? AND teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        if ($is_admin) {
            $verify_stmt->bind_param("i", $class_id);
        } else {
            $verify_stmt->bind_param("ii", $class_id, $user_id);
        }
        $verify_stmt->execute();

        if ($verify_stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            exit();
        }

        $removed = ClassController::remove_student($class_id, $student_id);
        echo json_encode(['success' => (bool)$removed]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

$class_id = $_GET['id'] ?? 0;

$verify_query = $is_admin
    ? "SELECT * FROM classes WHERE id = ?"
    : "SELECT * FROM classes WHERE id = ? AND teacher_id = ?";
$verify_stmt = $conn->prepare($verify_query);
if ($is_admin) {
    $verify_stmt->bind_param("i", $class_id);
} else {
    $verify_stmt->bind_param("ii", $class_id, $user_id);
}
$verify_stmt->execute();
if ($verify_stmt->get_result()->num_rows === 0) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

$students = ClassController::get_class_students($class_id);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klases pārvaldība - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Klases pārvaldība</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>Klases studenti</h1>
            <button class="btn" onclick="openModal('addStudentModal')">+ Pievienot studentu</button>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Vārds</th>
                        <th>E-pasts</th>
                        <th>Darbības</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td>
                                <button class="btn btn-danger btn-small" onclick="removeStudent(<?php echo $student['id']; ?>)">Noņemt</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Student Modal -->
    <div id="addStudentModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="addStudentTitle">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="addStudentTitle">Pievienot studentu klasei</h2>
                <button type="button" class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="action" value="enroll_student">
                    <div class="form-group">
                        <label class="form-label" for="student_query">Studenta vārds uzvārds vai e-pasts</label>
                        <input class="form-input" type="text" id="student_query" name="student_query" required placeholder="Piem., Janis Berzins">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addStudentModal')">Atcelt</button>
                        <button type="submit" class="btn">Pievienot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        }
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
            }
        }
        
        function removeStudent(studentId) {
            if (confirm('Noņemt studentu no klases?')) {
                fetch('manage_class.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'remove_student', class_id: <?php echo $class_id; ?>, user_id: studentId})
                }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addStudentForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('manage_class.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            alert('Students pievienots!');
                            closeModal('addStudentModal');
                            form.reset();
                            location.reload();
                        } else {
                            alert('Kļūda: ' + (d.message || 'Unknown error'));
                        }
                    })
                    .catch(e => alert('Error: ' + e.message));
                });
            }
        });
    </script>
</body>
</html>
