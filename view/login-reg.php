<?php 
require_once __DIR__ . '/../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../controlers__(loģistika)/autenController.php';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . 'view/admin/admin_dashboard.php');
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
        header('Location: ' . BASE_URL . 'view/teacher/teacher_dashboard.php');
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
            } elseif ($result['role'] === 'teacher') {
                header('Location: ' . BASE_URL . 'view/teacher/teacher_dashboard.php');
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

$news_items = [];
$news_query = "SELECT title, body, image_path, published_at FROM school_news WHERE is_active = 1 ORDER BY published_at DESC LIMIT 6";
$news_stmt = $conn->prepare($news_query);
if ($news_stmt) {
    $news_stmt->execute();
    $news_items = $news_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>




<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ApgūstiVairāk - Mācību platforma</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/modern-style.css?v=20260210">
    <script defer src="<?php echo BASE_URL; ?>assets/js/app.js?v=20260210"></script>
</head>
<body data-theme="light" data-lang="lv">
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">🎓 ApgūstiVairāk</div>
            <div class="nav-actions">
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
            </div>
        </div>
    </nav>

    <div class="container container-tight" style="padding: 4rem 0;">
        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; min-height: 70vh;">
            <!-- Left Side - Info -->
            <div class="slide-in-left">
                <h1 class="page-title" style="line-height: 1.2;">Profesionāla mācību platforma</h1>
                <p class="page-subtitle" style="font-size: 1.15rem; margin: 1.5rem 0 2rem;">
                    Apgūsti jaunas prasmes, seko līdzi savai progresai un sagatavies eksāmeniem ar ApgūstiVairāk.
                </p>
                
                <!-- Features -->
                <div class="grid gap-3" style="margin-top: 2.5rem;">
                    <div class="flex gap-2" style="align-items: flex-start;">
                        <span style="font-size: 1.5rem;">📚</span>
                        <div>
                            <h4 style="margin: 0 0 0.5rem; color: var(--text-primary);">Interaktīvie kursi</h4>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">Mācies prasmes, kas nepieciešamas profesionālajā nozarē</p>
                        </div>
                    </div>
                    <div class="flex gap-2" style="align-items: flex-start;">
                        <span style="font-size: 1.5rem;">✅</span>
                        <div>
                            <h4 style="margin: 0 0 0.5rem; color: var(--text-primary);">Prasmju pārbaudīšana</h4>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">Testējies ar interaktīviem viktorīniem</p>
                        </div>
                    </div>
                    <div class="flex gap-2" style="align-items: flex-start;">
                        <span style="font-size: 1.5rem;">📊</span>
                        <div>
                            <h4 style="margin: 0 0 0.5rem; color: var(--text-primary);">Progresa izsekošana</h4>
                            <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">Redzi savu progresu uz reāllaikā</p>
                        </div>
                    </div>
                </div>

                <div class="news-section" style="margin-top: 2.5rem;">
                    <h3 style="margin-bottom: 1rem;">Jaunumi</h3>
                    <?php if (empty($news_items)): ?>
                        <div class="card" style="padding: 1rem;">
                            <p style="margin: 0; color: var(--text-secondary);">Jaunumi tiks pievienoti drīzumā.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid gap-2">
                            <?php foreach ($news_items as $news): ?>
                                <?php $image_path = !empty($news['image_path']) ? BASE_URL . 'uploads/' . $news['image_path'] : BASE_URL . 'assets/image/picture.jpg'; ?>
                                <div class="news-card" style="background-image: url('<?php echo htmlspecialchars($image_path); ?>');">
                                    <div class="news-overlay">
                                        <h4><?php echo htmlspecialchars($news['title']); ?></h4>
                                        <p><?php echo htmlspecialchars(substr($news['body'] ?? '', 0, 120)); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Side - Forms -->
            <div class="glass-card" style="padding: 2.5rem;">
                <div class="tabs" style="margin-bottom: 2rem; flex-wrap: wrap;">
                    <button class="tab-btn active login-btn" onclick="switchTab('login')" data-i18n="login.loginBtn">
                        Pieslēgties
                    </button>
                    <button class="tab-btn register-btn" onclick="switchTab('register')" data-i18n="login.registerBtn">
                        Reģistrēties
                    </button>
                </div>
                
                <!-- Login Form -->
                <div id="login" class="tab-content active">
                    <form method="POST" action="">
                        <input type="hidden" name="form_type" value="login">
                        
                        <div class="form-group">
                            <label class="form-label" for="login_username" data-i18n="login.username">Lietotājvārds vai e-pasts</label>
                            <input class="form-input" type="text" id="login_username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="login_password" data-i18n="login.password">Parole</label>
                            <input class="form-input" type="password" id="login_password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;" data-i18n="login.loginBtn">Pieslēgties</button>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger mt-3" style="margin-top: 1rem;">
                                <span>⚠️</span>
                                <span><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success mt-3" style="margin-top: 1rem;">
                                <span>✓</span>
                                <span><?php echo htmlspecialchars($success); ?></span>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Register Form -->
                <div id="register" class="tab-content">
                    <form method="POST" action="">
                        <input type="hidden" name="form_type" value="register">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="first_name" data-i18n="register.firstName">Vārds</label>
                                <input class="form-input" type="text" id="first_name" name="first_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="last_name" data-i18n="register.lastName">Uzvārds</label>
                                <input class="form-input" type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="reg_username" data-i18n="register.username">Lietotājvārds</label>
                            <input class="form-input" type="text" id="reg_username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="reg_email" data-i18n="register.email">E-pasts</label>
                            <input class="form-input" type="email" id="reg_email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="reg_password" data-i18n="register.password">Parole</label>
                            <input class="form-input" type="password" id="reg_password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password" data-i18n="register.confirm">Apstipriniet paroli</label>
                            <input class="form-input" type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;" data-i18n="register.registerBtn">Reģistrēties</button>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger mt-3" style="margin-top: 1rem;">
                                <span>⚠️</span>
                                <span><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success mt-3" style="margin-top: 1rem;">
                                <span>✓</span>
                                <span><?php echo htmlspecialchars($success); ?></span>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
   
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            switchTab('login');
        });

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(panel => panel.classList.remove('active'));
            const target = document.getElementById(tabName);
            if (target) {
                target.classList.add('active');
            }

            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            if (event && event.target) {
                event.target.classList.add('active');
            } else {
                const fallback = document.querySelector(`.${tabName}-btn`);
                if (fallback) fallback.classList.add('active');
            }
        }
    </script>
</body>
</html>