<?php
// public/login.php - Trang đăng nhập hệ thống phong cách HackerRank
require_once __DIR__ . '/../config/db.php';
session_start();
$pdo = getDBConnection();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password_hash']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role']; // 'student', 'teacher', 'admin'

        if ($user['role'] === 'admin') {
            header('Location: admin.php');
        } elseif ($user['role'] === 'teacher') {
            header('Location: teacher.php');
        } else {
            header('Location: index.php');
        }
        exit;
    } else {
        $error = 'Sai tên đăng nhập hoặc mật khẩu!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Online Judge (HackerRank Style)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --hr-dark: #0e141e;
            --hr-panel-dark: #1c2432;
            --hr-green: #00ea64;
            --hr-green-hover: #00c853;
            --hr-green-text: #0e141e;
            --hr-border: #2b3648;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--hr-dark);
            color: #e2e8f0;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hr-login-card {
            background-color: var(--hr-panel-dark);
            border: 1px solid var(--hr-border);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            width: 400px;
            padding: 32px;
        }

        .hr-logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .hr-logo-box {
            width: 16px;
            height: 24px;
            background-color: var(--hr-green);
            border-radius: 2px;
        }

        .hr-logo-text {
            font-weight: 700;
            font-size: 1.4rem;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .form-label {
            color: #cbd5e1;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .form-control {
            background-color: #0b0f17;
            border: 1px solid var(--hr-border);
            color: #ffffff;
            font-size: 0.95rem;
            padding: 10px 14px;
        }

        .form-control:focus {
            background-color: #0b0f17;
            border-color: var(--hr-green);
            color: #ffffff;
            box-shadow: 0 0 0 2px rgba(0, 234, 100, 0.2);
        }

        .btn-hr-login {
            background-color: var(--hr-green);
            color: var(--hr-green-text);
            font-weight: 700;
            border: none;
            padding: 10px;
            font-size: 0.95rem;
            transition: background-color 0.2s;
        }

        .btn-hr-login:hover {
            background-color: var(--hr-green-hover);
            color: var(--hr-green-text);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
        }

        .credentials-box {
            background-color: #0b0f17;
            border: 1px dashed var(--hr-border);
            border-radius: 6px;
            padding: 12px;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .credentials-box code {
            color: var(--hr-green);
            background: rgba(0, 234, 100, 0.1);
            padding: 2px 4px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="hr-login-card">
        <div class="hr-logo-container">
            <span class="hr-logo-box"></span>
            <span class="hr-logo-text">HackerRank <span class="fs-6 fw-normal text-secondary">| Login</span></span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger p-2 small text-center mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Tên đăng nhập (Username):</label>
                <input type="text" name="username" class="form-control" required placeholder="studentA, teacher1, admin1" autocomplete="off">
            </div>
            <div class="mb-4">
                <label class="form-label">Mật khẩu (Password):</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••">
            </div>
            <button type="submit" class="btn btn-hr-login w-100">Đăng nhập hệ thống</button>
        </form>

        <div class="credentials-box text-center">
            <strong>Tài khoản mẫu thử nghiệm:</strong><br>
            • Học sinh: <code>studentA</code> / <code>123456</code><br>
            • Giảng viên: <code>teacher1</code> / <code>123456</code><br>
            • Quản trị viên: <code>admin1</code> / <code>123456</code>
        </div>
    </div>
</body>
</html>