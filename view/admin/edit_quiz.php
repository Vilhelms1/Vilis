<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/QuizController.php';

if (!AuthController::is_logged_in() || !AuthController::is_admin()) {
    header('Location: login.php');
    exit();
}

$quiz_id = $_GET['id'] ?? 0;
$admin_id = $_SESSION['user_id'];

// Verify admin owns this quiz
$verify_query = "SELECT q.* FROM quizzes q WHERE q.id = ? AND q.admin_id = ?";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $quiz_id, $admin_id);
$verify_stmt->execute();
if ($verify_stmt->get_result()->num_rows === 0) {
    header('Location: admin_dashboard.php');
    exit();
}

$quiz = QuizController::get_quiz($quiz_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="manage_quizzes.php?class_id=<?php echo $quiz['class_id']; ?>" class="nav-back">← Back</a>
            <div class="nav-brand">Edit Quiz</div>
            <a href="process_logout.php" class="btn btn-small">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="quiz-editor">
            <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
            
            <button class="btn btn-primary" onclick="openModal('addQuestionModal')">+ Add Question</button>
            
            <div class="questions-list">
                <?php foreach ($quiz['questions'] as $index => $question): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <h3>Question <?php echo $index + 1; ?></h3>
                            <span class="badge"><?php echo $question['points']; ?> pts</span>
                        </div>
                        <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                        
                        <div class="answers-list">
                            <?php foreach ($question['answers'] as $answer): ?>
                                <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : ''; ?>">
                                    <span><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                    <?php if ($answer['is_correct']): ?>
                                        <span class="badge-success">✓ Correct</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button class="btn btn-danger btn-small" onclick="deleteQuestion(<?php echo $question['id']; ?>)">Delete Question</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Question Modal -->
    <div id="addQuestionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addQuestionModal')">&times;</span>
            <h2>Add Question</h2>
            <form id="questionForm" method="POST" action="api/add_question.php">
                <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                
                <div class="form-group">
                    <label for="question_text">Question</label>
                    <textarea id="question_text" name="question_text" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="question_type">Type</label>
                        <select id="question_type" name="question_type">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="points">Points</label>
                        <input type="number" id="points" name="points" value="1" min="1">
                    </div>
                </div>
                
                <div id="answersContainer">
                    <h4>Answers</h4>
                    <div class="answer-input">
                        <input type="text" class="answer-text" placeholder="Answer option 1" required>
                        <input type="checkbox" class="answer-correct" title="Mark as correct answer">
                        <button type="button" class="btn btn-small" onclick="removeAnswerInput(this)">Remove</button>
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="addAnswerInput()">+ Add Answer Option</button>
                <button type="submit" class="btn btn-primary">Save Question</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(modalId) { document.getElementById(modalId).style.display = 'block'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        function addAnswerInput() {
            const div = document.createElement('div');
            div.className = 'answer-input';
            div.innerHTML = `
                <input type="text" class="answer-text" placeholder="Answer option" required>
                <input type="checkbox" class="answer-correct" title="Mark as correct answer">
                <button type="button" class="btn btn-small" onclick="removeAnswerInput(this)">Remove</button>
            `;
            document.getElementById('answersContainer').appendChild(div);
        }
        
        function removeAnswerInput(btn) {
            btn.parentElement.remove();
        }
        
        function deleteQuestion(questionId) {
            if (confirm('Delete this question?')) {
                fetch('api/delete_question.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({question_id: questionId})
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
            
            fetch('api/add_question.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Error adding question');
                }
            });
        });
    </script>
</body>
</html>
