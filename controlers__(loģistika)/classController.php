<?php
require_once __DIR__ . '/../configs__(iestatījumi)/database.php';

class ClassController {
    
    // Create class
    public static function create_class($name, $description, $teacher_id) {
        global $conn;
        
        $query = "INSERT INTO classes (name, description, teacher_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $name, $description, $teacher_id);
        
        if ($stmt->execute()) {
            return ['success' => true, 'class_id' => $stmt->insert_id];
        }
        return ['success' => false];
    }
    
    // Get teacher's classes
    public static function get_teacher_classes($teacher_id) {
        global $conn;
        
        $query = "SELECT c.*, (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count FROM classes c WHERE c.teacher_id = ? ORDER BY c.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get all classes for admin overview
    public static function get_all_classes() {
        global $conn;

        $query = "SELECT c.*, u.first_name, u.last_name, (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count FROM classes c INNER JOIN users u ON c.teacher_id = u.id ORDER BY c.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get student's classes
    public static function get_student_classes($user_id) {
        global $conn;
        
        $query = "SELECT c.* FROM classes c INNER JOIN class_enrollments ce ON c.id = ce.class_id WHERE ce.student_id = ? ORDER BY ce.enrolled_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Enroll student
    public static function enroll_student($class_id, $student_id) {
        global $conn;
        
        $query = "INSERT INTO class_enrollments (class_id, student_id) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $class_id, $student_id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Student already enrolled'];
    }
    
    // Get class students
    public static function get_class_students($class_id) {
        global $conn;
        
        $query = "SELECT u.id, u.first_name, u.last_name, u.email FROM users u INNER JOIN class_enrollments ce ON u.id = ce.student_id WHERE ce.class_id = ? ORDER BY u.first_name";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Remove student from class
    public static function remove_student($class_id, $student_id) {
        global $conn;
        
        $query = "DELETE FROM class_enrollments WHERE class_id = ? AND student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $class_id, $student_id);
        return $stmt->execute();
    }

    public static function set_class_teacher($class_id, $teacher_id) {
        global $conn;

        $query = "UPDATE classes SET teacher_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $teacher_id, $class_id);
        return $stmt->execute();
    }
}
?>
