<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$assignment_id = (int)($_GET['id'] ?? ($_POST['assignment_id'] ?? 0));

if ($assignment_id <= 0) {
    header('Location: student_dashboard.php');
    exit();
}

$assignment_query = "SELECT a.*, c.id as class_id FROM class_assignments a INNER JOIN classes c ON a.class_id = c.id WHERE a.id = ?";
$assignment_stmt = $conn->prepare($assignment_query);
$assignment_stmt->bind_param('i', $assignment_id);
$assignment_stmt->execute();
$assignment = $assignment_stmt->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: student_dashboard.php');
    exit();
}

$enroll_check = "SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?";
$enroll_stmt = $conn->prepare($enroll_check);
$enroll_stmt->bind_param('ii', $assignment['class_id'], $user_id);
$enroll_stmt->execute();
if ($enroll_stmt->get_result()->num_rows === 0) {
    header('Location: student_dashboard.php');
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$assignment['allow_submissions']) {
        $error = 'Iesniegumi šim uzdevumam nav atļauti.';
    } elseif (!isset($_FILES['work_file']) || $_FILES['work_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Faila augšupielāde neizdevās.';
    } else {
        $upload_dir = __DIR__ . '/../../uploads/';
        $extension = strtolower(pathinfo($_FILES['work_file']['name'], PATHINFO_EXTENSION));
        $safe_token = bin2hex(random_bytes(8));
        $file_name = 'work_' . $safe_token . ($extension ? '.' . $extension : '');
        $target_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['work_file']['tmp_name'], $target_path)) {
            $error = 'Neizdevās saglabāt failu.';
        } else {
            $insert_query = "INSERT INTO assignment_submissions (assignment_id, student_id, file_path, file_type) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('iiss', $assignment_id, $user_id, $file_name, $extension);
            if ($insert_stmt->execute()) {
                $success = 'Darbs iesniegts!';
            } else {
                @unlink($target_path);
                $error = 'Datubāzes kļūda.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iesniegt darbu - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="class_details.php?id=<?php echo $assignment['class_id']; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Iesniegt darbu</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1><?php echo htmlspecialchars($assignment['title']); ?></h1>
            <p><?php echo htmlspecialchars($assignment['description'] ?? ''); ?></p>
            <p class="text-muted">Termiņš: <?php echo $assignment['due_at'] ? date('d.m.Y H:i', strtotime($assignment['due_at'])) : 'Nav norādīts'; ?></p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                <div class="form-group">
                    <label for="work_file">Darba fails</label>
                    <input type="file" id="work_file" name="work_file" required>
                </div>
                <button type="submit" class="btn">Iesniegt</button>
            </form>
        </div>
    </div>
</body>
</html>
