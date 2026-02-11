<?php
require_once __DIR__ . '/../configs__(iestatījumi)/database.php';

class QuizController {
    
    // Create quiz
    public static function create_quiz($class_id, $title, $description, $created_by, $time_limit, $passing_score, $show_leaderboard) {
        global $conn;
        
        $query = "INSERT INTO quizzes (class_id, title, description, created_by, time_limit, passing_score, show_leaderboard) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issiiii", $class_id, $title, $description, $created_by, $time_limit, $passing_score, $show_leaderboard);
        
        if ($stmt->execute()) {
            return ['success' => true, 'quiz_id' => $stmt->insert_id];
        }
        return ['success' => false, 'message' => 'Failed to create quiz'];
    }
    
    // Add question
    public static function add_question($quiz_id, $question_text, $question_type, $points, $question_image = null) {
        global $conn;
        
        $query = "INSERT INTO questions (quiz_id, question_text, question_type, points, question_image) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            return ['success' => false, 'message' => 'DB prepare failed: ' . $conn->error];
        }
        $stmt->bind_param("issis", $quiz_id, $question_text, $question_type, $points, $question_image);
        
        if ($stmt->execute()) {
            return ['success' => true, 'question_id' => $stmt->insert_id];
        }
        return ['success' => false, 'message' => 'Failed to add question'];
    }
    
    // Add answer option
    public static function add_answer($question_id, $answer_text, $is_correct) {
        global $conn;
        
        $query = "INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
        
        return $stmt->execute();
    }
    
    // Get all quizzes for a class
    public static function get_class_quizzes($class_id) {
        global $conn;
        
        $query = "SELECT q.*, 
                    (SELECT COUNT(*) FROM quiz_results WHERE quiz_id = q.id) as attempts,
                    (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count
                  FROM quizzes q 
                  WHERE q.class_id = ? AND q.is_active = 1
                  ORDER BY q.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get quiz with questions and answers
    public static function get_quiz($quiz_id) {
        global $conn;
        
        $query = "SELECT * FROM quizzes WHERE id = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $quiz = $stmt->get_result()->fetch_assoc();
        
        if (!$quiz) {
            return null;
        }
        
        // Get questions
        $q_query = "SELECT q.*, (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count FROM questions q WHERE q.quiz_id = ? ORDER BY q.id ASC";
        $q_stmt = $conn->prepare($q_query);
        $q_stmt->bind_param("i", $quiz_id);
        $q_stmt->execute();
        $quiz['questions'] = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get answers for each question
        foreach ($quiz['questions'] as &$question) {
            $a_query = "SELECT * FROM answers WHERE question_id = ? ORDER BY RAND()";
            $a_stmt = $conn->prepare($a_query);
            $a_stmt->bind_param("i", $question['id']);
            $a_stmt->execute();
            $question['answers'] = $a_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        return $quiz;
    }
    
    // Submit quiz
    public static function submit_quiz($quiz_id, $user_id, $answers, $time_taken) {
        global $conn;
        
        $score = 0;
        $total_points = 0;
        
        $conn->begin_transaction();
        
        try {
            $q_query = "SELECT SUM(points) as total FROM questions WHERE quiz_id = ?";
            $q_stmt = $conn->prepare($q_query);
            $q_stmt->bind_param("i", $quiz_id);
            $q_stmt->execute();
            $total_points = $q_stmt->get_result()->fetch_assoc()['total'] ?? 0;
            
            $pass_query = "SELECT passing_score FROM quizzes WHERE id = ?";
            $pass_stmt = $conn->prepare($pass_query);
            $pass_stmt->bind_param("i", $quiz_id);
            $pass_stmt->execute();
            $passing_score = $pass_stmt->get_result()->fetch_assoc()['passing_score'] ?? 0;
            
            $result_query = "INSERT INTO quiz_results (quiz_id, user_id, time_taken) VALUES (?, ?, ?)";
            $result_stmt = $conn->prepare($result_query);
            $result_stmt->bind_param("iii", $quiz_id, $user_id, $time_taken);
            $result_stmt->execute();
            $result_id = $result_stmt->insert_id;
            
            foreach ($answers as $question_id => $answer_id) {
                $pts_query = "SELECT points FROM questions WHERE id = ?";
                $pts_stmt = $conn->prepare($pts_query);
                $pts_stmt->bind_param("i", $question_id);
                $pts_stmt->execute();
                $points = $pts_stmt->get_result()->fetch_assoc()['points'] ?? 0;
                
                $chk_query = "SELECT is_correct FROM answers WHERE id = ? AND question_id = ?";
                $chk_stmt = $conn->prepare($chk_query);
                $chk_stmt->bind_param("ii", $answer_id, $question_id);
                $chk_stmt->execute();
                $is_correct = $chk_stmt->get_result()->fetch_assoc()['is_correct'] ?? 0;
                
                if ($is_correct) {
                    $score += $points;
                }
                
                $ans_query = "INSERT INTO student_answers (result_id, question_id, answer_id, is_correct) VALUES (?, ?, ?, ?)";
                $ans_stmt = $conn->prepare($ans_query);
                $ans_stmt->bind_param("iiii", $result_id, $question_id, $answer_id, $is_correct);
                $ans_stmt->execute();
            }
            
            $percentage = $total_points > 0 ? ($score / $total_points) * 100 : 0;
            $passed = $percentage >= $passing_score ? 1 : 0;
            $grade = self::map_grade($percentage);
            
            $update_query = "UPDATE quiz_results SET score = ?, total_points = ?, percentage = ?, grade = ?, passed = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("dddiii", $score, $total_points, $percentage, $grade, $passed, $result_id);
            $update_stmt->execute();
            
            $conn->commit();
            
            return [
                'success' => true,
                'result_id' => $result_id,
                'score' => $score,
                'total' => $total_points,
                'percentage' => round($percentage, 2),
                'grade' => $grade,
                'passed' => $passed
            ];
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Error submitting quiz'];
        }
    }

    private static function map_grade($percentage) {
        if ($percentage >= 90) {
            return 10;
        }
        if ($percentage >= 85) {
            return 9;
        }
        if ($percentage >= 75) {
            return 8;
        }
        if ($percentage >= 65) {
            return 7;
        }
        if ($percentage >= 55) {
            return 6;
        }
        if ($percentage >= 45) {
            return 5;
        }
        if ($percentage >= 35) {
            return 4;
        }
        if ($percentage >= 25) {
            return 3;
        }
        if ($percentage >= 15) {
            return 2;
        }
        return 1;
    }
}