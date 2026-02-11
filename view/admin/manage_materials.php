<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';

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

    if ($action === 'upload_material') {
        $class_id = (int)($_POST['class_id'] ?? 0);

        if ($class_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid class id']);
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

        if (!isset($_FILES['document'])) {
            echo json_encode(['success' => false, 'message' => 'File upload failed']);
            exit();
        }

        $upload_dir = __DIR__ . '/../../uploads/';
        if (!is_dir($upload_dir)) {
            echo json_encode(['success' => false, 'message' => 'Upload directory missing']);
            exit();
        }

        $names = $_FILES['document']['name'];
        $tmp_names = $_FILES['document']['tmp_name'];
        $errors = $_FILES['document']['error'];

        if (!is_array($names)) {
            $names = [$names];
            $tmp_names = [$tmp_names];
            $errors = [$errors];
        }

        $insert_query = "INSERT INTO class_materials (class_id, title, file_path, file_type, material_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $saved_count = 0;
        $failed_count = 0;

        foreach ($names as $index => $original_name) {
            if (($errors[$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $failed_count++;
                continue;
            }

            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $base_name = trim(pathinfo($original_name, PATHINFO_FILENAME));
            $title = $base_name !== '' ? $base_name : 'Document';

            $safe_token = bin2hex(random_bytes(8));
            $file_name = $safe_token . ($extension ? '.' . $extension : '');
            $target_path = $upload_dir . $file_name;

            if (!move_uploaded_file($tmp_names[$index], $target_path)) {
                $failed_count++;
                continue;
            }

            $file_type = $extension !== '' ? $extension : 'file';
            $material_type = in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? 'image' : 'document';
            $insert_stmt->bind_param("issssi", $class_id, $title, $file_name, $file_type, $material_type, $user_id);

            if ($insert_stmt->execute()) {
                $saved_count++;
            } else {
                @unlink($target_path);
                $failed_count++;
            }
        }

        if ($saved_count > 0 && $failed_count === 0) {
            echo json_encode(['success' => true]);
        } elseif ($saved_count > 0) {
            echo json_encode(['success' => true, 'message' => 'Some files failed to upload']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No files were uploaded']);
        }
        exit();
    }

    if ($action === 'delete_material') {
        $material_id = (int)($_POST['material_id'] ?? ($payload['material_id'] ?? 0));

        if ($material_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid material id']);
            exit();
        }

        $select_query = $is_admin
            ? "SELECT cm.file_path FROM class_materials cm INNER JOIN classes c ON cm.class_id = c.id WHERE cm.id = ?"
            : "SELECT cm.file_path FROM class_materials cm INNER JOIN classes c ON cm.class_id = c.id WHERE cm.id = ? AND c.teacher_id = ?";
        $select_stmt = $conn->prepare($select_query);
        if ($is_admin) {
            $select_stmt->bind_param("i", $material_id);
        } else {
            $select_stmt->bind_param("ii", $material_id, $user_id);
        }
        $select_stmt->execute();
        $material = $select_stmt->get_result()->fetch_assoc();

        if (!$material) {
            echo json_encode(['success' => false, 'message' => 'Material not found']);
            exit();
        }

        $delete_query = "DELETE FROM class_materials WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $material_id);
        $delete_stmt->execute();

        if ($delete_stmt->affected_rows > 0) {
            $file_path = __DIR__ . '/../../uploads/' . $material['file_path'];
            if (is_file($file_path)) {
                @unlink($file_path);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

$class_id = $_GET['id'] ?? 0;

// Verify admin owns this class
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
$class = $verify_stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

// Get materials
$materials_query = "SELECT * FROM class_materials WHERE class_id = ? ORDER BY created_at DESC";
$materials_stmt = $conn->prepare($materials_query);
$materials_stmt->bind_param("i", $class_id);
$materials_stmt->execute();
$materials = $materials_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Materials - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Materiāli</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header" style="margin-bottom: 1.5rem;">
            <h1 class="page-title">Materiāli</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($class['name']); ?> · Pievieno un pārvaldi materiālus</p>
        </div>
        <div class="flex gap-2" style="margin-bottom: 2rem;">
            <button class="btn" onclick="openModal('uploadModal')">📁 Pievienot materiālu</button>
        </div>
        
        <div class="materials-list">
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    Šeit vēl nav materiālu. Spied “Pievienot materiālu”, lai augšupielādētu failus.
                </div>
            <?php else: ?>
                <?php foreach ($materials as $material): ?>
                    <div class="material-item">
                        <div class="material-header">
                            <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                            <span class="badge"><?php echo strtoupper($material['file_type']); ?></span>
                        </div>
                        <p class="material-date">Pievienots: <?php echo date('d.m.Y H:i', strtotime($material['created_at'])); ?></p>
                        <div class="material-actions">
                            <a href="../../uploads/<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-secondary btn-small" download>Lejupielādēt</a>
                            <button class="btn btn-danger btn-small" onclick="deleteMaterial(<?php echo $material['id']; ?>)">Dzēst</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">Pievienot mācību materiālu</h2>
                <button type="button" class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="action" value="upload_material">
                    
                    <div class="form-group">
                        <label class="form-label" for="document">Izvēlies failu</label>
                        <input class="form-input" type="file" id="document" name="document[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.png,.jpg,.jpeg,.gif,.webp">
                        <small class="text-muted">Atļauts: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, PNG, JPG, WEBP. Vari izvēlēties vairākus failus.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Atcelt</button>
                <button type="submit" form="uploadForm" class="btn">Augšupielādēt</button>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('manage_materials.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Augšupielāde neizdevās');
                }
            });
        });
        
        function deleteMaterial(materialId) {
            if (confirm('Dzēst šo materiālu?')) {
                fetch('manage_materials.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_material', material_id: materialId})
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                });
            }
        }
    </script>
    
</body>
</html>
