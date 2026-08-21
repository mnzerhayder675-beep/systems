<?php
session_start();

// تسجيل الخروج في سجل النشاط
if (isset($_SESSION['user_id'])) {
    require_once 'config/db_connect.php';
    
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address) 
            VALUES (?, 'logout', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_GET['reason'] ?? 'manual',
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        // تجاهل الأخطاء في تسجيل الخروج
    }
}

// تدمير الجلسة
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
header('Location: login.php');
exit();
?>