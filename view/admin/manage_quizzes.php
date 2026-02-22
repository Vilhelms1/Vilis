<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    header('Location: ../login-reg.php');
    exit();
}

$class_id = (int)($_GET['class_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_admin = AuthController::is_admin();

if ($class_id <= 0) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

// Verify teacher owns class or admin access
$class_query = $is_admin
    ? "SELECT * FROM classes WHERE id = ?"
    : "SELECT * FROM classes WHERE id = ? AND teacher_id = ?";
$class_stmt = $conn->prepare($class_query);
if ($is_admin) {
    $class_stmt->bind_param("i", $class_id);
} else {
    $class_stmt->bind_param("ii", $class_id, $user_id);
}
$class_stmt->execute();
$class = $class_stmt->get_result()->fetch_assoc();

if (!$class) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

$students = [];
$students_query = "SELECT u.id, u.first_name, u.last_name FROM users u INNER JOIN class_enrollments ce ON ce.student_id = u.id WHERE ce.class_id = ? ORDER BY u.first_name, u.last_name";
$students_stmt = $conn->prepare($students_query);
if ($students_stmt) {
    $students_stmt->bind_param("i", $class_id);
    $students_stmt->execute();
    $students = $students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($action === 'create_quiz') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $time_limit = (int)($_POST['time_limit'] ?? 0);
        $passing_score = (int)($_POST['passing_score'] ?? 60);
        $show_leaderboard = isset($_POST['show_leaderboard']) ? 1 : 1;
        $max_attempts_add = (int)($_POST['max_attempts_add'] ?? 0);
        $current_max_attempts = (int)($_POST['current_max_attempts'] ?? 0);
        $publish_now = (int)($_POST['publish_now'] ?? 0);
        
        // Konvertēt datetime-local formātu uz MySQL DATETIME formātu
        $available_until = null;
        if (!empty($_POST['available_until'])) {
            $dt = str_replace('T', ' ', $_POST['available_until']); // 2026-02-22T15:30 -> 2026-02-22 15:30
            $available_until = $dt . ':00'; // Pievienot sekundes
        }
        
        $scheduled_at = null;
        $status = $publish_now ? 'published' : 'draft';

        if ($title === '') {
            $error_message = 'Testa nosaukums ir obligāts.';
        } elseif ($passing_score < 0 || $passing_score > 100) {
            $error_message = 'Nokārtošanas slieksnis jābūt no 0 līdz 100.';
        } else {
            $result = QuizController::create_quiz($class_id, $title, $description, $user_id, $time_limit, $passing_score, $show_leaderboard, $max_attempts, $available_until, $status, $scheduled_at);
            if (!empty($result['success'])) {
                header('Location: edit_quiz.php?id=' . $result['quiz_id']);
                exit();
            }
            $error_message = $result['message'] ?? 'Kļūda veidojot testu.';
        }
    }

    if ($action === 'delete_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        if ($quiz_id <= 0) {
            $error_message = 'Nederīgs testa ID.';
        } else {
            $delete_query = $is_admin
                ? "DELETE FROM quizzes WHERE id = ? AND class_id = ?"
                : "DELETE FROM quizzes WHERE id = ? AND class_id = ? AND created_by = ?";
            $delete_stmt = $conn->prepare($delete_query);
            if ($is_admin) {
                $delete_stmt->bind_param('ii', $quiz_id, $class_id);
            } else {
                $delete_stmt->bind_param('iii', $quiz_id, $class_id, $user_id);
            }
            $delete_stmt->execute();

            if ($delete_stmt->affected_rows > 0) {
                header('Location: manage_quizzes.php?class_id=' . $class_id);
                exit();
            }
            $error_message = 'Neizdevās dzēst testu.';
        }
    }

    if ($action === 'set_quiz_status') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        $allowed = ['draft', 'published'];

        if ($quiz_id <= 0 || !in_array($new_status, $allowed, true)) {
            $error_message = 'Nederīgs testa statuss.';
        } else {
            if ($new_status === 'published') {
                $count_query = "SELECT COUNT(*) as total FROM questions WHERE quiz_id = ?";
                $count_stmt = $conn->prepare($count_query);
                $count_stmt->bind_param('i', $quiz_id);
                $count_stmt->execute();
                $count_row = $count_stmt->get_result()->fetch_assoc();
                if ((int)($count_row['total'] ?? 0) === 0) {
                    $error_message = 'Pirms publicēšanas pievieno vismaz vienu jautājumu.';
                }
            }

            if (!$error_message) {
                $is_active = $new_status === 'published' ? 1 : 0;
                $update_query = $is_admin
                    ? "UPDATE quizzes SET status = ?, is_active = ? WHERE id = ? AND class_id = ?"
                    : "UPDATE quizzes SET status = ?, is_active = ? WHERE id = ? AND class_id = ? AND created_by = ?";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt === false) {
                    $update_query = $is_admin
                        ? "UPDATE quizzes SET is_active = ? WHERE id = ? AND class_id = ?"
                        : "UPDATE quizzes SET is_active = ? WHERE id = ? AND class_id = ? AND created_by = ?";
                    $update_stmt = $conn->prepare($update_query);
                }
                if ($update_stmt === false) {
                    $error_message = 'Neizdevās atjaunot testa statusu.';
                } else {
                    if ($is_admin) {
                        $types = strpos($update_query, 'status') !== false ? 'siii' : 'iii';
                        $types === 'siii'
                            ? $update_stmt->bind_param($types, $new_status, $is_active, $quiz_id, $class_id)
                            : $update_stmt->bind_param($types, $is_active, $quiz_id, $class_id);
                    } else {
                        $types = strpos($update_query, 'status') !== false ? 'siiii' : 'iiii';
                        $types === 'siiii'
                            ? $update_stmt->bind_param($types, $new_status, $is_active, $quiz_id, $class_id, $user_id)
                            : $update_stmt->bind_param($types, $is_active, $quiz_id, $class_id, $user_id);
                    }
                    $update_stmt->execute();
                }

                if (!$error_message && $update_stmt->affected_rows > 0) {
                    header('Location: manage_quizzes.php?class_id=' . $class_id);
                    exit();
                }
                if (!$error_message) {
                    $error_message = 'Neizdevās atjaunot testa statusu.';
                }
            }
        }
    }

    if ($action === 'repeat_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $time_limit = (int)($_POST['time_limit'] ?? 0);
        $passing_score = (int)($_POST['passing_score'] ?? 60);
        $max_attempts_add = (int)($_POST['max_attempts_add'] ?? 0);
        $repeat_scope = $_POST['repeat_scope'] ?? 'class';
        $repeat_student_ids = $_POST['repeat_student_ids'] ?? [];
        if (!is_array($repeat_student_ids)) {
            $repeat_student_ids = [];
        }
        $available_until = null;
        if (!empty($_POST['available_until'])) {
            $dt = str_replace('T', ' ', $_POST['available_until']);
            $available_until = $dt . ':00';
        }

        if ($quiz_id <= 0 || $title === '') {
            $error_message = 'Nederīgi dati.';
        } elseif ($passing_score < 0 || $passing_score > 100) {
            $error_message = 'Nokārtošanas slieksnis jābūt no 0 līdz 100.';
        } else {
            $count_query = "SELECT COUNT(*) as total FROM questions WHERE quiz_id = ?";
            $count_stmt = $conn->prepare($count_query);
            if ($count_stmt) {
                $count_stmt->bind_param('i', $quiz_id);
                $count_stmt->execute();
                $count_row = $count_stmt->get_result()->fetch_assoc();
                if ((int)($count_row['total'] ?? 0) === 0) {
                    $error_message = 'Pirms publicēšanas pievieno vismaz vienu jautājumu.';
                }
            }

            if (!$error_message) {
                $max_attempts_add = max(0, $max_attempts_add);
                $max_attempts_add_for_quiz = $repeat_scope === 'class' ? $max_attempts_add : 0;
                $update_query = $is_admin
                    ? "UPDATE quizzes SET title = ?, time_limit = ?, passing_score = ?, max_attempts = COALESCE(max_attempts, 0) + ?, available_until = ?, status = 'published', is_active = 1 WHERE id = ? AND class_id = ?"
                    : "UPDATE quizzes SET title = ?, time_limit = ?, passing_score = ?, max_attempts = COALESCE(max_attempts, 0) + ?, available_until = ?, status = 'published', is_active = 1 WHERE id = ? AND class_id = ? AND created_by = ?";
                $update_stmt = $conn->prepare($update_query);
                if ($update_stmt === false) {
                    $update_query = $is_admin
                        ? "UPDATE quizzes SET title = ?, time_limit = ?, passing_score = ?, max_attempts = COALESCE(max_attempts, 0) + ?, available_until = ?, is_active = 1 WHERE id = ? AND class_id = ?"
                        : "UPDATE quizzes SET title = ?, time_limit = ?, passing_score = ?, max_attempts = COALESCE(max_attempts, 0) + ?, available_until = ?, is_active = 1 WHERE id = ? AND class_id = ? AND created_by = ?";
                    $update_stmt = $conn->prepare($update_query);
                }

                if ($update_stmt) {
                    if ($is_admin) {
                        $update_stmt->bind_param('siiisii', $title, $time_limit, $passing_score, $max_attempts_add_for_quiz, $available_until, $quiz_id, $class_id);
                    } else {
                        $update_stmt->bind_param('siiisiii', $title, $time_limit, $passing_score, $max_attempts_add_for_quiz, $available_until, $quiz_id, $class_id, $user_id);
                    }
                    $update_stmt->execute();
                    if ($update_stmt->affected_rows >= 0) {
                        if ($repeat_scope === 'students' && $max_attempts_add > 0) {
                            $repeat_student_ids = array_values(array_filter(array_map('intval', $repeat_student_ids)));
                            if (empty($repeat_student_ids)) {
                                $error_message = 'Izvēlies vismaz vienu skolēnu.';
                            } else {
                                $override_query = "INSERT INTO quiz_attempt_overrides (quiz_id, user_id, extra_attempts) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE extra_attempts = extra_attempts + VALUES(extra_attempts)";
                                $override_stmt = $conn->prepare($override_query);
                                if ($override_stmt) {
                                    foreach ($repeat_student_ids as $student_id) {
                                        $override_stmt->bind_param('iii', $quiz_id, $student_id, $max_attempts_add);
                                        $override_stmt->execute();
                                    }
                                }
                            }
                        }

                        if (!$error_message) {
                            header('Location: manage_quizzes.php?class_id=' . $class_id);
                            exit();
                        }
                    }
                }
                if (!$error_message) {
                    $error_message = 'Neizdevās publicēt testu.' . ($conn->error ? ' ' . $conn->error : '');
                }
            }
        }
    }

    if ($action === 'copy_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        if ($quiz_id <= 0) {
            $error_message = 'Nederīgs testa ID.';
        } else {
            $orig_query = "SELECT * FROM quizzes WHERE id = ? AND class_id = ?";
            $orig_stmt = $conn->prepare($orig_query);
            if (!$orig_stmt) {
                $error_message = 'Database error: ' . $conn->error;
            } else {
                $orig_stmt->bind_param('ii', $quiz_id, $class_id);
                $orig_stmt->execute();
                $original_quiz = $orig_stmt->get_result()->fetch_assoc();

                if ($original_quiz) {
                    $copy_title = $original_quiz['title'] . ' (kopija)';
                    $status = 'draft';
                    $scheduled_at = null;
                    $is_active = 0;

                    $insert_query = "INSERT INTO quizzes (class_id, title, description, created_by, time_limit, passing_score, show_leaderboard, max_attempts, available_until, status, scheduled_at, is_active)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    if ($insert_stmt === false) {
                        $insert_query = "INSERT INTO quizzes (class_id, title, description, created_by, time_limit, passing_score, show_leaderboard, max_attempts, available_until, is_active)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_query);
                    }

                    if (!$insert_stmt) {
                        $error_message = 'Database error: ' . $conn->error;
                    } else {
                        if (strpos($insert_query, 'status') !== false) {
                            $insert_stmt->bind_param("issiiiiisssi",
                                $class_id,
                                $copy_title,
                                $original_quiz['description'],
                                $user_id,
                                $original_quiz['time_limit'],
                                $original_quiz['passing_score'],
                                $original_quiz['show_leaderboard'],
                                $original_quiz['max_attempts'],
                                $original_quiz['available_until'],
                                $status,
                                $scheduled_at,
                                $is_active
                            );
                        } else {
                            $insert_stmt->bind_param("issiiiiisi",
                                $class_id,
                                $copy_title,
                                $original_quiz['description'],
                                $user_id,
                                $original_quiz['time_limit'],
                                $original_quiz['passing_score'],
                                $original_quiz['show_leaderboard'],
                                $original_quiz['max_attempts'],
                                $original_quiz['available_until'],
                                $is_active
                            );
                        }

                        if ($insert_stmt->execute()) {
                            $new_quiz_id = $insert_stmt->insert_id;

                            $get_q_query = "SELECT id, question_text, question_type, points, question_image FROM questions WHERE quiz_id = ?";
                            $get_q_stmt = $conn->prepare($get_q_query);
                            if (!$get_q_stmt) {
                                $error_message = 'Database error in Step 1: ' . $conn->error;
                            } else {
                                $get_q_stmt->bind_param("i", $quiz_id);
                                $get_q_stmt->execute();
                                $orig_questions = $get_q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                                $question_map = [];
                                foreach ($orig_questions as $orig_q) {
                                    $insert_q = "INSERT INTO questions (quiz_id, question_text, question_type, points, question_image) VALUES (?, ?, ?, ?, ?)";
                                    $insert_q_stmt = $conn->prepare($insert_q);
                                    if (!$insert_q_stmt) {
                                        $error_message = 'Database error in Step 2: ' . $conn->error;
                                        break;
                                    }
                                    $insert_q_stmt->bind_param("issis",
                                        $new_quiz_id,
                                        $orig_q['question_text'],
                                        $orig_q['question_type'],
                                        $orig_q['points'],
                                        $orig_q['question_image']
                                    );
                                    if ($insert_q_stmt->execute()) {
                                        $new_q_id = $insert_q_stmt->insert_id;
                                        $question_map[$orig_q['id']] = $new_q_id;
                                    }
                                }

                                if (!$error_message) {
                                    foreach ($question_map as $old_q_id => $new_q_id) {
                                        $get_a_query = "SELECT answer_text, is_correct FROM answers WHERE question_id = ?";
                                        $get_a_stmt = $conn->prepare($get_a_query);
                                        if ($get_a_stmt) {
                                            $get_a_stmt->bind_param("i", $old_q_id);
                                            $get_a_stmt->execute();
                                            $answers = $get_a_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            foreach ($answers as $answer) {
                                                $insert_a = "INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                                                $insert_a_stmt = $conn->prepare($insert_a);
                                                if ($insert_a_stmt) {
                                                    $insert_a_stmt->bind_param("isi",
                                                        $new_q_id,
                                                        $answer['answer_text'],
                                                        $answer['is_correct']
                                                    );
                                                    $insert_a_stmt->execute();
                                                }
                                            }
                                        }
                                    }

                                    header('Location: manage_quizzes.php?class_id=' . $class_id . '&success=1');
                                    exit();
                                }
                            }
                        } else {
                            $error_message = 'Kļūda veidojot kopiju: ' . $insert_stmt->error;
                        }
                    }
                } else {
                    $error_message = 'Oriģinālais tests nav atrasts.';
                }
            }
        }
    }

    if ($action === 'duplicate_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $new_title = trim($_POST['new_title'] ?? '');
        $new_time_limit = (int)($_POST['new_time_limit'] ?? 0);
        $new_max_attempts = (int)($_POST['new_max_attempts'] ?? 0);
        $new_passing_score = ($_POST['new_passing_score'] ?? '') !== '' ? (int)$_POST['new_passing_score'] : null;
        $publish_now = isset($_POST['publish_now']) ? 1 : 0;
        $redirect_to_edit = isset($_POST['redirect_to_edit']);
        
        // Konvertēt datetime-local formātu uz MySQL DATETIME formātu
        $new_available_until = null;
        if (!empty($_POST['new_available_until'])) {
            $dt = str_replace('T', ' ', $_POST['new_available_until']);
            $new_available_until = $dt . ':00';
        }
        
        $scheduled_at = null;
        $status = $publish_now ? 'published' : 'draft';
        
        if ($quiz_id <= 0 || $new_title === '') {
            $error_message = 'Nederīgi dati duplikācijai.';
        } else {
            // Get original quiz - simplified query
            $orig_query = "SELECT * FROM quizzes WHERE id = ? AND class_id = ?";
            $orig_stmt = $conn->prepare($orig_query);
            
            if (!$orig_stmt) {
                $error_message = 'Database error: ' . $conn->error;
            } else {
                $orig_stmt->bind_param('ii', $quiz_id, $class_id);
                $orig_stmt->execute();
                $original_quiz = $orig_stmt->get_result()->fetch_assoc();
                
                if ($original_quiz) {
                    // Insert duplicate quiz with new title and parameters
                    $final_passing_score = $new_passing_score !== null ? $new_passing_score : (int)$original_quiz['passing_score'];
                    $insert_query = "INSERT INTO quizzes (class_id, title, description, created_by, time_limit, passing_score, show_leaderboard, max_attempts, available_until, status, scheduled_at, is_active) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    if ($insert_stmt === false) {
                        $insert_query = "INSERT INTO quizzes (class_id, title, description, created_by, time_limit, passing_score, show_leaderboard, max_attempts, available_until, is_active) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_query);
                    }
                    
                    if (!$insert_stmt) {
                        $error_message = 'Database error: ' . $conn->error;
                    } else {
                        $is_active = $status === 'published' ? 1 : 0;
                        if (strpos($insert_query, 'status') !== false) {
                            $insert_stmt->bind_param("issiiiiisssi", 
                                $class_id, 
                                $new_title, 
                                $original_quiz['description'], 
                                $user_id, 
                                $new_time_limit, 
                                $final_passing_score, 
                                $original_quiz['show_leaderboard'], 
                                $new_max_attempts, 
                                $new_available_until,
                                $status,
                                $scheduled_at,
                                $is_active
                            );
                        } else {
                            $insert_stmt->bind_param("issiiiiisi", 
                                $class_id, 
                                $new_title, 
                                $original_quiz['description'], 
                                $user_id, 
                                $new_time_limit, 
                                $final_passing_score, 
                                $original_quiz['show_leaderboard'], 
                                $new_max_attempts, 
                                $new_available_until,
                                $is_active
                            );
                        }
                        
                        if ($insert_stmt->execute()) {
                            $new_quiz_id = $insert_stmt->insert_id;
                            
                            // Step 1: Get all original questions
                            $get_q_query = "SELECT id, question_text, question_type, points, question_image FROM questions WHERE quiz_id = ?";
                            $get_q_stmt = $conn->prepare($get_q_query);
                            
                            if (!$get_q_stmt) {
                                $error_message = 'Database error in Step 1: ' . $conn->error;
                            } else {
                                $get_q_stmt->bind_param("i", $quiz_id);
                                $get_q_stmt->execute();
                                $orig_questions = $get_q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                
                                // Step 2: Map old question IDs to new
                                $question_map = [];
                                foreach ($orig_questions as $orig_q) {
                                    $insert_q = "INSERT INTO questions (quiz_id, question_text, question_type, points, question_image) VALUES (?, ?, ?, ?, ?)";
                                    $insert_q_stmt = $conn->prepare($insert_q);
                                    
                                    if (!$insert_q_stmt) {
                                        $error_message = 'Database error in Step 2: ' . $conn->error;
                                        break;
                                    }
                                    
                                    $insert_q_stmt->bind_param("issis", 
                                        $new_quiz_id, 
                                        $orig_q['question_text'], 
                                        $orig_q['question_type'], 
                                        $orig_q['points'], 
                                        $orig_q['question_image']
                                    );
                                    
                                    if ($insert_q_stmt->execute()) {
                                        $new_q_id = $insert_q_stmt->insert_id;
                                        $question_map[$orig_q['id']] = $new_q_id;
                                    }
                                }
                                
                                if (!$error_message) {
                                    // Step 3: Copy all answers using the mapping
                                    foreach ($question_map as $old_q_id => $new_q_id) {
                                        $get_a_query = "SELECT answer_text, is_correct FROM answers WHERE question_id = ?";
                                        $get_a_stmt = $conn->prepare($get_a_query);
                                        
                                        if ($get_a_stmt) {
                                            $get_a_stmt->bind_param("i", $old_q_id);
                                            $get_a_stmt->execute();
                                            $answers = $get_a_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                            
                                            foreach ($answers as $answer) {
                                                $insert_a = "INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                                                $insert_a_stmt = $conn->prepare($insert_a);
                                                
                                                if ($insert_a_stmt) {
                                                    $insert_a_stmt->bind_param("isi", 
                                                        $new_q_id, 
                                                        $answer['answer_text'], 
                                                        $answer['is_correct']
                                                    );
                                                    $insert_a_stmt->execute();
                                                }
                                            }
                                        }
                                    }
                                    
                                    if ($redirect_to_edit || !$publish_now) {
                                        header('Location: edit_quiz.php?id=' . $new_quiz_id . '&copied=1');
                                        exit();
                                    }
                                    header('Location: manage_quizzes.php?class_id=' . $class_id . '&success=1');
                                    exit();
                                }
                            }
                        } else {
                            $error_message = 'Kļūda veidojot jaunā testa ierakstu: ' . $insert_stmt->error;
                        }
                    }
                } else {
                    $error_message = 'Oriģinālais tests nav atrasts.';
                }
            }
        }
    }
}

$quizzes = QuizController::get_class_quizzes($class_id, true);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testi - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Testi - <?php echo htmlspecialchars($class['name']); ?></div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header" style="margin-bottom: 1.5rem;">
            <h1 class="page-title">Testi</h1>
            <p class="page-subtitle">Pārvaldi testus klasei <?php echo htmlspecialchars($class['name']); ?>.</p>
        </div>
        <?php if ($error_message): ?>
            <div class="card" style="margin-bottom: 1.5rem; border-color: rgba(239, 68, 68, 0.4);">
                <p class="text-muted" style="margin: 0; color: #dc2626;"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <div class="flex gap-2" style="margin-bottom: 2rem;">
            <button class="btn" onclick="openModal('createQuizModal')">+ Izveidot testu</button>
        </div>
        
        <div class="quiz-grid">
            <?php if (empty($quizzes)): ?>
                <div class="card" style="grid-column: 1 / -1;">
                    <p class="text-muted" style="margin: 0;">Šai klasei vēl nav izveidoti testi.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($quizzes as $quiz): ?>
                <?php
                    $status = $quiz['status'] ?? (!empty($quiz['is_active']) ? 'published' : 'draft');
                    $scheduled_at = $quiz['scheduled_at'] ?? null;
                    $status_labels = [
                        'draft' => 'Melnraksts',
                        'published' => 'Publicēts'
                    ];
                    $status_label = $status_labels[$status] ?? 'Melnraksts';
                    $available_until_input = !empty($quiz['available_until'])
                        ? substr(str_replace(' ', 'T', $quiz['available_until']), 0, 16)
                        : '';
                ?>
                <div class="quiz-tile">
                    <div class="quiz-tile__top">
                        <div>
                            <h3 class="quiz-tile__title"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                            <div class="quiz-tile__subtitle">
                                <?php echo htmlspecialchars($quiz['description'] !== '' ? substr($quiz['description'], 0, 120) : 'Nav apraksta'); ?>
                            </div>
                        </div>
                        <div class="quiz-tile__badges">
                            <span class="pill"><?php echo (int)($quiz['question_count'] ?? 0); ?> jaut.</span>
                            <?php if ($status === 'published'): ?>
                                <span class="pill pill-success"><?php echo $status_label; ?></span>
                            <?php else: ?>
                                <span class="pill pill-muted"><?php echo $status_label; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="quiz-tile__meta">
                        <span class="meta-chip">⏱️ <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'Nav limita'; ?></span>
                        <a class="meta-chip meta-chip-link" href="quiz_attempts.php?class_id=<?php echo $class_id; ?>&quiz_id=<?php echo $quiz['id']; ?>">👥 <?php echo (int)($quiz['attempts'] ?? 0); ?> mēģ.</a>
                        <span class="meta-chip">🎯 <?php echo (int)($quiz['passing_score'] ?? 60); ?>%</span>
                        
                    </div>
                    <div class="quiz-tile__footer">
                        <div class="quiz-tile__actions">
                            <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-secondary">Rediģēt</a>
                            <button class="btn btn-secondary" onclick="openRepeatModal(
                                <?php echo (int)$quiz['id']; ?>,
                                '<?php echo htmlspecialchars($quiz['title']); ?>',
                                <?php echo (int)($quiz['time_limit'] ?? 0); ?>,
                                <?php echo (int)($quiz['max_attempts'] ?? 0); ?>,
                                <?php echo (int)($quiz['passing_score'] ?? 60); ?>,
                                '<?php echo $available_until_input; ?>'
                            )">↻ Atkārtot</button>
                            <form method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>">
                                <input type="hidden" name="action" value="copy_quiz">
                                <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                <button type="submit" class="btn btn-ghost">📄 Kopēt</button>
                            </form>
                            <?php if ($status !== 'published'): ?>
                                <form method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>">
                                    <input type="hidden" name="action" value="set_quiz_status">
                                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                    <input type="hidden" name="status" value="published">
                                    <button type="submit" class="btn btn-success btn-small" title="Publicēt skolēniem">Publicēt</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>">
                                    <input type="hidden" name="action" value="set_quiz_status">
                                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                    <input type="hidden" name="status" value="draft">
                                    <button type="submit" class="btn btn-ghost btn-small" title="Pārvērst par melnrakstu">Melnraksts</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>" onsubmit="return confirm('Dzēst šo testu? Šo darbību nevar atsaukt.');">
                                <input type="hidden" name="action" value="delete_quiz">
                                <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-small">Dzēst</button>
                            </form>
                        </div>
                        <div class="quiz-tile__dates">
                            <span>📣 Publicēts: <?php echo $quiz['created_at'] ? date('d.m.Y H:i', strtotime($quiz['created_at'])) : '—'; ?></span>
                            <span>📅 Termiņš: <?php echo $quiz['available_until'] ? date('d.m.Y H:i', strtotime($quiz['available_until'])) : 'Nav'; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Create Quiz Modal -->
    <div id="createQuizModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">Izveidot jaunu testu</h2>
                <button type="button" class="modal-close" onclick="closeModal('createQuizModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createQuizForm" method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="action" value="create_quiz">
                    
                    <div class="form-group">
                        <label class="form-label" for="quiz_title">Testa nosaukums</label>
                        <input class="form-input" type="text" id="quiz_title" name="title" required placeholder="Testa nosaukums">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="quiz_description">Apraksts</label>
                        <textarea class="form-textarea" id="quiz_description" name="description" rows="3" placeholder="Testa apraksts"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="time_limit">Laika limits (min)</label>
                            <input class="form-input" type="number" id="time_limit" name="time_limit" value="0" min="0" placeholder="0 = nav limita">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="passing_score">Nokārtošanas slieksnis (%)</label>
                            <input class="form-input" type="number" id="passing_score" name="passing_score" value="60" min="0" max="100">
                        </div>
                    </div>

                    <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; margin-top: 1rem;">
                        <h4 style="margin-bottom: 1rem; color: rgba(255,255,255,0.8);">Papildu opcijas</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Mēģinājumi</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.75rem; background: rgba(255,255,255,0.05); border-radius: 6px;">
                                    <input type="radio" name="attempts_type" value="unlimited" checked onchange="toggleAttemptsInput()">
                                    <span>♾️ Bez ierobežojuma</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.75rem; background: rgba(255,255,255,0.05); border-radius: 6px;">
                                    <input type="radio" name="attempts_type" value="limited" onchange="toggleAttemptsInput()">
                                    <span>🔢 Ierobežots</span>
                                </label>
                            </div>
                            <input class="form-input" type="number" id="max_attempts" name="max_attempts" value="" min="1" placeholder="Mēģinājumu skaits" style="display: none; margin-top: 0.5rem;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="available_until">📅 Pieejams līdz (opcijas)</label>
                            <input class="form-input" type="datetime-local" id="available_until" name="available_until" placeholder="Atstāt tukšu - pieejams beztermiņa">
                        </div>
                        <div class="form-group" style="margin-top: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="publish_now" value="1">
                                <span>Publicēt uzreiz</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createQuizModal')">Atcelt</button>
                <button type="submit" form="createQuizForm" class="btn">Izveidot testu</button>
            </div>
        </div>
    </div>
    
    <!-- Duplicate Quiz Modal -->
    <div id="duplicateQuizModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">📋 Kopēt testu</h2>
                <button type="button" class="modal-close" onclick="closeModal('duplicateQuizModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="duplicateQuizForm" method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>">
                    <input type="hidden" name="action" value="duplicate_quiz">
                    <input type="hidden" name="quiz_id" id="duplicate_quiz_id">
                    <input type="hidden" name="redirect_to_edit" id="redirect_to_edit" value="0">
                    <input type="hidden" name="publish_now" id="duplicate_publish_now" value="0">
                    
                    <div class="form-group">
                        <label class="form-label" for="new_title">Jaunais nosaukums</label>
                        <input class="form-input" type="text" id="new_title" name="new_title" required placeholder="Ievadiet jaunu nosaukumu">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="new_time_limit">Laika limits (min)</label>
                            <input class="form-input" type="number" id="new_time_limit" name="new_time_limit" value="0" min="0" placeholder="0 = nav limita">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="new_max_attempts">Maksimālie mēģinājumi</label>
                            <input class="form-input" type="number" id="new_max_attempts" name="new_max_attempts" value="0" min="0" placeholder="0 = bez ierobežojuma">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="new_passing_score">Nokārtošanas slieksnis (%)</label>
                            <input class="form-input" type="number" id="new_passing_score" name="new_passing_score" value="" min="0" max="100" placeholder="Atstāt kā oriģinālajam">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="new_available_until">Pieejams līdz (opcionals)</label>
                        <input class="form-input" type="datetime-local" id="new_available_until" name="new_available_until">
                    </div>
                    <p class="text-muted" style="margin-top: -0.5rem;">Kopija tiek izveidota ar jaunajiem datiem. Vari to publicēt uzreiz vai saglabāt melnrakstā.</p>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('duplicateQuizModal')">Atcelt</button>
                <button type="button" class="btn btn-secondary" onclick="submitDuplicate('draft')">📄 Saglabāt kā melnrakstu</button>
                <button type="button" class="btn" onclick="submitDuplicate('publish')">✓ Publicēt kopiju</button>
            </div>
        </div>
    </div>

    <!-- Repeat Quiz Modal (publish original with edits) -->
    <div id="repeatQuizModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">↻ Atkārtot</h2>
                <button type="button" class="modal-close" onclick="closeModal('repeatQuizModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="repeatQuizForm" method="POST" action="manage_quizzes.php?class_id=<?php echo $class_id; ?>">
                    <input type="hidden" name="action" value="repeat_quiz">
                    <input type="hidden" name="quiz_id" id="repeat_quiz_id">
                    <input type="hidden" name="current_max_attempts" id="repeat_current_max_attempts" value="0">

                    <div class="form-group">
                        <label class="form-label">Nosūtīt</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.65rem; background: rgba(255,255,255,0.05); border-radius: 6px;">
                                <input type="radio" name="repeat_scope" value="class" checked onchange="toggleRepeatScope()">
                                <span>Visam kursam</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.65rem; background: rgba(255,255,255,0.05); border-radius: 6px;">
                                <input type="radio" name="repeat_scope" value="students" onchange="toggleRepeatScope()">
                                <span>Atlasīti skolēni</span>
                            </label>
                        </div>
                        <div id="repeat_students_panel" style="margin-top: 0.75rem; display: none;">
                            <input class="form-input" type="text" id="repeat_students_search" placeholder="Meklēt skolēnu...">
                            <div id="repeat_students_list" style="margin-top: 0.6rem; max-height: 180px; overflow: auto; padding: 0.5rem; border-radius: 0.6rem; border: 1px solid var(--border-color); background: var(--bg-secondary);">
                                <?php if (empty($students)): ?>
                                    <div class="text-muted">Nav skolēnu šajā kursā.</div>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <label class="repeat-student-row" data-student-name="<?php echo htmlspecialchars(strtolower(trim($student['first_name'] . ' ' . $student['last_name']))); ?>" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.25rem; cursor: pointer;">
                                            <input type="checkbox" name="repeat_student_ids[]" value="<?php echo (int)$student['id']; ?>" disabled>
                                            <span><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name'])); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="repeat_title">Testa nosaukums</label>
                        <input class="form-input" type="text" id="repeat_title" name="title" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="repeat_time_limit">Laika limits (min)</label>
                            <input class="form-input" type="number" id="repeat_time_limit" name="time_limit" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="repeat_max_attempts_add">Papildu mēģinājumi</label>
                            <input class="form-input" type="number" id="repeat_max_attempts_add" name="max_attempts_add" value="0" min="0" placeholder="Cik pielikt klāt">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="repeat_passing_score">Nokārtošanas slieksnis (%)</label>
                            <input class="form-input" type="number" id="repeat_passing_score" name="passing_score" value="60" min="0" max="100">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="repeat_available_until">Pieejams līdz (opcionals)</label>
                        <input class="form-input" type="datetime-local" id="repeat_available_until" name="available_until">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('repeatQuizModal')">Atcelt</button>
                <button type="button" class="btn" onclick="submitRepeat()">✓ Publicēt</button>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        
        function openDuplicateModal(quizId, quizTitle, passingScore, mode) {
            document.getElementById('duplicate_quiz_id').value = quizId;
            document.getElementById('new_title').value = quizTitle + ' (kopija)';
            document.getElementById('new_passing_score').value = (passingScore !== undefined && passingScore !== null) ? passingScore : '';
            document.getElementById('redirect_to_edit').value = mode === 'draft' ? '1' : '0';
            document.getElementById('duplicate_publish_now').value = mode === 'publish' ? '1' : '0';
            openModal('duplicateQuizModal');
        }

        function openRepeatModal(quizId, title, timeLimit, maxAttempts, passingScore, availableUntil) {
            document.getElementById('repeat_quiz_id').value = quizId;
            document.getElementById('repeat_title').value = title || '';
            document.getElementById('repeat_time_limit').value = timeLimit || 0;
            document.getElementById('repeat_current_max_attempts').value = maxAttempts || 0;
            document.getElementById('repeat_max_attempts_add').value = 0;
            document.getElementById('repeat_passing_score').value = passingScore || 60;
            document.getElementById('repeat_available_until').value = availableUntil || '';
            const scopeClass = document.querySelector('input[name="repeat_scope"][value="class"]');
            if (scopeClass) {
                scopeClass.checked = true;
            }
            toggleRepeatScope();
            openModal('repeatQuizModal');
        }

        function toggleRepeatScope() {
            const isStudents = document.querySelector('input[name="repeat_scope"][value="students"]')?.checked;
            const panel = document.getElementById('repeat_students_panel');
            const studentList = document.getElementById('repeat_students_list');
            if (panel) {
                panel.style.display = isStudents ? 'block' : 'none';
            }
            if (studentList) {
                studentList.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.disabled = !isStudents;
                    if (!isStudents) {
                        cb.checked = false;
                    }
                });
                studentList.querySelectorAll('.repeat-student-row').forEach(row => {
                    row.style.display = '';
                });
            }
        }

        const repeatSearch = document.getElementById('repeat_students_search');
        if (repeatSearch) {
            repeatSearch.addEventListener('input', function() {
                const query = repeatSearch.value.trim().toLowerCase();
                const rows = document.querySelectorAll('.repeat-student-row');
                rows.forEach(row => {
                    const name = row.getAttribute('data-student-name') || '';
                    row.style.display = name.includes(query) ? '' : 'none';
                });
            });
        }

        function submitRepeat() {
            document.getElementById('repeatQuizForm').submit();
        }

        function submitDuplicate(mode) {
            document.getElementById('redirect_to_edit').value = mode === 'draft' ? '1' : '0';
            document.getElementById('duplicate_publish_now').value = mode === 'publish' ? '1' : '0';
            document.getElementById('duplicateQuizForm').submit();
        }

        function toggleAttemptsInput() {
            const type = document.querySelector('input[name="attempts_type"]:checked').value;
            const input = document.getElementById('max_attempts');
            
            if (type === 'limited') {
                input.style.display = '';
                input.required = true;
                input.value = '3';
            } else {
                input.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.classList.remove('active');
                }
            };

            const form = document.getElementById('createQuizForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const attemptsType = document.querySelector('input[name="attempts_type"]:checked').value;
                    if (attemptsType === 'limited') {
                        const maxAttempts = parseInt(document.getElementById('max_attempts').value);
                        if (!maxAttempts || maxAttempts < 1) {
                            alert('Lūdzu, norādiet mēģinājumu skaitu!');
                            e.preventDefault();
                            return;
                        }
                    }
                });
            }

        });
    </script>
</body>
</html>
