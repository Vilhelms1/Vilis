<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';

// Check if logged in and is admin
if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    header('Location: ../login-reg.php');
    exit();
}

$admin_id = $_SESSION['user_id'];

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

        $result = ClassController::create_class($name, $description, $admin_id);
        echo json_encode($result);
        exit();
    }

    if ($action === 'delete_class') {
        $class_id = (int)($_POST['class_id'] ?? ($payload['class_id'] ?? 0));

        if ($class_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid class id']);
            exit();
        }

        $delete_query = "DELETE FROM classes WHERE id = ? AND admin_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('ii', $class_id, $admin_id);
        $delete_stmt->execute();

        if ($delete_stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Class not found']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

$classes = ClassController::get_admin_classes($admin_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
       <link rel="stylesheet" href="../../assets/css/majaslapas-disange.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">🎓 ApgūstiVairāk Admin</div>
            <div class="nav-menu">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
          <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <button class="btn btn-primary" onclick="openModal('classModal')">+ Create Class</button>
        </div>
        
        <div class="dashboard-grid">
            <?php foreach ($classes as $class): ?>
                <div class="card class-card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($class['name']); ?></h3>
                        <div class="badge"><?php echo $class['student_count']; ?> Students</div>
                    </div>
                    <p><?php echo htmlspecialchars($class['description']); ?></p>
                    <div class="card-actions">
                        <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-secondary">Manage</a>
                        <a href="manage_quizzes.php?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary">Quizzes</a>
                        <button class="btn btn-danger btn-small" onclick="deleteClass(<?php echo $class['id']; ?>)">Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Create Class Modal -->
    <div id="classModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('classModal')">&times;</span>
            <h2>Create New Class</h2>
            <form id="createClassForm">
                <input type="hidden" name="action" value="create_class">
                <div class="form-group">
                    <label for="class_name">Class Name</label>
                    <input type="text" id="class_name" name="name" required placeholder="e.g., Programming Basics">
                </div>
                
                <div class="form-group">
                    <label for="class_description">Description</label>
                    <textarea id="class_description" name="description" rows="4" placeholder="Class description and objectives"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Class</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function deleteClass(classId) {
            if (confirm('Are you sure you want to delete this class? This action cannot be undone.')) {
                fetch('admin_dashboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_class', class_id: classId})
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                });
            }
        }
        
        // Handle create class form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('createClassForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('admin_dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            alert('Class created successfully!');
                            closeModal('classModal');
                            document.getElementById('createClassForm').reset();
                            location.reload();
                        } else {
                            alert('Error: ' + (d.message || 'Unknown error'));
                        }
                    })
                    .catch(e => {
                        alert('Error: ' + e.message);
                    });
                });
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
