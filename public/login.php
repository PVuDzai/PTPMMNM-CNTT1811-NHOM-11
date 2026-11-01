<?php
// public/login.php - Đăng nhập & Đăng ký (CyberRank Glassmorphism Style - Việt Hóa 100%)
require_once __DIR__ . '/../config/db.php';
session_start();
$pdo = getDBConnection();

$error = '';
$success = '';
$activeTab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'login';

    if ($formType === 'register') {
        $activeTab = 'register';
        $username  = trim($_POST['reg_username'] ?? '');
        $password  = trim($_POST['reg_password'] ?? '');
        $confirm   = trim($_POST['reg_confirm'] ?? '');
        $fullName  = trim($_POST['reg_fullname'] ?? '');
        $classCode = trim($_POST['reg_class'] ?? 'CNTT18-11');

        if ($username === '' || $password === '' || $fullName === '') {
            $error = 'Vui lòng điền đầy đủ thông tin bắt buộc!';
        } elseif ($password !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp!';
        } else {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetch()) {
                $error = 'Tên đăng nhập đã tồn tại, vui lòng chọn tên khác!';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmtIns = $pdo->prepare("
                    INSERT INTO users (username, password_hash, full_name, role, class_code, is_locked)
                    VALUES (?, ?, ?, 'student', ?, 0)
                ");
                $stmtIns->execute([$username, $hashedPassword, $fullName, $classCode]);
                $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay.';
                $activeTab = 'login';
            }
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $isValidPassword = false;
        if ($user) {
            if ($password === $user['password_hash']) {
                $isValidPassword = true; 
            } elseif (password_verify($password, $user['password_hash'])) {
                $isValidPassword = true;
            }
        }

        if ($isValidPassword) {
            if ($user['is_locked'] == 1) {
                $error = 'Tài khoản của bạn đã bị khóa do vi phạm. Vui lòng liên hệ Quản trị viên!';
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                if ($user['role'] === 'admin') header('Location: admin.php');
                elseif ($user['role'] === 'teacher') header('Location: teacher.php');
                else header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Sai tên đăng nhập hoặc mật khẩu!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cổng Xác Thực | CyberRank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --c-bg: #0b1120; 
            --c-primary: #10b981; 
            --c-primary-glow: rgba(16, 185, 129, 0.4);
            --c-text-main: #f8fafc; 
            --c-text-muted: #94a3b8; 
            --c-border: rgba(255, 255, 255, 0.08); 
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--c-bg); 
            color: var(--c-text-main); 
            min-height: 100vh; 
            display: flex; align-items: center; justify-content: center; 
            background-image: radial-gradient(circle at 50% 0%, rgba(30, 41, 59, 1) 0%, rgba(11, 17, 32, 1) 60%);
            background-repeat: no-repeat;
            background-attachment: fixed;
            margin: 0;
            padding: 20px;
        }

        .mono { font-family: 'JetBrains Mono', monospace; }

        /* Khung Glassmorphism */
        .glass-card { 
            background: rgba(30, 41, 59, 0.4); 
            backdrop-filter: blur(12px);
            border: 1px solid var(--c-border); 
            border-radius: 16px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            position: relative;
        }

        /* Ánh sáng trang trí */
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--c-primary), transparent);
            opacity: 0.5;
        }

        .brand-header {
            display: flex; align-items: center; justify-content: center; gap: 12px;
            padding: 32px 24px 20px;
        }
        
        .brand-icon {
            width: 28px; height: 28px; 
            background: linear-gradient(135deg, #10b981, #059669); 
            border-radius: 8px;
            box-shadow: 0 0 16px var(--c-primary-glow);
        }

        .brand-text { font-weight: 800; font-size: 1.6rem; letter-spacing: 0.5px; }

        /* Các ô nhập liệu (Inputs) */
        .form-label { color: var(--c-text-muted); font-weight: 500; font-size: 0.85rem; margin-bottom: 6px; }
        .form-control { 
            background-color: rgba(15, 23, 42, 0.6); 
            border: 1px solid var(--c-border); 
            color: var(--c-text-main); 
            font-size: 0.95rem; 
            padding: 10px 14px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .form-control:focus { 
            background-color: rgba(15, 23, 42, 0.9); 
            border-color: var(--c-primary); 
            color: #fff; 
            box-shadow: 0 0 0 3px var(--c-primary-glow); 
        }

        /* Nút Submit */
        .btn-cyber { 
            background: linear-gradient(to right, #10b981, #059669); 
            color: #fff; font-weight: 600; border: none; border-radius: 8px; 
            padding: 12px; font-size: 1rem; letter-spacing: 0.5px;
            transition: all 0.2s; width: 100%; margin-top: 10px;
        }
        .btn-cyber:hover { transform: translateY(-1px); box-shadow: 0 4px 15px var(--c-primary-glow); color: #fff; }

        /* Tài khoản mẫu */
        .credentials-box { 
            background: rgba(0,0,0,0.2); 
            border: 1px dashed var(--c-border); 
            border-radius: 8px; padding: 12px; margin-top: 24px; 
            font-size: 0.8rem; color: var(--c-text-muted); 
        }
        .credentials-box code { color: #6ee7b7; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; }

        /* Liên kết chuyển đổi (Link Toggle) - ĐÃ LÀM NỔI BẬT */
        .auth-link {
            text-align: center; margin-top: 20px; font-size: 0.9rem; color: var(--c-text-muted);
        }
        .auth-link-action {
            color: var(--c-primary); 
            font-weight: 700; 
            text-decoration: underline; 
            text-underline-offset: 3px;
            transition: all 0.2s; 
            cursor: pointer;
            margin-left: 4px;
        }
        .auth-link-action:hover { 
            color: #34d399; 
            text-shadow: 0 0 8px var(--c-primary-glow); 
        }

        /* Hiệu ứng Fade Form */
        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .form-section.active { display: block; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="glass-card">
        <div class="brand-header">
            <div class="brand-icon"></div>
            <div class="brand-text text-white">CyberRank</div>
        </div>
        
        <div class="px-4 pb-4">
            <!-- Thông báo Lỗi / Thành công -->
            <?php if ($error): ?>
                <div class="alert mb-3 py-2 px-3 border-0 rounded-3 text-center" style="background: rgba(239,68,68,0.15); color: #fca5a5; font-size: 0.85rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert mb-3 py-2 px-3 border-0 rounded-3 text-center" style="background: rgba(16,185,129,0.15); color: #6ee7b7; font-size: 0.85rem;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- FORM ĐĂNG NHẬP -->
            <div id="sectionLogin" class="form-section <?= $activeTab === 'login' ? 'active' : '' ?>">
                <form method="POST">
                    <input type="hidden" name="form_type" value="login">
                    <div class="mb-3">
                        <label class="form-label">Tên đăng nhập (Username)</label>
                        <input type="text" name="username" class="form-control" required placeholder="Nhập tên tài khoản..." autocomplete="off">
                    </div>
                    <div class="mb-4">
                        <label class="form-label d-flex justify-content-between">
                            Mật khẩu (Password)
                            <a href="#" class="text-muted text-decoration-none" style="font-size: 0.75rem;">Quên mật khẩu?</a>
                        </label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    
                    <button type="submit" class="btn-cyber">Đăng Nhập Vào Hệ Thống</button>
                    
                    <div class="auth-link">
                        Chưa có tài khoản? <span class="auth-link-action" onclick="toggleForms('register')">Tạo tài khoản học viên mới</span>
                    </div>
                    
                    <div class="credentials-box text-center">
                        <div class="fw-bold mb-1 text-white">Tài khoản trải nghiệm mẫu:</div>
                        Học viên: <code>studentA</code> / <code>123456</code><br>
                        Giảng viên: <code>teacher1</code> / <code>123456</code><br>
                        Quản trị: <code>admin1</code> / <code>123456</code>
                    </div>
                </form>
            </div>

            <!-- FORM ĐĂNG KÝ -->
            <div id="sectionRegister" class="form-section <?= $activeTab === 'register' ? 'active' : '' ?>">
                <form method="POST">
                    <input type="hidden" name="form_type" value="register">
                    
                    <div class="mb-3">
                        <label class="form-label">Họ và tên đầy đủ</label>
                        <input type="text" name="reg_fullname" class="form-control" required placeholder="VD: Nguyễn Văn A">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label">Tên đăng nhập</label>
                            <input type="text" name="reg_username" class="form-control mono" required placeholder="student_new">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Mã lớp</label>
                            <input type="text" name="reg_class" class="form-control mono text-uppercase" value="CNTT18-11" required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" name="reg_password" class="form-control" required placeholder="••••••••">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Xác nhận MK</label>
                            <input type="password" name="reg_confirm" class="form-control" required placeholder="••••••••">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-cyber">Đăng Ký Tài Khoản</button>
                    
                    <div class="auth-link">
                        Đã có tài khoản? <span class="auth-link-action" onclick="toggleForms('login')">Quay lại đăng nhập</span>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        function toggleForms(target) {
            document.getElementById('sectionLogin').classList.toggle('active', target === 'login');
            document.getElementById('sectionRegister').classList.toggle('active', target === 'register');
        }
    </script>
</body>
</html>