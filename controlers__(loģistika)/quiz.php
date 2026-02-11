<?php

require_once __DIR__ . '/../config/database.php';

class QuizController {

static function create_quiz($class_id, $title, $description, $admin_id, $time_limit, $passing_score) {
    global $conn;

    $query = "INSERT INTO quizzes (class_id, title, description, admin_id, time_limit, passing_score) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isisii", $class_id, $title, $description, $admin_id, $time_limit, $passing_score);

    if ($stmt->execute()) {
        return ['success' => true, 'quiz_id' => $stmt->insert_id];
    }
    return ['success' => false];

}




}