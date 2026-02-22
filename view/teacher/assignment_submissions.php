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

    $update_query = "UPDATE assignment_submissions SET grade = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('ii', $grade, $submission_id);
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

        <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: 6px; transition: background 0.3s;">
                <input type="radio" name="grade-type" value="grade" checked onchange="toggleGradeType('grade')" style="cursor: pointer;">
                <span>⭐ Atzīme (1-10)</span>
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: 6px; transition: background 0.3s;">
                <input type="radio" name="grade-type" value="percent" onchange="toggleGradeType('percent')" style="cursor: pointer;">
                <span>📊 Procenti (0-100%)</span>
            </label>
        </div>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Students</th>
                        <th>Fails</th>
                        <th>Iesniegts</th>
                        <th>Atzīme</th>
                        <th>Darbība</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></td>
                            <td><a href="../../uploads/<?php echo htmlspecialchars($submission['file_path']); ?>" class="btn btn-secondary btn-small" download>Lejupielādēt</a></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($submission['submitted_at'])); ?></td>
                            <td>
                                <input type="number" min="1" max="10" value="<?php echo $submission['grade'] ?? ''; ?>" class="grade-input form-input grade-mode" data-submission-id="<?php echo $submission['id']; ?>" style="max-width: 100px; text-align: center; padding: 0.75rem; font-size: 16px; font-weight: bold;" placeholder="1-10">
                                <input type="number" min="0" max="100" value="" class="percent-input form-input percent-mode" data-submission-id="<?php echo $submission['id']; ?>" style="max-width: 100px; text-align: center; padding: 0.75rem; font-size: 16px; font-weight: bold; display: none;" placeholder="0-100%">
                            </td>
                            <td><button class="btn btn-small" onclick="saveGrade(<?php echo $submission['id']; ?>)">Saglabāt</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function convertGradeToPercent(grade) {
            return grade ? Math.round((grade * 10)) : '';
        }

        function convertPercentToGrade(percent) {
            return percent ? Math.round((percent / 10)) : '';
        }

        function toggleGradeType(type) {
            const gradeInputs = document.querySelectorAll('.grade-input');
            const percentInputs = document.querySelectorAll('.percent-input');
            
            gradeInputs.forEach(input => {
                if (type === 'grade') {
                    input.style.display = '';
                    input.max = 10;
                    input.focus();
                } else {
                    // Konvertēt atzīmi uz procentiem
                    const grade = input.value;
                    const submissionId = input.dataset.submissionId;
                    const percentInput = document.querySelector(`.percent-input[data-submission-id="${submissionId}"]`);
                    if (grade) {
                        percentInput.value = convertGradeToPercent(grade);
                    }
                    input.style.display = 'none';
                }
            });
            
            percentInputs.forEach(input => {
                if (type === 'percent') {
                    input.style.display = '';
                    input.max = 100;
                    input.focus();
                } else {
                    // Konvertēt procentus uz atzīmi
                    const percent = input.value;
                    const submissionId = input.dataset.submissionId;
                    const gradeInput = document.querySelector(`.grade-input[data-submission-id="${submissionId}"]`);
                    if (percent) {
                        gradeInput.value = convertPercentToGrade(percent);
                    }
                    input.style.display = 'none';
                }
            });
        }

        function saveGrade(submissionId) {
            const gradeType = document.querySelector('input[name="grade-type"]:checked').value;
            let value = '';
            
            if (gradeType === 'grade') {
                const gradeInput = document.querySelector(`.grade-input[data-submission-id="${submissionId}"]`);
                value = parseInt(gradeInput.value) || '';
                
                if (value && (value < 1 || value > 10)) {
                    alert('Atzīme jābūt no 1 līdz 10!');
                    gradeInput.value = '';
                    return;
                }
            } else {
                const percentInput = document.querySelector(`.percent-input[data-submission-id="${submissionId}"]`);
                value = parseInt(percentInput.value) || '';
                
                if (value && (value < 0 || value > 100)) {
                    alert('Procenti jābūt no 0 līdz 100!');
                    percentInput.value = '';
                    return;
                }
            }
            
            const formData = new FormData();
            formData.append('submission_id', submissionId);
            formData.append('grade', value);

            fetch('assignment_submissions.php?id=<?php echo $assignment_id; ?>', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    alert('Atzīme saglabāta!');
                } else {
                    alert(d.message || 'Kļūda');
                }
            });
        }

        // Validācija reālā laikā
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('grade-input')) {
                let val = parseInt(e.target.value) || 0;
                if (val > 10) {
                    e.target.value = 10;
                } else if (val < 0) {
                    e.target.value = '';
                }
            }
            if (e.target.classList.contains('percent-input')) {
                let val = parseInt(e.target.value) || 0;
                if (val > 100) {
                    e.target.value = 100;
                } else if (val < 0) {
                    e.target.value = '';
                }
            }
        });
    </script>
</body>
</html>
