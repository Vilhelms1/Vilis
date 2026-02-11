<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/classController.php';

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
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);

        if ($name === '' || $teacher_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Class name and teacher are required']);
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

        $delete_query = "DELETE FROM classes WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('i', $class_id);
        $delete_stmt->execute();

        echo json_encode(['success' => $delete_stmt->affected_rows > 0]);
        exit();
    }

    if ($action === 'promote_teacher') {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            exit();
        }

        $user_query = "SELECT id FROM users WHERE email = ? AND is_active = 1";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param('s', $email);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }

        $result = AuthController::set_role((int)$user['id'], 'teacher');
        echo json_encode($result);
        exit();
    }

    if ($action === 'assign_teacher') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);

        if ($class_id <= 0 || $teacher_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }

        $updated = ClassController::set_class_teacher($class_id, $teacher_id);
        echo json_encode(['success' => (bool)$updated]);
        exit();
    }

    if ($action === 'create_news') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit();
        }

        $image_name = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/';
            $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $safe_token = bin2hex(random_bytes(8));
            $image_name = 'news_' . $safe_token . ($extension ? '.' . $extension : '');
            $target_path = $upload_dir . $image_name;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                echo json_encode(['success' => false, 'message' => 'Could not save image']);
                exit();
            }
        }

        $insert_query = "INSERT INTO school_news (title, body, image_path, created_by) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param('sssi', $title, $body, $image_name, $admin_id);

        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            if ($image_name) {
                @unlink(__DIR__ . '/../../uploads/' . $image_name);
            }
            echo json_encode(['success' => false, 'message' => 'Database insert failed']);
        }
        exit();
    }

    if ($action === 'delete_news') {
        $news_id = (int)($_POST['news_id'] ?? ($payload['news_id'] ?? 0));
        if ($news_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid news id']);
            exit();
        }

        $select_query = "SELECT image_path FROM school_news WHERE id = ?";
        $select_stmt = $conn->prepare($select_query);
        $select_stmt->bind_param('i', $news_id);
        $select_stmt->execute();
        $news = $select_stmt->get_result()->fetch_assoc();

        $delete_query = "DELETE FROM school_news WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('i', $news_id);
        $delete_stmt->execute();

        if ($delete_stmt->affected_rows > 0 && $news && !empty($news['image_path'])) {
            $file_path = __DIR__ . '/../../uploads/' . $news['image_path'];
            if (is_file($file_path)) {
                @unlink($file_path);
            }
        }

        echo json_encode(['success' => $delete_stmt->affected_rows > 0]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

$teachers_query = "SELECT id, first_name, last_name, email FROM users WHERE role = 'teacher' AND is_active = 1 ORDER BY first_name";
$teachers_stmt = $conn->prepare($teachers_query);
$teachers_stmt->execute();
$teachers = $teachers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$classes = ClassController::get_all_classes();

$news_query = "SELECT id, title, image_path, published_at FROM school_news ORDER BY published_at DESC LIMIT 6";
$news_stmt = $conn->prepare($news_query);
$news_stmt->execute();
$news_items = $news_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panelis - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">👨‍💼 ApgūstiVairāk Admin</div>
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
            <h1 class="page-title">Admin Panelis</h1>
            <p class="page-subtitle">Pārvaldi klases, skolotājus un jaunumus</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2" style="margin-bottom: 2rem; flex-wrap: wrap;">
            <button class="btn" onclick="openModal('classModal')">+ Jauna mācību klase</button>
            <button class="btn btn-secondary" onclick="openModal('teacherModal')">+ Jaunais skolotājs</button>
            <button class="btn btn-secondary" onclick="openModal('newsModal')">+ Jauns jaunums</button>
        </div>

        <!-- Tabs -->
        <div class="tabs" style="margin-bottom: 2rem;">
            <button class="tab-btn active" onclick="switchTab('classes')">📚 Mācību Klases</button>
            <button class="tab-btn" onclick="switchTab('teachers')">👨‍🏫 Skolotāji</button>
            <button class="tab-btn" onclick="switchTab('news')">📰 Jaunumi</button>
        </div>

        <!-- Classes Tab -->
        <div id="classes" class="tab-content active">
            <div class="grid grid-2">
                <?php foreach ($classes as $class): ?>
                    <div class="card slide-up">
                        <div class="card-header">
                            <h3 class="card-title" style="font-size: 1.175rem;"><?php echo htmlspecialchars($class['name']); ?></h3>
                            <span class="badge"><?php echo $class['student_count']; ?> 👥</span>
                        </div>
                        <div class="card-body">
                            <p style="color: var(--text-secondary); margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($class['description'] ?? 'Nav apraksta'); ?>
                            </p>
                            <p style="color: var(--text-tertiary); font-size: 0.9rem; margin: 0;">
                                👨‍🏫 <?php echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']); ?>
                            </p>
                        </div>
                        <div class="card-footer">
                            <a href="class_detail.php?id=<?php echo $class['id']; ?>" class="btn btn-secondary">Apskatīt</a>
                            <button class="btn btn-secondary" onclick="openAssignModal(<?php echo $class['id']; ?>)">Mainīt</button>
                            <button class="btn btn-danger" onclick="deleteClass(<?php echo $class['id']; ?>)">Dzēst</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Teachers Tab -->
        <div id="teachers" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Skolotāju Saraksts</h3>
                </div>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>👤 Vārds</th>
                                <th>📧 E-pasts</th>
                                <th>⚙️ Darbības</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td>
                                        <a href="teacher_detail.php?id=<?php echo $teacher['id']; ?>" class="btn btn-secondary btn-small">Klases</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- News Tab -->
        <div id="news" class="tab-content">
            <div class="grid grid-2">
                <?php foreach ($news_items as $news): ?>
                    <?php $image_path = !empty($news['image_path']) ? BASE_URL . 'uploads/' . $news['image_path'] : BASE_URL . 'assets/image/picture.jpg'; ?>
                    <div class="card slide-up news-item-card">
                        <img class="news-thumb" src="<?php echo htmlspecialchars($image_path); ?>" alt="Jaunuma attēls">
                        <div style="flex: 1;">
                            <div class="card-header">
                                <h3 class="card-title" style="font-size: 1.1rem;"><?php echo htmlspecialchars($news['title']); ?></h3>
                            </div>
                            <div class="card-body">
                                <p style="color: var(--text-tertiary); font-size: 0.9rem; margin: 0;">
                                    📅 <?php echo date('d.m.Y', strtotime($news['published_at'])); ?>
                                </p>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-danger" onclick="deleteNews(<?php echo $news['id']; ?>)">Dzēst</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    
    <!-- Create Class Modal -->
    <div id="classModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin: 0;">📚 Izveidot jaunu klasi</h2>
            </div>
            <div class="modal-body">
                <form id="createClassForm">
                    <input type="hidden" name="action" value="create_class">
                    
                    <div class="form-group">
                        <label class="form-label">Klases nosaukums</label>
                        <input class="form-input" type="text" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Apraksts</label>
                        <textarea class="form-textarea" name="description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Skolotājs</label>
                        <select class="form-select" name="teacher_id" required>
                            <option value="">Izvēlies skolotāju</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('classModal')">Atcelt</button>
                <button type="submit" form="createClassForm" class="btn">Izveidot</button>
            </div>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div id="teacherModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin: 0;">👨‍🏫 Pievienot jaunu skolotāju</h2>
            </div>
            <div class="modal-body">
                <form id="promoteTeacherForm">
                    <input type="hidden" name="action" value="promote_teacher">
                    
                    <div class="form-group">
                        <label class="form-label">Skolotāja e-pasts</label>
                        <input class="form-input" type="email" name="email" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('teacherModal')">Atcelt</button>
                <button type="submit" form="promoteTeacherForm" class="btn">Apstiprināt</button>
            </div>
        </div>
    </div>

    <!-- Assign Teacher Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin: 0;">👨‍🏫 Mainīt skolotāju</h2>
            </div>
            <div class="modal-body">
                <form id="assignTeacherForm">
                    <input type="hidden" name="action" value="assign_teacher">
                    <input type="hidden" name="class_id" id="assign_class_id" value="">
                    
                    <div class="form-group">
                        <label class="form-label">Jaunais skolotājs</label>
                        <select class="form-select" name="teacher_id" required>
                            <option value="">Izvēlies skolotāju</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Atcelt</button>
                <button type="submit" form="assignTeacherForm" class="btn">Saglabāt</button>
            </div>
        </div>
    </div>

    <!-- Create News Modal -->
    <div id="newsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin: 0;">📰 Publicēt jaunu jaunumu</h2>
            </div>
            <div class="modal-body">
                <form id="newsForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_news">
                    
                    <div class="form-group">
                        <label class="form-label">Virsraksts</label>
                        <input class="form-input" type="text" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Teksts</label>
                        <textarea class="form-textarea" name="body"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Attēls (pēc izvēles)</label>
                        <input class="form-input" type="file" name="image" accept=".png,.jpg,.jpeg">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newsModal')">Atcelt</button>
                <button type="submit" form="newsForm" class="btn">Publicēt</button>
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
                fetch('admin_dashboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_class', class_id: classId})
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                    else alert(d.message || 'Kļūda');
                });
            }
        }

        function openAssignModal(classId) {
            document.getElementById('assign_class_id').value = classId;
            openModal('assignModal');
        }

        function deleteNews(newsId) {
            if (confirm('Vai tiešām vēlies dzēst šo jaunumu?')) {
                fetch('admin_dashboard.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_news', news_id: newsId})
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                    else alert(d.message || 'Kļūda');
                });
            }
        }

        document.getElementById('createClassForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('admin_dashboard.php', {method: 'POST', body: formData})
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.message || 'Kļūda');
                    }
                });
        });

        document.getElementById('promoteTeacherForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('admin_dashboard.php', {method: 'POST', body: formData})
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.message || 'Kļūda');
                    }
                });
        });

        document.getElementById('assignTeacherForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('admin_dashboard.php', {method: 'POST', body: formData})
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.message || 'Kļūda');
                    }
                });
        });

        document.getElementById('newsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('admin_dashboard.php', {method: 'POST', body: formData})
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
