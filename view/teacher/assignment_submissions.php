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
$assignment_id = (int)($_GET['id'] ?? 0);

if ($assignment_id <= 0) {
    header('Location: ../teacher/teacher_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $grade = isset($_POST['grade']) ? (int)$_POST['grade'] : null;
    $feedback = trim($_POST['feedback'] ?? '');

    if ($submission_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid submission']);
        exit();
    }

    if (!$is_admin) {
        $check_query = "SELECT s.id FROM assignment_submissions s INNER JOIN class_assignments a ON s.assignment_id = a.id INNER JOIN classes c ON a.class_id = c.id WHERE s.id = ? AND c.teacher_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('ii', $submission_id, $user_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit();
        }
    }

    $update_query = "UPDATE assignment_submissions SET grade = ?, feedback = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('isi', $grade, $feedback, $submission_id);
    $update_stmt->execute();

    echo json_encode(['success' => $update_stmt->affected_rows >= 0]);
    exit();
}

$assignment_query = $is_admin
    ? "SELECT a.*, c.name as class_name FROM class_assignments a INNER JOIN classes c ON a.class_id = c.id WHERE a.id = ?"
    : "SELECT a.*, c.name as class_name FROM class_assignments a INNER JOIN classes c ON a.class_id = c.id WHERE a.id = ? AND c.teacher_id = ?";
$assignment_stmt = $conn->prepare($assignment_query);
if ($is_admin) {
    $assignment_stmt->bind_param('i', $assignment_id);
} else {
    $assignment_stmt->bind_param('ii', $assignment_id, $user_id);
}
$assignment_stmt->execute();
$assignment = $assignment_stmt->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: ../teacher/teacher_dashboard.php');
    exit();
}

$submissions_query = "SELECT s.*, u.first_name, u.last_name FROM assignment_submissions s INNER JOIN users u ON s.student_id = u.id WHERE s.assignment_id = ? ORDER BY s.submitted_at DESC";
$submissions_stmt = $conn->prepare($submissions_query);
$submissions_stmt->bind_param('i', $assignment_id);
$submissions_stmt->execute();
$submissions = $submissions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iesniegumi - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="manage_assignments.php?class_id=<?php echo $assignment['class_id']; ?>" class="nav-back">← Back</a>
            <div class="nav-brand"><?php echo htmlspecialchars($assignment['class_name']); ?> - Iesniegumi</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>◐</button>
                <a href="../process_logout.php" class="btn btn-small">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1><?php echo htmlspecialchars($assignment['title']); ?></h1>
            <span class="badge">Iesniegumi: <?php echo count($submissions); ?></span>
        </div>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Students</th>
                        <th>Fails</th>
                        <th>Iesniegts</th>
                        <th>Atzīme</th>
                        <th>Atsauksme</th>
                        <th>Darbība</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></td>
                            <td><a href="../../uploads/<?php echo htmlspecialchars($submission['file_path']); ?>" class="btn btn-secondary btn-small" download>Lejupielādēt</a></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($submission['submitted_at'])); ?></td>
                            <td><input type="number" min="1" max="10" value="<?php echo $submission['grade'] ?? ''; ?>" class="grade-input" data-submission-id="<?php echo $submission['id']; ?>"></td>
                            <td><input type="text" value="<?php echo htmlspecialchars($submission['feedback'] ?? ''); ?>" class="feedback-input" data-submission-id="<?php echo $submission['id']; ?>"></td>
                            <td><button class="btn btn-small" onclick="saveGrade(<?php echo $submission['id']; ?>)">Saglabāt</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function saveGrade(submissionId) {
            const gradeInput = document.querySelector(`.grade-input[data-submission-id="${submissionId}"]`);
            const feedbackInput = document.querySelector(`.feedback-input[data-submission-id="${submissionId}"]`);
            const formData = new FormData();
            formData.append('submission_id', submissionId);
            formData.append('grade', gradeInput.value);
            formData.append('feedback', feedbackInput.value);

            fetch('assignment_submissions.php?id=<?php echo $assignment_id; ?>', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (!d.success) alert(d.message || 'Error');
            });
        }
    </script>
</body>
</html>
