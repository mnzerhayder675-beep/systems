<?php
session_start();
require_once 'config/db_connect.php';

// إذا كان المستخدم مسجل الدخول بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // التحقق من المدخلات
    if (empty($username) || empty($password)) {
        $error = 'يرجى ملء جميع الحقول';
    } else {
        try {
            $stmt = $db->prepare("
                SELECT id, username, password, full_name, role, is_active 
                FROM users 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // التحقق من كلمة المرور
                if (password_verify($password, $user['password'])) {
                    if ($user['is_active'] == 1) {
                        // تسجيل الدخول الناجح
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_time'] = time();
                        
                        // تسجيل الدخول في سجل النشاط
                        $stmt = $db->prepare("
                            INSERT INTO activity_log (user_id, action, ip_address) 
                            VALUES (?, 'login', ?)
                        ");
                        $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
                        
                        header('Location: index.php');
                        exit();
                    } else {
                        $error = 'هذا الحساب معطل';
                    }
                } else {
                    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                }
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
            }
        } catch (PDOException $e) {
            $error = 'حدث خطأ في النظام. الرجاء المحاولة لاحقاً';
        }
    }
}

// تعطيل الشريط الجانبي
$hide_sidebar = true;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة الصيدلية</title>
    <link rel="stylesheet" href="assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cairo', sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: bold;
        }
        
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.8;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            padding: 12px 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #eee;
            border-left: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px;
        }
        
        .logo {
            font-size: 2.5rem;
            color: #3498db;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fa fa-heartbeat"></i>
                </div>
                <h2>نظام إدارة الصيدلية</h2>
                <p>تسجيل الدخول إلى لوحة التحكم</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fa fa-user"></i> اسم المستخدم
                        </label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   placeholder="أدخل اسم المستخدم"
                                   required
                                   autofocus>
                            <span class="input-group-text">
                                <i class="fa fa-user-circle"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fa fa-lock"></i> كلمة المرور
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="أدخل كلمة المرور"
                                   required>
                            <span class="input-group-text">
                                <i class="fa fa-key"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">
                            تذكرني
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fa fa-sign-in-alt"></i> تسجيل الدخول
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                <p class="text-muted mb-0">
                    <i class="fa fa-info-circle"></i>
                    للدعم الفني: <a href="mailto:support@pharmacy.com">support@pharmacy.com</a>
                </p>
                <small class="text-muted">
                    الإصدار 1.0.0 © <?php echo date('Y'); ?>
                </small>
            </div>
        </div>
    </div>
    
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // عرض/إخفاء كلمة المرور
        $('#show-password').click(function() {
            const passwordInput = $('#password');
            const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
            passwordInput.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        
        // تحقق من الحقول المطلوبة
        $('form').submit(function(e) {
            let isValid = true;
            $(this).find('[required]').each(function() {
                if ($(this).val().trim() === '') {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('يرجى ملء جميع الحقول المطلوبة');
            }
        });
    });
    </script>
</body>
</html>