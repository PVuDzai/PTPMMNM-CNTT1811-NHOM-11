<?php
// public/admin.php - Quản trị viên: Live Health-check, Retry Error & CRUD (Cyberpunk / Glassmorphism Style - 100% Tiếng Việt)
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$message = '';
$errorMsg = '';

// -----------------------------------------------------------------------------
// 1. XỬ LÝ CÁC HÀNH ĐỘNG (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Nút: Thử lại các bài bị lỗi hệ thống
    if ($action === 'retry_system_error') {
        $stmt = $pdo->prepare("UPDATE judging_runs SET status = 'queued' WHERE status = 'SYSTEM_ERROR'");
        $stmt->execute();
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $message = "Đã đưa $affected bài nộp bị kẹt (SYSTEM_ERROR) quay lại hàng đợi chấm!";
        } else {
            $errorMsg = "Không có bài nộp nào đang bị lỗi hệ thống để thử lại.";
        }
    }
    // Nút: Thêm người dùng mới
    elseif ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $pass     = trim($_POST['password'] ?? '123456');
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? 'student';
        $class    = trim($_POST['class_code'] ?? 'CNTT18-11');

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetch()) {
            $errorMsg = "Tên đăng nhập '$username' đã tồn tại!";
        } else {
            $hashed = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, class_code, is_locked) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$username, $hashed, $fullName, $role, $class]);
            $message = "Đã thêm người dùng mới thành công!";
        }
    } 
    // Nút: Sửa thông tin người dùng
    elseif ($action === 'edit_user') {
        $uid      = (int)$_POST['user_id'];
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? 'student';
        $class    = trim($_POST['class_code'] ?? '');
        
        $pdo->prepare("UPDATE users SET full_name = ?, role = ?, class_code = ? WHERE id = ?")
            ->execute([$fullName, $role, $class, $uid]);
        $message = "Cập nhật thông tin tài khoản #$uid thành công!";
    }
    // Nút: Khóa / Mở khóa tài khoản
    elseif ($action === 'toggle_lock') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            $errorMsg = "Bạn không thể tự khóa tài khoản của chính mình!";
        } else {
            $pdo->prepare("UPDATE users SET is_locked = 1 - is_locked WHERE id = ?")->execute([$uid]);
            $message = "Đã thay đổi trạng thái khóa của tài khoản #$uid!";
        }
    }
    // Nút: Đặt lại mật khẩu (Reset Password)
    elseif ($action === 'reset_password') {
        $uid = (int)$_POST['user_id'];
        $hashed = password_hash('123456', PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hashed, $uid]);
        $message = "Đã đặt lại mật khẩu thành '123456' cho tài khoản #$uid!";
    }
}

// -----------------------------------------------------------------------------
// 2. LIVE HEALTH-CHECK & THỐNG KÊ HỆ THỐNG
// -----------------------------------------------------------------------------
// A. Ping Sandbox Service
$sandboxHost = '127.0.0.1';
$sandboxPort = 4000;
$sandboxStatus = 'OFFLINE';
$pingTime = 0;

$startTime = microtime(true);
$fp = @fsockopen($sandboxHost, $sandboxPort, $errno, $errstr, 1);
if ($fp) {
    $sandboxStatus = 'ONLINE';
    $pingTime = round((microtime(true) - $startTime) * 1000);
    fclose($fp);
}

// B. Lấy các số liệu thống kê
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$totalSubmissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$totalProblems = $pdo->query("SELECT COUNT(*) FROM problems")->fetchColumn();
$sysErrorCount = $pdo->query("SELECT COUNT(*) FROM judging_runs WHERE status = 'SYSTEM_ERROR'")->fetchColumn();

// Thống kê vai trò
$roleStats = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$studentCount = $roleStats['student'] ?? 0;
$teacherCount = $roleStats['teacher'] ?? 0;
$adminCount   = $roleStats['admin'] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng Điều Khiển Admin | Online Judge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --c-bg: #0b1120; 
            --c-surface: #1e293b; 
            --c-surface-hover: #334155; 
            --c-primary: #10b981; 
            --c-primary-glow: rgba(16, 185, 129, 0.4);
            --c-accent: #3b82f6; 
            --c-danger: #ef4444; 
            --c-warning: #f59e0b; 
            --c-text-main: #f8fafc; 
            --c-text-muted: #94a3b8; 
            --c-border: rgba(255, 255, 255, 0.08); 
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--c-bg); 
            color: var(--c-text-main); 
            font-size: 14px; 
            min-height: 100vh; 
            background-image: radial-gradient(circle at 50% 0%, rgba(30, 41, 59, 1) 0%, rgba(11, 17, 32, 1) 60%);
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .mono { font-family: 'JetBrains Mono', monospace; }

        /* Navbar Glassmorphism */
        .glass-nav { 
            background: rgba(11, 17, 32, 0.7); 
            backdrop-filter: blur(12px); 
            border-bottom: 1px solid var(--c-border); 
            height: 64px; 
            position: sticky; top: 0; z-index: 1000;
        }
        
        .brand-icon {
            width: 24px; height: 24px; 
            background: linear-gradient(135deg, #10b981, #059669); 
            border-radius: 6px;
            box-shadow: 0 0 12px var(--c-primary-glow);
        }

        /* Thẻ Glass Card */
        .glass-card { 
            background: rgba(30, 41, 59, 0.4); 
            backdrop-filter: blur(8px);
            border: 1px solid var(--c-border); 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .glass-card:hover { border-color: rgba(255,255,255,0.15); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2); }
        .glass-header { background: rgba(0, 0, 0, 0.2); border-bottom: 1px solid var(--c-border); padding: 14px 20px; font-weight: 600; letter-spacing: 0.5px; }

        /* Stat Cards */
        .stat-val { font-size: 2rem; font-weight: 700; line-height: 1.2; font-family: 'JetBrains Mono', monospace; }
        .stat-label { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; color: var(--c-text-muted); font-weight: 600; }
        
        .border-neon-success { border-left: 4px solid var(--c-primary); }
        .border-neon-warning { border-left: 4px solid var(--c-warning); }
        .border-neon-info { border-left: 4px solid var(--c-accent); }
        .border-neon-primary { border-left: 4px solid #8b5cf6; }

        /* Form Controls */
        .form-control, .form-select { 
            background-color: rgba(15, 23, 42, 0.6); 
            border: 1px solid var(--c-border); 
            color: var(--c-text-main); 
            border-radius: 8px;
            padding: 10px 14px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus { 
            background-color: rgba(15, 23, 42, 0.9); 
            border-color: var(--c-primary); 
            color: #fff; 
            box-shadow: 0 0 0 3px var(--c-primary-glow); 
        }

        /* Buttons */
        .btn-cyber { 
            background: linear-gradient(to right, #10b981, #059669); 
            color: #fff; font-weight: 600; border: none; border-radius: 8px; 
            padding: 10px 16px; transition: all 0.2s; 
        }
        .btn-cyber:hover { transform: translateY(-1px); box-shadow: 0 4px 12px var(--c-primary-glow); color: #fff; }
        
        .btn-outline-glass { border: 1px solid var(--c-border); color: var(--c-text-muted); background: transparent; border-radius: 6px; }
        .btn-outline-glass:hover { border-color: var(--c-text-main); color: var(--c-text-main); background: rgba(255,255,255,0.05); }

        /* Table Design */
        .table-custom { color: var(--c-text-main); margin-bottom: 0; }
        .table-custom th { background: rgba(0, 0, 0, 0.2); color: var(--c-text-muted); border-bottom: 1px solid var(--c-border); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; padding: 14px 16px; }
        .table-custom td { background: transparent; color: var(--c-text-main); border-bottom: 1px solid var(--c-border); padding: 14px 16px; vertical-align: middle; }
        .table-custom tbody tr { transition: background-color 0.15s; }
        .table-custom tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }

        /* Badges */
        .badge-cyber { padding: 4px 8px; border-radius: 4px; font-weight: 600; letter-spacing: 0.5px; font-size: 0.7rem; text-transform: uppercase; }
        .bg-admin { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }
        .bg-teacher { background: rgba(245, 158, 11, 0.15); color: #fcd34d; border: 1px solid rgba(245, 158, 11, 0.3); }
        .bg-student { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.3); }

        /* Status Dot */
        .pulse-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; }
        .dot-on { background-color: #10b981; box-shadow: 0 0 10px #10b981; animation: pulse 2s infinite; }
        .dot-off { background-color: #ef4444; box-shadow: 0 0 10px #ef4444; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

        /* Alerts */
        .alert-glass { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #6ee7b7; backdrop-filter: blur(4px); }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }

        /* Custom Scrollbar cho bảng */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar glass-nav px-4 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="brand-icon"></div>
        <span class="fw-bold text-white fs-5 tracking-wide">Trung Tâm Quản Trị OJ</span>
    </div>
    <div class="d-flex gap-3 align-items-center">
        <div class="d-none d-md-flex flex-column text-end me-3" style="line-height: 1.2;">
            <span class="small text-white fw-bold"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <span class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Quản trị viên hệ thống</span>
        </div>
        <a href="teacher.php" class="btn btn-outline-glass btn-sm px-3 py-2">Quản lý Đề</a>
        <a href="index.php" class="btn btn-outline-glass btn-sm px-3 py-2">Luyện tập</a>
        <a href="logout.php" class="btn btn-cyber btn-sm px-3 py-2" style="background: rgba(239,68,68,0.2); color: #fca5a5; box-shadow: none;">Đăng xuất</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5 max-w-7xl mx-auto" style="max-width: 1400px;">
    
    <!-- Thông báo -->
    <?php if ($message): ?>
        <div class="alert alert-glass d-flex align-items-center gap-2 py-3 mb-4 rounded-3 shadow-sm border-0">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
            <span class="fw-medium"><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error d-flex align-items-center gap-2 py-3 mb-4 rounded-3 shadow-sm border-0">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/></svg>
            <span class="fw-medium"><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <!-- THỐNG KÊ VÀ GIÁM SÁT HỆ THỐNG -->
    <div class="row g-4 mb-5">
        <!-- Sandbox Status -->
        <div class="col-md-3">
            <div class="glass-card p-4 border-neon-success h-100 d-flex flex-column justify-content-between">
                <div class="stat-label">Dịch vụ Thực thi (Cổng 4000)</div>
                <div class="mt-3 d-flex justify-content-between align-items-end">
                    <div class="stat-val <?= $sandboxStatus === 'ONLINE' ? 'text-white' : 'text-danger' ?>">
                        <span class="pulse-dot <?= $sandboxStatus === 'ONLINE' ? 'dot-on' : 'dot-off' ?>"></span>
                        <?= $sandboxStatus ?>
                    </div>
                    <?php if ($sandboxStatus === 'ONLINE'): ?>
                        <span class="badge" style="background: rgba(255,255,255,0.1); color: var(--c-text-muted); font-family: 'JetBrains Mono';"><?= $pingTime ?>ms</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hàng đợi & Lỗi -->
        <div class="col-md-3">
            <div class="glass-card p-4 border-neon-warning h-100 d-flex flex-column justify-content-between">
                <div class="stat-label">Hàng Đợi Lỗi Hệ Thống</div>
                <div class="mt-3 d-flex justify-content-between align-items-end">
                    <div class="stat-val text-white"><?= $sysErrorCount ?> <span class="fs-6 fw-normal text-muted font-sans">bài kẹt</span></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="retry_system_error">
                        <button type="submit" class="btn btn-sm btn-outline-glass" style="border-color: rgba(245,158,11,0.3); color: #fcd34d;" <?= $sysErrorCount == 0 ? 'disabled' : '' ?>>
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>
                            Thử lại
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Dữ liệu bài tập & Bài nộp -->
        <div class="col-md-3">
            <div class="glass-card p-4 border-neon-info h-100 d-flex flex-column justify-content-between">
                <div class="stat-label">Tổng Số Bài Nộp</div>
                <div class="mt-3 d-flex align-items-baseline gap-2">
                    <div class="stat-val text-white"><?= number_format($totalSubmissions) ?></div>
                    <span class="text-muted small">/ <?= $totalProblems ?> Đề bài</span>
                </div>
            </div>
        </div>

        <!-- Người dùng -->
        <div class="col-md-3">
            <div class="glass-card p-4 border-neon-primary h-100 d-flex flex-column justify-content-between">
                <div class="stat-label">Phân Bổ Người Dùng</div>
                <div class="mt-3 d-flex gap-2">
                    <div class="flex-fill text-center p-2 rounded" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2);">
                        <div class="mono fw-bold" style="color: #6ee7b7;"><?= $studentCount ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Học viên</div>
                    </div>
                    <div class="flex-fill text-center p-2 rounded" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2);">
                        <div class="mono fw-bold" style="color: #fcd34d;"><?= $teacherCount ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Giảng viên</div>
                    </div>
                    <div class="flex-fill text-center p-2 rounded" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);">
                        <div class="mono fw-bold" style="color: #fca5a5;"><?= $adminCount ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Quản trị</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QUẢN LÝ NGƯỜI DÙNG -->
    <div class="row g-4">
        <!-- Form thêm tài khoản -->
        <div class="col-lg-3">
            <div class="glass-card">
                <div class="glass-header d-flex align-items-center gap-2">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg>
                    Tạo Tài Khoản
                </div>
                <div class="p-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label class="small text-muted mb-2 fw-semibold">Tên đăng nhập</label>
                            <input type="text" name="username" class="form-control" required autocomplete="off" placeholder="nguyenvana">
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted mb-2 fw-semibold">Mật khẩu</label>
                            <input type="text" name="password" class="form-control mono" value="123456" required>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted mb-2 fw-semibold">Họ và tên</label>
                            <input type="text" name="full_name" class="form-control" required autocomplete="off" placeholder="Nguyễn Văn A">
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted mb-2 fw-semibold">Vai trò</label>
                            <select name="role" class="form-select">
                                <option value="student">Học viên</option>
                                <option value="teacher">Giảng viên</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="small text-muted mb-2 fw-semibold">Mã lớp</label>
                            <input type="text" name="class_code" class="form-control mono text-uppercase" value="CNTT18-11">
                        </div>
                        <button type="submit" class="btn btn-cyber w-100 d-flex justify-content-center align-items-center gap-2">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/></svg>
                            Tạo Tài Khoản
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bảng danh sách người dùng -->
        <div class="col-lg-9">
            <div class="glass-card">
                <div class="glass-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg>
                        Danh Sách Người Dùng
                    </div>
                    <span class="badge rounded-pill bg-dark border border-secondary text-muted px-3 py-1 mono">Tổng: <?= count($users) ?></span>
                </div>
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-custom align-middle">
                        <thead class="sticky-top" style="z-index: 10;">
                            <tr>
                                <th class="ps-4" style="width: 80px;">ID</th>
                                <th>Tài khoản / Họ Tên</th>
                                <th>Vai trò</th>
                                <th>Lớp</th>
                                <th>Trạng thái</th>
                                <th class="text-end pe-4">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-4 mono text-muted">#<?= $u['id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-white mb-1"><?= htmlspecialchars($u['username']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3Zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg><?= htmlspecialchars($u['full_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge-cyber <?= $u['role'] === 'admin' ? 'bg-admin' : ($u['role'] === 'teacher' ? 'bg-teacher' : 'bg-student') ?>">
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </td>
                                    <td class="mono" style="font-size: 0.8rem;"><?= htmlspecialchars($u['class_code']) ?></td>
                                    <td>
                                        <?php if ($u['is_locked']): ?>
                                            <span class="badge rounded-pill" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); padding: 4px 10px; font-size: 0.7rem;"><span class="pulse-dot dot-off" style="width:6px; height:6px; margin-right:4px;"></span>BỊ KHÓA</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill" style="background: rgba(255,255,255,0.05); color: #94a3b8; border: 1px solid rgba(255,255,255,0.1); padding: 4px 10px; font-size: 0.7rem;"><span class="pulse-dot dot-on" style="width:6px; height:6px; margin-right:4px; box-shadow: none; animation:none;"></span>HOẠT ĐỘNG</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-glass" style="padding: 4px 8px; font-size: 0.75rem;" title="Sửa thông tin"
                                                onclick="openEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', '<?= $u['role'] ?>', '<?= htmlspecialchars($u['class_code'], ENT_QUOTES) ?>')">
                                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/></svg>
                                            </button>
                                            
                                            <form method="POST" class="d-inline m-0 p-0">
                                                <input type="hidden" name="action" value="toggle_lock">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-glass" style="padding: 4px 8px; font-size: 0.75rem; <?= $u['is_locked'] ? 'color:#10b981; border-color:rgba(16,185,129,0.3);' : 'color:#f59e0b; border-color:rgba(245,158,11,0.3);' ?>" title="<?= $u['is_locked'] ? 'Mở khóa tài khoản' : 'Khóa tài khoản' ?>"
                                                    onclick="return confirm('Xác nhận thay đổi trạng thái khóa tài khoản này?');">
                                                    <?php if($u['is_locked']): ?>
                                                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M11 1a2 2 0 0 0-2 2v4a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h5V3a3 3 0 0 1 6 0v4a.5.5 0 0 1-1 0V3a2 2 0 0 0-2-2zM3 8a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1H3z"/></svg>
                                                    <?php else: ?>
                                                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/></svg>
                                                    <?php endif; ?>
                                                </button>
                                            </form>

                                            <form method="POST" class="d-inline m-0 p-0">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-glass" style="padding: 4px 8px; font-size: 0.75rem; color:#ef4444; border-color:rgba(239,68,68,0.3);" title="Khôi phục mật khẩu mặc định (123456)"
                                                    onclick="return confirm('Mật khẩu sẽ bị reset về 123456. Xác nhận?');">
                                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M3.5 11.5a3.5 3.5 0 1 1 3.163-5h1.23l2.844 2.844v.656h-1v-1l-2.5-2.5h-1a3.488 3.488 0 0 1-.36 1.704l2.505 2.505.707-.707-2.5-2.5h-1.23a3.5 3.5 0 0 1-5.163 1zM2.5 9a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sửa Thông Tin Nổi -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content glass-card" style="background: rgba(30,41,59,0.95); box-shadow: 0 0 40px rgba(0,0,0,0.5);">
      <form method="POST">
          <div class="modal-header border-bottom border-secondary py-3">
            <h6 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="text-primary"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                Sửa Thông Tin Tài Khoản
            </h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body py-4">
              <input type="hidden" name="action" value="edit_user">
              <input type="hidden" name="user_id" id="edit_user_id">
              
              <div class="mb-3">
                  <label class="small text-muted mb-1 fw-medium">Họ và tên</label>
                  <input type="text" name="full_name" id="edit_full_name" class="form-control form-control-sm" required>
              </div>
              <div class="mb-3">
                  <label class="small text-muted mb-1 fw-medium">Vai trò</label>
                  <select name="role" id="edit_role" class="form-select form-select-sm">
                      <option value="student">Học viên</option>
                      <option value="teacher">Giảng viên</option>
                      <option value="admin">Quản trị viên</option>
                  </select>
              </div>
              <div class="mb-2">
                  <label class="small text-muted mb-1 fw-medium">Mã lớp</label>
                  <input type="text" name="class_code" id="edit_class_code" class="form-control form-control-sm mono text-uppercase">
              </div>
          </div>
          <div class="modal-footer border-top border-secondary py-2">
            <button type="button" class="btn btn-outline-glass btn-sm" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-cyber btn-sm">Lưu Thay Đổi</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openEditModal(id, fullName, role, classCode) {
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_full_name').value = fullName;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_class_code').value = classCode;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
</script>
</body>
</html>