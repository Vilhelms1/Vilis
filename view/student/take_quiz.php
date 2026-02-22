<?php
require_once __DIR__ . '/../../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../../controlers__(loģistika)/autenController.php';
require_once __DIR__ . '/../../controlers__(loģistika)/QuizController.php';

if (!AuthController::is_logged_in()) {
    header('Location: ../login-reg.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = $_GET['quiz_id'] ?? 0;

// Get quiz
$quiz = QuizController::get_quiz($quiz_id);

if (!$quiz) {
    header('Location: student_dashboard.php');
    exit();
}

// Verify student is enrolled in the class
$enroll_check = "SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?";
$enroll_stmt = $conn->prepare($enroll_check);
$enroll_stmt->bind_param("ii", $quiz['class_id'], $user_id);
$enroll_stmt->execute();
if ($enroll_stmt->get_result()->num_rows === 0) {
    header('Location: student_dashboard.php');
    exit();
}

// Pārbaudīt, vai testu vēl var kārtot (termiņš)
$error_message = null;
if ($quiz['available_until']) {
    $available_until = strtotime($quiz['available_until']);
    $now = time();
    if ($now > $available_until) {
        $error_message = 'Šis tests vairs nav pieejams. Termiņš ir beidzies.';
    }
}

// Pārbaudīt mēģinājumu skaitu
if (!$error_message) {
    $extra_attempts = 0;
    $extra_stmt = $conn->prepare("SELECT extra_attempts FROM quiz_attempt_overrides WHERE quiz_id = ? AND user_id = ?");
    if ($extra_stmt) {
        $extra_stmt->bind_param("ii", $quiz_id, $user_id);
        $extra_stmt->execute();
        $extra_row = $extra_stmt->get_result()->fetch_assoc();
        $extra_attempts = (int)($extra_row['extra_attempts'] ?? 0);
    }
    $max_attempts_total = (int)$quiz['max_attempts'] + $extra_attempts;
}

if (!$error_message && $max_attempts_total > 0) {
    $attempts_query = "SELECT COUNT(*) as attempts FROM quiz_results WHERE quiz_id = ? AND user_id = ?";
    $attempts_stmt = $conn->prepare($attempts_query);
    $attempts_stmt->bind_param("ii", $quiz_id, $user_id);
    $attempts_stmt->execute();
    $attempts_result = $attempts_stmt->get_result()->fetch_assoc();
    
    if ($attempts_result['attempts'] >= $max_attempts_total) {
        $error_message = 'Jūs esat pārsniegis maksimālo mēģinājumu skaitu (' . $max_attempts_total . '). Vairāk šo testu nevar kārtot.';
    }
}

if ($error_message) {
    ?>
    <!DOCTYPE html>
    <html lang="lv">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Kļūda - ApgūstiVairāk</title>
        <link rel="stylesheet" href="../../assets/css/modern-style.css">
    </head>
    <body data-theme="light" data-lang="lv">
        <div class="container" style="margin-top: 3rem;">
            <div class="card" style="text-align: center; border-color: rgba(239, 68, 68, 0.4);">
                <h2 style="color: #dc2626; margin-top: 0;">⛔ Piekļuve liegta</h2>
                <p style="font-size: 16px; color: #dc2626; margin: 1rem 0;"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="student_dashboard.php" class="btn btn-secondary" style="margin-top: 1rem;">← Atpakaļ uz Mājas lapu</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Shuffle questions
$questions = $quiz['questions'];
shuffle($questions);
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - ApgūstiVairāk</title>
    <link rel="stylesheet" href="../../assets/css/modern-style.css">
    <script defer src="../../assets/js/app.js"></script>
    <style>
        .quiz-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .quiz-header {
            margin-bottom: 2rem;
        }

        .quiz-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .quiz-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            padding: 1.5rem;
            border-radius: 0.75rem;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .timer-box {
            padding: 2rem;
            border-radius: 1rem;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            border: 2px solid var(--danger);
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.1);
        }

        .timer-label {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .timer-display {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--danger);
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.1em;
        }

        .questions-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .question-wrapper {
            padding: 2rem;
            border-radius: 0.75rem;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            animation: slideUp 0.5s ease forwards;
            opacity: 0;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .question-wrapper:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .question-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .question-number {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .question-points {
            font-size: 0.85rem;
            color: var(--text-secondary);
            background: rgba(99, 102, 241, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
        }

        .question-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .question-image {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            max-height: 300px;
            object-fit: cover;
        }

        .answers-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .answer-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 0.5rem;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .answer-option:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .answer-option input[type="radio"] {
            width: 20px;
            height: 20px;
            margin-right: 1rem;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .answer-option input[type="radio"]:checked + span {
            color: var(--primary);
            font-weight: 700;
        }

        .answer-option input[type="radio"]:checked {
            accent-color: var(--primary);
        }

        .quiz-navigation {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 0.875rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            min-width: 200px;
            font-size: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Animation for question cards */
        .question-wrapper:nth-child(1) { animation-delay: 0.1s; }
        .question-wrapper:nth-child(2) { animation-delay: 0.2s; }
        .question-wrapper:nth-child(3) { animation-delay: 0.3s; }
        .question-wrapper:nth-child(4) { animation-delay: 0.4s; }
        .question-wrapper:nth-child(5) { animation-delay: 0.5s; }
        .question-wrapper:nth-child(n+6) { animation-delay: 0.6s; }
    </style>
</head>
<body class="quiz-page" data-theme="light" data-lang="lv">
    <div class="quiz-container">
        <div class="quiz-header">
            <h1 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
            
            <div class="quiz-stats">
                <?php if ($quiz['time_limit'] > 0): ?>
                    <div class="stat-box">
                        <div class="stat-label">⏱️ Laika limits</div>
                        <div class="stat-value"><?php echo $quiz['time_limit']; ?> min</div>
                    </div>
                <?php endif; ?>
                
                <?php if ($quiz['max_attempts'] > 0): ?>
                    <div class="stat-box">
                        <div class="stat-label">🔢 Mēģinājumi</div>
                        <div class="stat-value">
                            <?php 
                                $attempts_query = "SELECT COUNT(*) as attempts FROM quiz_results WHERE quiz_id = ? AND user_id = ?";
                                $attempts_stmt = $conn->prepare($attempts_query);
                                $attempts_stmt->bind_param("ii", $quiz_id, $user_id);
                                $attempts_stmt->execute();
                                $attempts_result = $attempts_stmt->get_result()->fetch_assoc();
                                echo ($quiz['max_attempts'] - $attempts_result['attempts']) . '/' . $quiz['max_attempts'];
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($quiz['available_until']): ?>
                    <div class="stat-box">
                        <div class="stat-label">📅 Pieejams līdz</div>
                        <div class="stat-value" style="font-size: 1rem;"><?php echo date('d.m.Y H:i', strtotime($quiz['available_until'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($quiz['time_limit'] > 0): ?>
                <div class="timer-box" id="timerBox">
                    <div class="timer-label">⏳ Atlikušais laiks</div>
                    <div class="timer-display">
                        <span id="minutes"><?php echo $quiz['time_limit']; ?></span>:<span id="seconds">00</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <form id="quizForm" method="POST" action="submit_quiz.php">
            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
            <input type="hidden" name="time_taken" id="timeTaken">

            <div class="questions-container">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-wrapper">
                        <div class="question-header">
                            <div class="question-number">Jautājums <?php echo ($index + 1); ?></div>
                            <div class="question-points"><?php echo $question['points']; ?> p.</div>
                        </div>
                        
                        <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                        
                        <?php if (!empty($question['question_image'])): ?>
                            <img class="question-image" src="../../<?php echo htmlspecialchars($question['question_image']); ?>" alt="Jautājuma attēls">
                        <?php endif; ?>

                        <div class="answers-group">
                            <?php foreach ($question['answers'] as $answer): ?>
                                <label class="answer-option">
                                    <input type="radio" name="answer_<?php echo $question['id']; ?>" value="<?php echo $answer['id']; ?>" required>
                                    <span><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="quiz-navigation">
                <button type="submit" class="btn btn-primary">✓ Iesniegties</button>
            </div>
        </form>
    </div>
    
    <script>
        const startTime = Date.now();

        // Timer with visual feedback
        <?php if ($quiz['time_limit'] > 0): ?>
        let timeLeft = <?php echo $quiz['time_limit'] * 60; ?>;
        const timerBox = document.getElementById('timerBox');
        
        const timerInterval = setInterval(() => {
            if (timeLeft <= 0) {
                document.getElementById('quizForm').submit();
                return;
            }
            
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('minutes').textContent = minutes;
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
            
            // Color change when time is running low
            if (timeLeft <= 60) {
                timerBox.style.background = 'linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(239, 68, 68, 0.1) 100%)';
                timerBox.style.animation = 'pulse 1s infinite';
            }
        }, 1000);
        
        // Add pulse animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.8; }
            }
        `;
        document.head.appendChild(style);
        <?php endif; ?>

        // Form submission
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Calculate time taken
            const timeTaken = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById('timeTaken').value = timeTaken;

            // Submit form
            this.submit();
        });
    </script>
</body>
</html>
