<?php
// public/admin.php - Giao diện Quản trị viên dịch vụ thực thi (Admin phong cách HackerRank)
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$message = '';

// Thêm người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $pass     = trim($_POST['password'] ?? '123456');
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? 'student';
        $class    = trim($_POST['class_code'] ?? 'CNTT18-11');

        if ($username !== '') {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, class_code) VALUES (?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $pass, $fullName, $role, $class]);
                $message = "✓ Đã thêm người dùng mới thành công!";
            } catch (Exception $e) {
                $message = "Lỗi: " . $e->getMessage();
            }
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$totalSubmissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$totalProblems = $pdo->query("SELECT COUNT(*) FROM problems")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản trị hệ thống - Admin (HackerRank Style)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --hr-dark: #0e141e;
            --hr-panel-dark: #1c2432;
            --hr-green: #00ea64;
            --hr-green-hover: #00c853;
            --hr-green-text: #0e141e;
            --hr-border: #2b3648;
            --hr-bg: #0b0f17;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--hr-bg);
            color: #e2e8f0;
            font-size: 14px;
            min-height: 100vh;
        }

        /* Navbar */
        .hr-navbar {
            background-color: var(--hr-dark);
            border-bottom: 1px solid var(--hr-border);
            height: 58px;
        }
        .hr-logo {
            font-weight: 700;
            font-size: 1.15rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hr-logo-box {
            display: inline-block;
            width: 14px;
            height: 20px;
            background-color: var(--hr-green);
            border-radius: 2px;
        }

        /* Thẻ Card Thống kê & Form */
        .hr-card {
            background-color: var(--hr-panel-dark);
            border: 1px solid var(--hr-border);
            border-radius: 6px;
            color: #ffffff;
        }
        .hr-card-header {
            background-color: #141a24;
            border-bottom: 1px solid var(--hr-border);
            font-weight: 700;
            color: #ffffff;
            padding: 12px 16px;
        }

        /* Form Controls */
        .form-label {
            color: #cbd5e1;
            font-size: 0.85rem;
        }
        .form-control, .form-select {
            background-color: #0b0f17;
            border: 1px solid var(--hr-border);
            color: #ffffff;
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            background-color: #0b0f17;
            border-color: var(--hr-green);
            color: #ffffff;
            box-shadow: 0 0 0 2px rgba(0, 234, 100, 0.2);
        }

        /* Nút bấm HackerRank */
        .btn-hr-primary {
            background-color: var(--hr-green);
            color: var(--hr-green-text);
            font-weight: 700;
            border: none;
        }
        .btn-hr-primary:hover {
            background-color: var(--hr-green-hover);
            color: var(--hr-green-text);
        }

        /* Bảng dữ liệu */
        .table {
            color: #e2e8f0;
            border-color: var(--hr-border);
            margin-bottom: 0;
        }
        .table-dark-custom {
            background-color: var(--hr-panel-dark);
        }
        .table-dark-custom th {
            background-color: #141a24;
            color: #94a3b8;
            border-bottom: 1px solid var(--hr-border);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .table-dark-custom td {
            border-bottom: 1px solid var(--hr-border);
            background-color: var(--hr-panel-dark);
            color: #e2e8f0;
        }
        .table-hover tbody tr:hover td {
            background-color: #253042;
            color: #ffffff;
        }

        .badge-role {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
        }

        .alert-info {
            background-color: rgba(0, 234, 100, 0.1);
            border: 1px solid var(--hr-green);
            color: var(--hr-green);
        }
    </style>
</head>
<body>

<nav class="navbar hr-navbar px-4 mb-4">
    <div class="hr-logo">
        <span class="hr-logo-box"></span>
        <span>HackerRank Admin</span>
        <span class="text-secondary fw-normal fs-6 ms-2">| Quản trị viên Dịch vụ thực thi</span>
    </div>
    <div class="d-flex align-items-center gap-3 text-white">
        <span class="small text-secondary">Xin chào, <strong class="text-white"><?= htmlspecialchars($_SESSION['full_name']) ?></strong></span>
        <a href="index.php" class="btn btn-outline-light btn-sm py-1 px-3">Vào trang Luyện tập</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm py-1 px-3">Đăng xuất</a>
    </div>
</nav>

<div class="container pb-5">
    <?php if ($message): ?>
        <div class="alert alert-info fw-bold py-2 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Thẻ thống kê tổng quan -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="hr-card p-3 border-start border-4 border-success">
                <div class="text-secondary small uppercase fw-semibold">Tổng số bài tập</div>
                <h3 class="fw-bold mb-0 text-white mt-1"><?= $totalProblems ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="hr-card p-3 border-start border-4 border-info">
                <div class="text-secondary small uppercase fw-semibold">Tổng số bài nộp</div>
                <h3 class="fw-bold mb-0 text-white mt-1"><?= $totalSubmissions ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="hr-card p-3 border-start border-4 border-warning">
                <div class="text-secondary small uppercase fw-semibold">Trạng thái Sandbox Service</div>
                <h5 class="fw-bold text-success mb-0 mt-2">● Hoạt động (Port 4000)</h5>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Form thêm tài khoản -->
        <div class="col-lg-4">
            <div class="hr-card shadow-sm">
                <div class="hr-card-header">Thêm tài khoản mới</div>
                <div class="card-body p-3">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-2">
                            <label class="form-label fw-bold">Tên đăng nhập (Username):</label>
                            <input type="text" name="username" class="form-control form-control-sm" required autocomplete="off">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Mật khẩu:</label>
                            <input type="text" name="password" class="form-control form-control-sm" value="123456" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Họ và tên:</label>
                            <input type="text" name="full_name" class="form-control form-control-sm" required autocomplete="off">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-bold">Vai trò (Role):</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="student">Người học (Student)</option>
                                <option value="teacher">Giảng viên (Teacher)</option>
                                <option value="admin">Quản trị viên (Admin)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mã lớp:</label>
                            <input type="text" name="class_code" class="form-control form-control-sm" value="CNTT18-11">
                        </div>
                        <button type="submit" class="btn btn-hr-primary btn-sm w-100 fw-bold py-2">Tạo tài khoản</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danh sách toàn bộ người dùng -->
        <div class="col-lg-8">
            <div class="hr-card shadow-sm overflow-hidden">
                <div class="hr-card-header d-flex justify-content-between align-items-center">
                    <span>Danh sách tài khoản hệ thống</span>
                    <span class="badge bg-secondary font-monospace">Tổng: <?= count($users) ?> users</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th class="ps-3">ID</th>
                                <th>Username</th>
                                <th>Họ tên</th>
                                <th>Vai trò</th>
                                <th>Lớp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-3 fw-bold font-monospace">#<?= $u['id'] ?></td>
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                                    <td>
                                        <span class="badge badge-role <?= $u['role'] === 'admin' ? 'bg-danger' : ($u['role'] === 'teacher' ? 'bg-warning text-dark' : 'bg-success') ?>">
                                            <?= strtoupper($u['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($u['class_code']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>