<?php 
require_once __DIR__ . '/../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../controlers__(loģistika)/autenController.php';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . 'view/admin/admin_dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'view/student/student_dashboard.php');
    }
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = AuthController::login($username, $password);
        if (!empty($result['success'])) {
            if ($result['role'] === 'admin') {
                header('Location: ' . BASE_URL . 'view/admin/admin_dashboard.php');
            } else {
                header('Location: ' . BASE_URL . 'view/student/student_dashboard.php');
            }
            exit();
        }

        $error = $result['message'] ?? 'Login failed';
    }

    if ($formType === 'register') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $result = AuthController::register($username, $email, $password, $confirmPassword, $firstName, $lastName);
        if (!empty($result['success'])) {
            $success = $result['message'] ?? 'Registration successful. Please login.';
        } else {
            $error = $result['message'] ?? 'Registration failed';
        }
    }
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApgūstiVairāk</title>
    <link rel="stylesheet" href="/BeiguDarbs/assets/css/login-style.css">
</head>
<body class="webpage" style="--login-bg: url('/BeiguDarbs/assets/image/picture.jpg');">
    <div class="container">
        <div class="VS-box">
            <h1>📒ApgūstiVairāk</h1>
            <h2>Learning Management System</h2>
            
            <div class="main-btns">
                <button class="login-btn active" onclick="switchTab('login')">Login</button>
                <button class="register-btn" onclick="switchTab('register')">Register</button>
            </div>
            
            <!-- Login Form -->
            <div id="login" class="login">
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="login">
                    <div class="form-group">
                        <label for="login_username">Username or Email</label>
                        <input type="text" id="login_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">Login</button>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Register Form -->
            <div id="register" class="Register-form">
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="register">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_username">Username</label>
                        <input type="text" id="reg_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_email">Email</label>
                        <input type="email" id="reg_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_password">Password</label>
                        <input type="password" id="reg_password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">Register</button>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                </form>
            </div>
        </div>
    </div>
   
    <script>
        function switchTab(tabName) {
    // Paslēpjam abas formas
    document.getElementById('login').style.display = 'none';
    document.getElementById('register').style.display = 'none';
    
    // Parādām vajadzīgo
    document.getElementById(tabName).style.display = 'block';

    // Noņemam "active" klasi no pogām un pieliekam klikšķinātajai
    document.querySelectorAll('.main-btns button').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
}
    </script>
</body>
</html>