<?php
// public/teacher.php - Giao diện Quản lý đề bài dành cho Giảng viên (HackerRank Style)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$currentUserId   = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];
$currentFullName = $_SESSION['full_name'] ?? $currentUsername;

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

$message = '';
// Xử lý thêm mới bài tập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_problem') {
        $title       = trim($_POST['title'] ?? '');
        $topic       = trim($_POST['topic'] ?? '');
        $difficulty  = $_POST['difficulty'] ?? 'easy';
        $description = trim($_POST['description'] ?? '');
        $ioDesc      = trim($_POST['io_description'] ?? '');
        $source      = trim($_POST['source_credit'] ?? 'Nhóm 11');
        $timeLimit   = (int)($_POST['time_limit_ms'] ?? 1000);
        $testStdin   = $_POST['test_stdin'] ?? [];
        $testStdout  = $_POST['test_stdout'] ?? [];
        $testPublic  = $_POST['test_public'] ?? [];

        if ($title !== '') {
            $pdo->beginTransaction();
            try {
                // 1. Thêm vào bảng problems
                $stmtProb = $pdo->prepare("
                    INSERT INTO problems (title, topic, difficulty, description, io_description, public_examples, source_credit)
                    VALUES (?, ?, ?, ?, ?, '[]', ?)
                ");
                $publicExJson = json_encode([[
                    'stdin' => $testStdin[0] ?? '3 5',
                    'stdout' => $testStdout[0] ?? '8'
                ]], JSON_UNESCAPED_UNICODE);

                $stmtProb->execute([$title, $topic, $difficulty, $description, $ioDesc, $source]);
                $problemId = (int)$pdo->lastInsertId();

                $pdo->prepare("UPDATE problems SET public_examples = ? WHERE id = ?")->execute([$publicExJson, $problemId]);

                // 2. Tạo phiên bản bộ dữ liệu đầu tiên (problem_versions)
                $stmtVer = $pdo->prepare("
                    INSERT INTO problem_versions (problem_id, version_number, time_limit_ms, memory_limit_kb, created_by)
                    VALUES (?, 1, ?, 65536, ?)
                ");
                $stmtVer->execute([$problemId, $timeLimit, $currentUserId]);
                $versionId = (int)$pdo->lastInsertId();

                $pdo->prepare("UPDATE problems SET current_version_id = ? WHERE id = ?")->execute([$versionId, $problemId]);

                // 3. Thêm các test cases
                $stmtTc = $pdo->prepare("
                    INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($testStdin as $idx => $stdinVal) {
                    if (trim($stdinVal) !== '') {
                        $stmtTc->execute([
                            $versionId,
                            $idx + 1,
                            $stdinVal,
                            $testStdout[$idx] ?? '',
                            isset($testPublic[$idx]) ? 1 : 0
                        ]);
                    }
                }

                $pdo->commit();
                $message = "✓ Thêm bài tập mới thành công (ID: #$problemId)!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Lỗi: " . $e->getMessage();
            }
        }
    }
}

$problems = $pdo->query("
    SELECT p.*, pv.version_number, pv.time_limit_ms
    FROM problems p
    LEFT JOIN problem_versions pv ON pv.id = p.current_version_id
    ORDER BY p.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý đề bài - Giảng viên (HackerRank Style)</title>
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

        /* Card & Panels */
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
            font-size: 0.82rem;
        }
        .form-control, .form-select {
            background-color: #0b0f17;
            border: 1px solid var(--hr-border);
            color: #ffffff;
            font-size: 0.88rem;
        }
        .form-control:focus, .form-select:focus {
            background-color: #0b0f17;
            border-color: var(--hr-green);
            color: #ffffff;
            box-shadow: 0 0 0 2px rgba(0, 234, 100, 0.2);
        }

        /* Buttons */
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

        /* Table */
        .table {
            color: #e2e8f0;
            border-color: var(--hr-border);
            margin-bottom: 0;
        }
        .table-dark-custom th {
            background-color: #141a24;
            color: #94a3b8;
            border-bottom: 1px solid var(--hr-border);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
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

        .alert-success {
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
        <span>HackerRank Teacher</span>
        <span class="text-secondary fw-normal fs-6 ms-2">| Cổng Quản lý Đề bài (Giảng viên)</span>
    </div>
    <div class="d-flex align-items-center gap-3 text-white">
        <span class="small text-secondary">Giảng viên: <strong class="text-white"><?= htmlspecialchars($currentFullName) ?></strong></span>
        <a href="index.php" class="btn btn-outline-light btn-sm py-1 px-3">Trang Luyện tập</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm py-1 px-3">Đăng xuất</a>
    </div>
</nav>

<div class="container pb-5">
    <?php if ($message !== ''): ?>
        <div class="alert alert-success fw-bold py-2 mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Thêm bài tập mới -->
        <div class="col-lg-5">
            <div class="hr-card shadow-sm">
                <div class="hr-card-header">Thêm Bài tập mới & Bộ dữ liệu kiểm thử</div>
                <div class="card-body p-3">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_problem">
                        
                        <div class="mb-2">
                            <label class="form-label fw-bold">Tiêu đề bài tập:</label>
                            <input type="text" name="title" class="form-control form-control-sm" required placeholder="VD: Bài 11: Kiểm tra chuỗi đối xứng">
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Chủ đề:</label>
                                <input type="text" name="topic" class="form-control form-control-sm" required placeholder="VD: Xử lý chuỗi">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Mức độ:</label>
                                <select name="difficulty" class="form-select form-select-sm">
                                    <option value="easy">Dễ (Easy)</option>
                                    <option value="medium">Trung bình (Medium)</option>
                                    <option value="hard">Khó (Hard)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold">Mô tả bài toán:</label>
                            <textarea name="description" class="form-control form-control-sm font-monospace" rows="3" required placeholder="Nội dung đề bài..."></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold">Định dạng Đầu vào / Đầu ra (IO Description):</label>
                            <textarea name="io_description" class="form-control form-control-sm font-monospace" rows="2" required placeholder="Đầu vào: ... Đầu ra: ..."></textarea>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Giới hạn thời gian (ms):</label>
                                <input type="number" name="time_limit_ms" class="form-control form-control-sm" value="1000" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nguồn sử dụng:</label>
                                <input type="text" name="source_credit" class="form-control form-control-sm" value="Nhóm 11 tự biên soạn">
                            </div>
                        </div>

                        <hr class="my-3 border-secondary">
                        <label class="form-label fw-bold text-success">Bộ ca kiểm thử (Test Cases):</label>
                        
                        <div id="testCasesContainer">
                            <div class="border border-secondary rounded p-2 mb-2 bg-dark test-case-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold text-warning">Test Case #1</span>
                                    <label class="small text-secondary"><input type="checkbox" name="test_public[0]" value="1" checked> Ví dụ công khai</label>
                                </div>
                                <div class="row g-1">
                                    <div class="col-6"><textarea name="test_stdin[]" class="form-control form-control-sm font-monospace" rows="2" placeholder="Input (stdin)" required>3 5\n</textarea></div>
                                    <div class="col-6"><textarea name="test_stdout[]" class="form-control form-control-sm font-monospace" rows="2" placeholder="Expected Output" required>8\n</textarea></div>
                                </div>
                            </div>
                            <div class="border border-secondary rounded p-2 mb-2 bg-dark test-case-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold text-warning">Test Case #2</span>
                                    <label class="small text-secondary"><input type="checkbox" name="test_public[1]" value="1" checked> Ví dụ công khai</label>
                                </div>
                                <div class="row g-1">
                                    <div class="col-6"><textarea name="test_stdin[]" class="form-control form-control-sm font-monospace" rows="2" placeholder="Input (stdin)" required>-10 10\n</textarea></div>
                                    <div class="col-6"><textarea name="test_stdout[]" class="form-control form-control-sm font-monospace" rows="2" placeholder="Expected Output" required>0\n</textarea></div>
                                </div>
                            </div>
                            <div class="border border-secondary rounded p-2 mb-2 bg-dark test-case-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold text-warning">Test Case #3 (Chấm kín)</span>
                                    <label class="small text-secondary"><input type="checkbox" name="test_public[2]" value="1"> Ví dụ công khai</label>
                                </div>
                                <div class="row g-1">
                                    <div class="col-6"><textarea name="test_stdin[]" class="form-control form-control-sm font-monospace" rows="2" placeholder="Input (stdin)" required>0 0\n</textarea></div>
                                    <div class="col-6"><textarea name="test_stdout[]" class="form-control form-control-sm font-monospace" rows="2" placeholder="Expected Output" required>0\n</textarea></div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-hr-primary btn-sm w-100 fw-bold mt-2 py-2">Lưu Bài tập & Nạp Test Cases</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Danh sách bài tập hiện có -->
        <div class="col-lg-7">
            <div class="hr-card shadow-sm overflow-hidden">
                <div class="hr-card-header d-flex justify-content-between align-items-center">
                    <span>Danh sách Bài tập hiện tại trên Hệ thống</span>
                    <span class="badge bg-secondary font-monospace">Tổng: <?= count($problems) ?> bài</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th class="ps-3">ID</th>
                                <th>Tiêu đề bài tập</th>
                                <th>Chủ đề</th>
                                <th>Mức độ</th>
                                <th>Phiên bản</th>
                                <th class="text-end pe-3">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($problems as $p): ?>
                                <tr>
                                    <td class="ps-3 fw-bold font-monospace">#<?= $p['id'] ?></td>
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($p['title']) ?></td>
                                    <td><?= htmlspecialchars($p['topic']) ?></td>
                                    <td><span class="badge bg-secondary"><?= ucfirst($p['difficulty']) ?></span></td>
                                    <td>v<?= $p['version_number'] ?? 1 ?> (<?= $p['time_limit_ms'] ?>ms)</td>
                                    <td class="text-end pe-3">
                                        <a href="index.php?problem_id=<?= $p['id'] ?>" class="btn btn-sm btn-hr-primary py-0 px-2" target="_blank">Làm bài</a>
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
</body>
</html>