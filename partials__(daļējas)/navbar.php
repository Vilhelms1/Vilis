<?php
// Modern Navbar Component
// Izmanto: $_SESSION, BASE_URL
// Iekļauj šo failu sākumā: require_once __DIR__ . '/../partials__(daļējas)/navbar.php';
// Renderē: render_navbar($title, $role);

function render_navbar($title = 'ApgūstiVairāk', $role = null) {
    $user_name = htmlspecialchars($_SESSION['first_name'] ?? 'Lietotājs');
    $base_url = BASE_URL ?? '/BeiguDarbs/';
    $role = $role ?? ($_SESSION['role'] ?? 'student');
    
    $icons = [
        'admin' => '👨‍💼',
        'teacher' => '👨‍🏫',
        'student' => '👨‍🎓'
    ];
    
    $icon = $icons[$role] ?? '👤';
    ?>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand"><?php echo $icon; ?> <?php echo $title; ?></div>
            <div class="nav-actions">
                <div class="nav-user">👤 <?php echo $user_name; ?></div>
                <button class="btn btn-ghost btn-small" data-lang-toggle>LV / EN</button>
                <button class="btn btn-ghost btn-small" data-theme-toggle>🌙</button>
                <a href="<?php echo $base_url; ?>view/process_logout.php" class="btn btn-small btn-secondary">Iziet</a>
            </div>
        </div>
    </nav>
    <?php
}
?>
