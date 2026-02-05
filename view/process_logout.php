<?php
require_once __DIR__ . '/../configs__(iestatījumi)/database.php';
require_once __DIR__ . '/../controlers__(loģistika)/autenController.php';

AuthController::logout();
header('Location: ' . BASE_URL . 'view/login-reg.php');
exit();
?>
