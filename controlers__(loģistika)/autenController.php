<?php
require_once __DIR__ . '/../configs__(iestatījumi)/database.php';

class AuthController {
    
    public static function register($username, $email, $password, $confirm_password, $first_name, $last_name) {
        global $conn;
        
        // Validate inputs
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if ($password !== $confirm_password) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        // Check if user exists
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert user
        $insert_query = "INSERT INTO users (username, email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'student')";
        $insert_stmt = $conn->prepare($insert_query);
        if (!$insert_stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $insert_stmt->bind_param("sssss", $username, $email, $hashed_password, $first_name, $last_name);
        
        if ($insert_stmt->execute()) {
            return ['success' => true, 'message' => 'Registration successful. Please login.'];
        } else {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    public static function login($username, $password) {
        global $conn;
        
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password required'];
        }
        
        $query = "SELECT id, username, email, password, role, first_name FROM users WHERE (username = ? OR email = ?) AND is_active = 1";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        
        return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
    }
    
    public static function logout() {
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public static function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
    
    public static function is_admin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function is_teacher() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
    }

    public static function is_student() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
    }

    public static function set_role($user_id, $role) {
        global $conn;

        $allowed = ['admin', 'teacher', 'student'];
        if (!in_array($role, $allowed, true)) {
            return ['success' => false, 'message' => 'Invalid role'];
        }

        $query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param("si", $role, $user_id);
        if ($stmt->execute()) {
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'Update failed'];
    }
}
?>
