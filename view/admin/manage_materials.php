<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    header('Location: ../login-reg.php');
    exit();
}

$class_id = $_GET['id'] ?? 0;
$admin_id = $_SESSION['user_id'];

// Verify admin owns this class
$verify_query = "SELECT * FROM classes WHERE id = ? AND admin_id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $class_id, $admin_id);
$verify_stmt->execute();
$class = $verify_stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: admin_dashboard.php');
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Materials - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="admin_dashboard.php" class="nav-back">← Back</a>
            <div class="nav-brand">Manage Class Materials</div>
            <a href="../process_logout.php" class="btn btn-small">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-header">
            <h1><?php echo htmlspecialchars($class['name']); ?> - Study Materials</h1>
            <button class="btn btn-primary" onclick="openModal('uploadModal')">📁 Upload Material</button>
        </div>
        
        <div class="materials-list">
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <p>No materials uploaded yet. Click "Upload Material" to add study documents.</p>
                </div>
            <?php else: ?>
                <?php foreach ($materials as $material): ?>
                    <div class="material-item">
                        <div class="material-header">
                            <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                            <span class="badge"><?php echo strtoupper($material['file_type']); ?></span>
                        </div>
                        <p class="material-date">Uploaded: <?php echo date('M d, Y H:i', strtotime($material['created_at'])); ?></p>
                        <div class="material-actions">
                            <a href="../../uploads/<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-secondary btn-small" download>Download</a>
                            <button class="btn btn-danger btn-small" onclick="deleteMaterial(<?php echo $material['id']; ?>)">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('uploadModal')">&times;</span>
            <h2>Upload Study Material</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                
                <div class="form-group">
                    <label for="document">Select File</label>
                    <input type="file" id="document" name="document" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                    <small>Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP</small>
                </div>
                
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(modalId) { document.getElementById(modalId).style.display = 'block'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/upload_material.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Upload failed');
                }
            });
        });
        
        function deleteMaterial(materialId) {
            if (confirm('Delete this material?')) {
                fetch('api/delete_material.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({material_id: materialId})
                }).then(r => r.json()).then(d => {
                    if (d.success) location.reload();
                });
            }
        }
    </script>
    
    <style>
        .materials-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .material-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6366f1;
        }
        
        .material-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .material-header h4 {
            margin: 0;
            color: #111827;
        }
        
        .material-date {
            color: #6b7280;
            font-size: 13px;
            margin: 10px 0;
        }
        
        .material-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
    </style>
</body>
</html>
