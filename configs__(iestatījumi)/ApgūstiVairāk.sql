-- Create Database
CREATE DATABASE IF NOT EXISTS apgūstivairāk
;
USE apgūstivairāk;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role ENUM('admin', 'student') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT DEFAULT 1
);

-- Classes Table
CREATE TABLE IF NOT EXISTS classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    admin_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Class Enrollments 
CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    user_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment (class_id, user_id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Quizzes Table
CREATE TABLE IF NOT EXISTS quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    admin_id INT NOT NULL,
    time_limit INT DEFAULT 0,
    passing_score INT DEFAULT 60,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

-- Questions Table
CREATE TABLE IF NOT EXISTS questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false') DEFAULT 'multiple_choice',
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Answers (Options for questions)
CREATE TABLE IF NOT EXISTS answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    is_correct TINYINT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Quiz Results
CREATE TABLE IF NOT EXISTS quiz_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT DEFAULT 0,
    total_points INT DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    passed TINYINT DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    time_taken INT DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Student Answers 
CREATE TABLE IF NOT EXISTS student_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    result_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_id INT,
    is_correct TINYINT DEFAULT 0,
    FOREIGN KEY (result_id) REFERENCES quiz_results(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id),
    FOREIGN KEY (answer_id) REFERENCES answers(id)
);

-- Class Materials/Documents
CREATE TABLE IF NOT EXISTS class_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255),
    file_type VARCHAR(50),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Create Indexes 
CREATE INDEX idx_user_role ON users(role);
CREATE INDEX idx_class_admin ON classes(admin_id);
CREATE INDEX idx_enrollment_user ON class_enrollments(user_id);
CREATE INDEX idx_enrollment_class ON class_enrollments(class_id);
CREATE INDEX idx_quiz_class ON quizzes(class_id);
CREATE INDEX idx_result_user ON quiz_results(user_id);
CREATE INDEX idx_result_quiz ON quiz_results(quiz_id);
CREATE INDEX idx_question_quiz ON questions(quiz_id);
CREATE INDEX idx_answer_question ON answers(question_id);