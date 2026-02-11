<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in() || (!AuthController::is_admin() && !AuthController::is_teacher())) {
    header('Location: ../login-reg.php');
    exit();
}

$quiz_id = $_GET['id'] ?? 0;
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

    if ($action === 'add_question') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $question_type = $_POST['question_type'] ?? 'multiple_choice';
        $points = (int)($_POST['points'] ?? 1);
        $answers = json_decode($_POST['answers'] ?? '[]', true);

        if ($quiz_id <= 0 || $question_text === '' || !is_array($answers) || count($answers) < 2) {
            echo json_encode(['success' => false, 'message' => 'Question and at least two answers are required']);
            exit();
        }

        $verify_query = $is_admin
            ? "SELECT id FROM quizzes WHERE id = ?"
            : "SELECT id FROM quizzes WHERE id = ? AND created_by = ?";
        $verify_stmt = $conn->prepare($verify_query);
        if ($is_admin) {
            $verify_stmt->bind_param("i", $quiz_id);
        } else {
            $verify_stmt->bind_param("ii", $quiz_id, $user_id);
        }
        $verify_stmt->execute();

        if ($verify_stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Quiz not found']);
            exit();
        }

        $has_correct = false;
        foreach ($answers as $answer) {
            if (!empty($answer['correct'])) {
                $has_correct = true;
                break;
            }
        }

        if (!$has_correct) {
            echo json_encode(['success' => false, 'message' => 'Mark at least one correct answer']);
            exit();
        }

        $result = QuizController::add_question($quiz_id, $question_text, $question_type, $points);
        if (!$result['success']) {
            echo json_encode($result);
            exit();
        }

        $question_id = (int)$result['question_id'];
        foreach ($answers as $answer) {
            $text = trim($answer['text'] ?? '');
            $correct = !empty($answer['correct']) ? 1 : 0;
            if ($text !== '') {
                QuizController::add_answer($question_id, $text, $correct);
            }
        }

        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'delete_question') {
        $question_id = (int)($_POST['question_id'] ?? ($payload['question_id'] ?? 0));

        if ($question_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid question id']);
            exit();
        }

        $delete_query = $is_admin
            ? "DELETE q FROM questions q INNER JOIN quizzes z ON q.quiz_id = z.id WHERE q.id = ?"
            : "DELETE q FROM questions q INNER JOIN quizzes z ON q.quiz_id = z.id WHERE q.id = ? AND z.created_by = ?";
        $delete_stmt = $conn->prepare($delete_query);
        if ($is_admin) {
            $delete_stmt->bind_param("i", $question_id);
        } else {
            $delete_stmt->bind_param("ii", $question_id, $user_id);
        }
        $delete_stmt->execute();

        if ($delete_stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Question not found']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

// Verify admin/teacher owns this quiz
$verify_query = $is_admin
    ? "SELECT q.* FROM quizzes q WHERE q.id = ?"
    : "SELECT q.* FROM quizzes q WHERE q.id = ? AND q.created_by = ?";
$verify_stmt = $conn->prepare($verify_query);
if ($is_admin) {
    $verify_stmt->bind_param("i", $quiz_id);
} else {
    $verify_stmt->bind_param("ii", $quiz_id, $user_id);
}
$verify_stmt->execute();
if ($verify_stmt->get_result()->num_rows === 0) {
    header('Location: ' . ($is_admin ? 'admin_dashboard.php' : '../teacher/teacher_dashboard.php'));
    exit();
}

$quiz = QuizController::get_quiz($quiz_id);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testa rediģēšana - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="manage_quizzes.php?class_id=<?php echo $quiz['class_id']; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Testa rediģēšana</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="../process_logout.php" class="btn btn-small btn-secondary">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="quiz-editor">
            <h1 class="page-title" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($quiz['title']); ?></h1>
            
            <button class="btn" onclick="openModal('addQuestionModal')">+ Pievienot jautājumu</button>
            
            <div class="questions-list" style="margin-top: 1.5rem;">
                <?php foreach ($quiz['questions'] as $index => $question): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <h3>Jautājums <?php echo $index + 1; ?></h3>
                            <span class="badge"><?php echo $question['points']; ?> pts</span>
                        </div>
                        <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                        
                        <div class="answers-list">
                            <?php foreach ($question['answers'] as $answer): ?>
                                <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : ''; ?>">
                                    <span><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                    <?php if ($answer['is_correct']): ?>
                                        <span class="badge-success">✓ Pareizi</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button class="btn btn-danger btn-small" onclick="deleteQuestion(<?php echo $question['id']; ?>)">Dzēst</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Question Modal -->
    <div id="addQuestionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="display: flex; align-items: center; gap: 0.75rem;">
                <h2 style="margin: 0;">Pievienot jautājumu</h2>
                <button type="button" class="modal-close" onclick="closeModal('addQuestionModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="questionForm" method="POST" action="edit_quiz.php?id=<?php echo $quiz_id; ?>">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="question_text">Jautājums</label>
                        <textarea class="form-textarea" id="question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="question_type">Tips</label>
                            <select class="form-select" id="question_type" name="question_type">
                                <option value="multiple_choice">Vairākas atbildes</option>
                                <option value="true_false">Patiess/Aplams</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="points">Punkti</label>
                            <input class="form-input" type="number" id="points" name="points" value="1" min="1">
                        </div>
                    </div>
                    
                    <div id="answersContainer">
                        <h4 style="margin-bottom: 0.5rem;">Atbildes</h4>
                        <div class="answer-input">
                            <input type="text" class="answer-text" placeholder="Atbildes variants 1" required>
                            <input type="checkbox" class="answer-correct" title="Pareiza atbilde">
                            <button type="button" class="btn btn-small" onclick="removeAnswerInput(this)">Noņemt</button>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="addAnswerInput()">+ Pievienot atbildi</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addQuestionModal')">Atcelt</button>
                <button type="submit" form="questionForm" class="btn">Saglabāt</button>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        
        function addAnswerInput() {
            const div = document.createElement('div');
            div.className = 'answer-input';
            div.innerHTML = `
                <input type="text" class="answer-text" placeholder="Atbildes variants" required>
                <input type="checkbox" class="answer-correct" title="Pareiza atbilde">
                <button type="button" class="btn btn-small" onclick="removeAnswerInput(this)">Noņemt</button>
            `;
            document.getElementById('answersContainer').appendChild(div);
        }
        
        function removeAnswerInput(btn) {
            btn.parentElement.remove();
        }
        
        function deleteQuestion(questionId) {
            if (confirm('Dzēst šo jautājumu?')) {
                fetch('edit_quiz.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_question', question_id: questionId})
                }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
            }
        }
        
        // Handle question form submission
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const answers = [];
            
            document.querySelectorAll('.answer-input').forEach(input => {
                answers.push({
                    text: input.querySelector('.answer-text').value,
                    correct: input.querySelector('.answer-correct').checked ? 1 : 0
                });
            });
            
            formData.append('answers', JSON.stringify(answers));
            formData.append('action', 'add_question');
            
            fetch('edit_quiz.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Kļūda');
                }
            });
        });
    </script>
</body>
</html>
