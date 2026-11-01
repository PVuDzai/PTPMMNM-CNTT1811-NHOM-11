<?php
// public/teacher.php - Quản lý Đề bài, Test Cases động & Versioning (CyberRank Glassmorphism Style - Việt Hóa 100%)
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'])) {
    header('Location: login.php');
    exit;
}

$currentUserId   = (int)$_SESSION['user_id'];
$currentFullName = $_SESSION['full_name'] ?? $_SESSION['username'];
$currentUserRole = $_SESSION['role'];

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();
$message = '';
$errorMsg = '';

// -----------------------------------------------------------------------------
// 1. API TRẢ VỀ DỮ LIỆU ĐỀ BÀI ĐỂ NẠP LÊN FORM BẰNG AJAX
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_problem') {
    header('Content-Type: application/json');
    $pid = (int)$_GET['id'];
    
    $stmtP = $pdo->prepare("SELECT p.*, pv.time_limit_ms FROM problems p LEFT JOIN problem_versions pv ON pv.id = p.current_version_id WHERE p.id = ?");
    $stmtP->execute([$pid]);
    $prob = $stmtP->fetch(PDO::FETCH_ASSOC);
    
    if ($prob) {
        $stmtTc = $pdo->prepare("SELECT stdin_data, expected_stdout, is_public FROM test_cases WHERE problem_version_id = ? ORDER BY case_order ASC");
        $stmtTc->execute([$prob['current_version_id']]);
        $prob['test_cases'] = $stmtTc->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $prob]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy bài tập']);
    }
    exit;
}

// -----------------------------------------------------------------------------
// 2. XỬ LÝ LƯU ĐỀ BÀI & TEST CASES (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_problem') {
        $delId = (int)($_POST['problem_id'] ?? 0);
        if ($delId > 0) {
            $pdo->prepare("DELETE FROM problems WHERE id = ?")->execute([$delId]);
            $message = "Đã xóa bài tập #$delId khỏi hệ thống!";
        }
    } elseif ($action === 'save_problem') {
        $problemId   = (int)($_POST['problem_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $topic       = trim($_POST['topic'] ?? '');
        $difficulty  = $_POST['difficulty'] ?? 'easy';
        $description = trim($_POST['description'] ?? '');
        $ioDesc      = trim($_POST['io_description'] ?? '');
        $timeLimit   = (int)($_POST['time_limit_ms'] ?? 1000);
        $updateMode  = $_POST['update_mode'] ?? 'text_only';
        
        $testStdin   = $_POST['test_stdin'] ?? [];
        $testStdout  = $_POST['test_stdout'] ?? [];
        $testPublic  = $_POST['test_public'] ?? [];

        $publicExamples = [];
        foreach ($testStdin as $idx => $stdinVal) {
            if (trim($stdinVal) !== '' && isset($testPublic[$idx])) {
                $publicExamples[] = [
                    'stdin'  => str_replace("\\n", "\n", $stdinVal),
                    'stdout' => str_replace("\\n", "\n", $testStdout[$idx] ?? '')
                ];
            }
        }
        $publicExJson = json_encode($publicExamples, JSON_UNESCAPED_UNICODE);

        if ($title !== '') {
            $pdo->beginTransaction();
            try {
                if ($problemId === 0) {
                    $stmtProb = $pdo->prepare("INSERT INTO problems (title, topic, difficulty, description, io_description, public_examples) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtProb->execute([$title, $topic, $difficulty, $description, $ioDesc, $publicExJson]);
                    $problemId = (int)$pdo->lastInsertId();

                    $stmtVer = $pdo->prepare("INSERT INTO problem_versions (problem_id, version_number, time_limit_ms, memory_limit_kb, created_by) VALUES (?, 1, ?, 65536, ?)");
                    $stmtVer->execute([$problemId, $timeLimit, $currentUserId]);
                    $versionId = (int)$pdo->lastInsertId();

                    $pdo->prepare("UPDATE problems SET current_version_id = ? WHERE id = ?")->execute([$versionId, $problemId]);

                    $stmtTc = $pdo->prepare("INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES (?, ?, ?, ?, ?)");
                    $order = 1;
                    foreach ($testStdin as $idx => $stdinVal) {
                        if (trim($stdinVal) !== '') {
                            $stmtTc->execute([$versionId, $order++, str_replace("\\n", "\n", $stdinVal), str_replace("\\n", "\n", $testStdout[$idx] ?? ''), isset($testPublic[$idx]) ? 1 : 0]);
                        }
                    }
                    $message = "Đã thêm bài tập mới (ID: #$problemId)!";
                } else {
                    $stmtUpd = $pdo->prepare("UPDATE problems SET title = ?, topic = ?, difficulty = ?, description = ?, io_description = ?, public_examples = ? WHERE id = ?");
                    $stmtUpd->execute([$title, $topic, $difficulty, $description, $ioDesc, $publicExJson, $problemId]);

                    if ($updateMode === 'new_version_rejudge') {
                        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 FROM problem_versions WHERE problem_id = ?");
                        $stmtMax->execute([$problemId]);
                        $nextVerNum = (int)$stmtMax->fetchColumn();

                        $stmtVer = $pdo->prepare("INSERT INTO problem_versions (problem_id, version_number, time_limit_ms, memory_limit_kb, created_by) VALUES (?, ?, ?, 65536, ?)");
                        $stmtVer->execute([$problemId, $nextVerNum, $timeLimit, $currentUserId]);
                        $newVersionId = (int)$pdo->lastInsertId();

                        $pdo->prepare("UPDATE problems SET current_version_id = ? WHERE id = ?")->execute([$newVersionId, $problemId]);

                        $stmtTc = $pdo->prepare("INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public) VALUES (?, ?, ?, ?, ?)");
                        $order = 1;
                        foreach ($testStdin as $idx => $stdinVal) {
                            if (trim($stdinVal) !== '') {
                                $stmtTc->execute([$newVersionId, $order++, str_replace("\\n", "\n", $stdinVal), str_replace("\\n", "\n", $testStdout[$idx] ?? ''), isset($testPublic[$idx]) ? 1 : 0]);
                            }
                        }

                        $stmtSubs = $pdo->prepare("SELECT id FROM submissions WHERE problem_id = ?");
                        $stmtSubs->execute([$problemId]);
                        $rejudgedCount = 0;
                        foreach ($stmtSubs->fetchAll() as $s) {
                            $pdo->prepare("INSERT INTO judging_runs (submission_id, problem_version_id, is_rejudge, status) VALUES (?, ?, TRUE, 'queued')")->execute([$s['id'], $newVersionId]);
                            $newRunId = (int)$pdo->lastInsertId();
                            $pdo->prepare("UPDATE submissions SET latest_run_id = ? WHERE id = ?")->execute([$newRunId, $s['id']]);
                            $rejudgedCount++;
                        }
                        $message = "Đã tạo Bộ Test v$nextVerNum và đưa $rejudgedCount bài nộp vào hàng đợi chấm lại!";
                    } else {
                        $message = "Đã cập nhật nội dung văn bản đề bài. Không thay đổi bộ test cũ.";
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = "Lỗi hệ thống: " . $e->getMessage();
            }
        }
    }
}

$problems = $pdo->query("SELECT p.*, pv.version_number, pv.time_limit_ms FROM problems p LEFT JOIN problem_versions pv ON pv.id = p.current_version_id ORDER BY p.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Kho Đề | CyberRank Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --c-bg: #0b1120; 
            --c-surface: #1e293b; 
            --c-primary: #10b981; 
            --c-primary-glow: rgba(16, 185, 129, 0.4);
            --c-accent: #3b82f6; 
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
        }
        .glass-header { background: rgba(0, 0, 0, 0.2); border-bottom: 1px solid var(--c-border); padding: 14px 20px; font-weight: 600; letter-spacing: 0.5px; }

        /* Form Controls */
        .form-label { color: var(--c-text-muted); font-weight: 600; font-size: 0.85rem; letter-spacing: 0.3px; }
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

        /* Nút bấm (Buttons) */
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
        .table-custom th { background-color: #0f172a; color: var(--c-text-muted); border-bottom: 1px solid var(--c-border); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; padding: 14px 16px; }
        .table-custom td { background-color: #1e293b; color: var(--c-text-main); border-bottom: 1px solid var(--c-border); padding: 14px 16px; vertical-align: middle; }
        .table-custom tbody tr:hover td { background-color: #334155; }

        /* Test Case Items */
        .test-case-item { 
            background: rgba(0,0,0,0.2); 
            border: 1px dashed var(--c-border); 
            border-radius: 8px; padding: 16px; 
            margin-bottom: 12px; position: relative; 
            transition: border-color 0.2s;
        }
        .test-case-item:hover { border-color: rgba(255,255,255,0.2); }
        .btn-remove-tc { position: absolute; top: 12px; right: 12px; font-size: 0.75rem; padding: 4px 10px; background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; transition: all 0.2s;}
        .btn-remove-tc:hover { background: rgba(239, 68, 68, 0.8); color: #fff; }

        /* Alerts */
        .alert-glass { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #6ee7b7; backdrop-filter: blur(4px); }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }

        .diff-easy { color: #6ee7b7; font-weight: 700; }
        .diff-medium { color: #fcd34d; font-weight: 700; }
        .diff-hard { color: #fca5a5; font-weight: 700; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
    </style>
</head>
<body>

<nav class="navbar glass-nav px-4 mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="brand-icon"></div>
        <span class="fw-bold text-white fs-5 tracking-wide">CyberRank Teacher</span>
    </div>
    <div class="d-flex gap-3 align-items-center">
        <div class="d-none d-md-flex flex-column text-end me-3" style="line-height: 1.2;">
            <span class="small text-white fw-bold"><?= htmlspecialchars($currentFullName) ?></span>
            <span class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Giảng viên hệ thống</span>
        </div>
        <?php if ($currentUserRole === 'admin'): ?>
            <a href="admin.php" class="btn btn-outline-glass btn-sm px-3 py-2">Trung tâm Quản trị</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-glass btn-sm px-3 py-2">Khu vực Luyện tập</a>
        <a href="logout.php" class="btn btn-cyber btn-sm px-3 py-2" style="background: rgba(239,68,68,0.2); color: #fca5a5; box-shadow: none;">Đăng xuất</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5 max-w-7xl mx-auto" style="max-width: 1500px;">
    
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

    <div class="row g-4">
        <!-- Form Soạn thảo Đề & Test Cases -->
        <div class="col-lg-5">
            <div class="glass-card h-100 d-flex flex-column">
                <div class="glass-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="text-primary"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
                        <span id="formTitle">Soạn Thảo Đề Mới</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-glass py-1 px-3 text-muted" onclick="resetForm()">Làm mới Form</button>
                </div>
                <div class="p-4 flex-grow-1 overflow-auto" style="max-height: calc(100vh - 180px);">
                    <form method="POST" id="problemForm">
                        <input type="hidden" name="action" value="save_problem">
                        <input type="hidden" name="problem_id" id="problem_id" value="0">
                        
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề bài tập</label>
                            <input type="text" name="title" id="title" class="form-control" placeholder="VD: Tính tổng 2 số nguyên" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Chủ đề (Topic)</label>
                                <input type="text" name="topic" id="topic" class="form-control" placeholder="VD: Toán học" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Mức độ khó</label>
                                <select name="difficulty" id="difficulty" class="form-select">
                                    <option value="easy">Dễ (Easy)</option>
                                    <option value="medium">Trung bình (Medium)</option>
                                    <option value="hard">Khó (Hard)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô tả bài toán chi tiết</label>
                            <textarea name="description" id="description" class="form-control" rows="5" placeholder="Nhập đề bài tại đây..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Định dạng Đầu vào / Đầu ra (I/O Format)</label>
                            <textarea name="io_description" id="io_description" class="form-control" rows="3" placeholder="Input: ... Output: ..." required></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Giới hạn thời gian chạy (ms)</label>
                            <input type="number" name="time_limit_ms" id="time_limit_ms" class="form-control mono text-primary fw-bold" value="1000" required>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-5 mb-3 border-bottom pb-2" style="border-color: var(--c-border) !important;">
                            <label class="form-label text-primary mb-0 d-flex align-items-center gap-2">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2H2zm0 2h12v1H2V3zm0 2h12v7H2V5zm3.5 1.5a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2a.5.5 0 0 1 .5-.5zM8 6.5a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2a.5.5 0 0 1 .5-.5zm3.5 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2a.5.5 0 0 1 .5-.5z"/></svg>
                                Bộ Dữ Liệu Kiểm Thử (Test Cases)
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-glass py-1 px-3 text-primary" style="border-color: rgba(16,185,129,0.3);" onclick="addTestCaseRow()">+ Thêm Test Case</button>
                        </div>
                        
                        <div id="tcContainer">
                            <!-- JS Sẽ sinh Test Cases ở đây -->
                        </div>

                        <div class="mt-4 p-3 rounded-3" id="saveModeContainer" style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2);">
                            <label class="form-label text-warning mb-3 fw-bold d-flex align-items-center gap-2">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                                Cấu hình Lưu Trữ (Versioning)
                            </label>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="update_mode" id="mode1" value="text_only" checked>
                                <label class="form-check-label text-muted" for="mode1">
                                    <strong class="text-white">Chỉ Cập Nhật Văn Bản</strong><br>
                                    <span class="small">Lưu lại tiêu đề, mô tả đề bài. Giữ nguyên toàn bộ Test cases và kết quả chấm của sinh viên.</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="update_mode" id="mode2" value="new_version_rejudge">
                                <label class="form-check-label text-warning" for="mode2">
                                    <strong class="text-warning">Lưu Thành Bộ Test Mới & Chấm Lại</strong><br>
                                    <span class="small text-muted">Tạo phiên bản Version mới cho bộ dữ liệu. Đưa toàn bộ các bài nộp hiện tại của học viên vào hàng đợi để chấm lại từ đầu.</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-cyber w-100 fw-bold mt-4 py-2 fs-6 shadow">LƯU CẤU HÌNH ĐỀ BÀI</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danh sách bài tập -->
        <div class="col-lg-7">
            <div class="glass-card h-100 d-flex flex-column">
                <div class="glass-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2zm15 2h-4v3h4V4zm0 4h-4v3h4V8zm0 4h-4v3h3a1 1 0 0 0 1-1v-2zm-5 3v-3H6v3h4zm-5 0v-3H1v2a1 1 0 0 0 1 1h3zm-4-4h4V8H1v3zm0-4h4V4H1v3zm5-3v3h4V4H6zm4 4H6v3h4V8z"/></svg>
                        Kho Bài Tập (Ngân Hàng Đề)
                    </div>
                    <span class="badge rounded-pill bg-dark border border-secondary text-muted px-3 py-1 mono">Tổng: <?= count($problems) ?> đề</span>
                </div>
                <div class="table-responsive flex-grow-1" style="max-height: calc(100vh - 180px);">
                    <table class="table table-custom align-middle text-nowrap">
                        <thead class="sticky-top" style="z-index: 10;">
                            <tr>
                                <th class="ps-4" style="width: 80px;">ID</th>
                                <th>Nội Dung Bài Toán</th>
                                <th>Phiên Bản Dữ Liệu</th>
                                <th class="text-end pe-4">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($problems as $p): 
                                $diffClass = 'diff-' . strtolower($p['difficulty']);
                            ?>
                                <tr>
                                    <td class="ps-4 mono text-muted">#<?= $p['id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-white mb-1 fs-6"><?= htmlspecialchars($p['title']) ?></div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge" style="background: rgba(255,255,255,0.05); color: #94a3b8; border: 1px solid rgba(255,255,255,0.1); font-weight: 500;">
                                                <?= htmlspecialchars($p['topic']) ?>
                                            </span>
                                            <span class="<?= $diffClass ?> small text-uppercase">• <?= $p['difficulty'] ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill mono" style="background: rgba(59,130,246,0.15); color: #93c5fd; border: 1px solid rgba(59,130,246,0.3); padding: 6px 12px; font-size: 0.75rem;">
                                            v<?= $p['version_number'] ?? 1 ?> (<?= $p['time_limit_ms'] ?>ms)
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-glass px-3" style="color: #6ee7b7; border-color: rgba(16,185,129,0.3);" onclick="editProblem(<?= $p['id'] ?>)">Sửa Đề / Test</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn xóa hoàn toàn bài tập này cùng toàn bộ lịch sử chấm của sinh viên? Thao tác này không thể hoàn tác!');">
                                                <input type="hidden" name="action" value="delete_problem">
                                                <input type="hidden" name="problem_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-glass px-3" style="color: #fca5a5; border-color: rgba(239,68,68,0.3); border-left: none;">Xóa Bỏ</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($problems)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">Chưa có bài tập nào trong cơ sở dữ liệu.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let tcCount = 0;

    function addTestCaseRow(stdin = '', stdout = '', isPublic = false) {
        let idx = tcCount++;
        let checkedStr = isPublic ? 'checked' : '';
        let html = `
            <div class="test-case-item" id="tc_row_${idx}">
                <button type="button" class="btn-remove-tc" onclick="removeTestCaseRow(${idx})">Xóa dòng</button>
                <div class="mb-2"><span class="small fw-bold" style="color: #93c5fd;">Test Case #${idx + 1}</span></div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="test_public[${idx}]" value="1" id="pub_${idx}" ${checkedStr}>
                    <label class="form-check-label small text-muted" for="pub_${idx}">Đánh dấu là Test Công Khai (Hiển thị làm Sample ở trang học viên)</label>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="small text-muted mb-1">Dữ liệu vào (Input - stdin)</label>
                        <textarea name="test_stdin[${idx}]" class="form-control form-control-sm mono text-white" rows="2" required>${stdin}</textarea>
                    </div>
                    <div class="col-6">
                        <label class="small text-muted mb-1">Dữ liệu ra chuẩn (Expected Output)</label>
                        <textarea name="test_stdout[${idx}]" class="form-control form-control-sm mono" style="color: #6ee7b7;" rows="2" required>${stdout}</textarea>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('tcContainer').insertAdjacentHTML('beforeend', html);
    }

    function removeTestCaseRow(idx) {
        document.getElementById(`tc_row_${idx}`).remove();
    }

    function resetForm() {
        document.getElementById('problemForm').reset();
        document.getElementById('problem_id').value = 0;
        document.getElementById('formTitle').innerText = "Soạn Thảo Đề Mới";
        document.getElementById('tcContainer').innerHTML = '';
        tcCount = 0;
        addTestCaseRow('', '', true); // Row 1 mặc định công khai
        addTestCaseRow('', '', false); // Row 2 kín
    }

    async function editProblem(id) {
        const res = await fetch(`teacher.php?action=get_problem&id=${id}`);
        const json = await res.json();
        
        if (json.success) {
            const p = json.data;
            document.getElementById('problem_id').value = p.id;
            document.getElementById('formTitle').innerHTML = `Chỉnh Sửa Bài Tập <span class="text-primary">#${p.id}</span> <span class="badge rounded-pill ms-2 mono" style="background: rgba(59,130,246,0.15); color: #93c5fd; border: 1px solid rgba(59,130,246,0.3); font-size: 0.7rem;">Dataset v${p.current_version_id}</span>`;
            
            document.getElementById('title').value = p.title;
            document.getElementById('topic').value = p.topic;
            document.getElementById('difficulty').value = p.difficulty;
            document.getElementById('description').value = p.description;
            document.getElementById('io_description').value = p.io_description;
            document.getElementById('time_limit_ms').value = p.time_limit_ms;

            // Xóa test cases hiện tại trên form và render lại từ JSON
            document.getElementById('tcContainer').innerHTML = '';
            tcCount = 0;
            if (p.test_cases && p.test_cases.length > 0) {
                p.test_cases.forEach(tc => {
                    addTestCaseRow(tc.stdin_data, tc.expected_stdout, tc.is_public == 1);
                });
            } else {
                addTestCaseRow(); // Dự phòng nếu bài chưa có test
            }
            // Auto check sang chế độ Text Only khi vừa tải đề lên
            document.getElementById('mode1').checked = true;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            alert('Lỗi: ' + json.error);
        }
    }

    // Khởi tạo form trống khi vào trang
    resetForm();
</script>
</body>
</html>