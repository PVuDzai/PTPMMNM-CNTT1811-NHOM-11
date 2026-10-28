<?php
// public/index.php - Giao diện phong cách HackerRank tích hợp Session
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId   = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];
$currentFullName = $_SESSION['full_name'] ?? $currentUsername;
$currentUserRole = $_SESSION['role']; // 'student', 'teacher', 'admin'

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();
$problems = $pdo->query("
    SELECT p.*, pv.version_number, pv.time_limit_ms
    FROM problems p
    LEFT JOIN problem_versions pv ON pv.id = p.current_version_id
    ORDER BY p.id ASC
")->fetchAll();

// Lấy ID bài tập đang chọn trên URL (mặc định là bài đầu tiên nếu chưa chọn)
$selectedProblemId = (int)($_GET['problem_id'] ?? ($problems[0]['id'] ?? 1));
$currentProblem = $problems[0] ?? null;
foreach ($problems as $p) {
    if ((int)$p['id'] === $selectedProblemId) {
        $currentProblem = $p;
        break;
    }
}

$examples = [];
$defaultStdin = "3 5";
if ($currentProblem) {
    $examples = json_decode($currentProblem['public_examples'] ?? '[]', true) ?: [];
    if (!empty($examples[0]['stdin'])) {
        $defaultStdin = $examples[0]['stdin'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>HackerRank Clone - Đề tài 20 (Nhóm 11)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --hr-dark: #0e141e;
            --hr-panel-dark: #1c2432;
            --hr-green: #00ea64;
            --hr-green-hover: #00c853;
            --hr-green-text: #0e141e;
            --hr-bg: #f3f7f7;
            --hr-border: #e0e4e8;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--hr-bg);
            color: #1f2937;
            font-size: 14px;
        }

        /* Thanh điều hướng chuẩn HackerRank */
        .hr-navbar {
            background-color: var(--hr-dark);
            border-bottom: 1px solid #2b3648;
            height: 58px;
        }
        .hr-logo {
            font-weight: 700;
            font-size: 1.15rem;
            color: #ffffff;
            letter-spacing: -0.3px;
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

        /* Thanh Breadcrumb & Chọn bài tập */
        .hr-subbar {
            background: #ffffff;
            border-bottom: 1px solid var(--hr-border);
            padding: 10px 24px;
        }

        /* Khung nội dung đề bài (Cột trái) */
        .hr-card {
            background: #ffffff;
            border: 1px solid var(--hr-border);
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .hr-section-title {
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #374151;
            margin-top: 14px;
            margin-bottom: 6px;
        }
        .hr-sample-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 4px solid var(--hr-green);
            border-radius: 4px;
            padding: 10px 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: #0f172a;
            white-space: pre-wrap;
            margin-bottom: 8px;
        }

        /* Độ khó chuẩn HackerRank */
        .diff-easy { color: #00b760; font-weight: 700; }
        .diff-medium { color: #f59e0b; font-weight: 700; }
        .diff-hard { color: #ef4444; font-weight: 700; }

        /* Khung Editor (Cột phải) */
        .hr-editor-wrapper {
            background-color: #1e1e1e;
            border: 1px solid #2b3648;
            border-radius: 6px;
            overflow: hidden;
        }
        .hr-editor-header {
            background-color: var(--hr-panel-dark);
            padding: 8px 14px;
            border-bottom: 1px solid #2b3648;
            color: #cbd5e1;
        }
        #editor {
            height: 350px;
            width: 100%;
        }
        .hr-console-panel {
            background-color: #141a24;
            border-top: 1px solid #2b3648;
            padding: 12px 14px;
            color: #e2e8f0;
        }
        .hr-console-input, .hr-console-output {
            background-color: #0b0f17 !important;
            border: 1px solid #2b3648 !important;
            color: #00ea64 !important;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.82rem;
        }

        /* Các nút bấm chuẩn HackerRank */
        .btn-hr-submit {
            background-color: var(--hr-green);
            color: var(--hr-green-text);
            font-weight: 700;
            border: none;
            padding: 7px 18px;
        }
        .btn-hr-submit:hover {
            background-color: var(--hr-green-hover);
            color: var(--hr-green-text);
        }
        .btn-hr-run {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid #64748b;
            font-weight: 600;
            padding: 7px 14px;
        }
        .btn-hr-run:hover {
            background-color: #2b3648;
            color: #ffffff;
            border-color: #94a3b8;
        }

        /* Nhãn trạng thái chấm bài */
        .badge-AC { background-color: #00b760; color: #fff; }
        .badge-WA { background-color: #ef4444; color: #fff; }
        .badge-TLE { background-color: #f59e0b; color: #fff; }
        .badge-RE, .badge-CE { background-color: #8b5cf6; color: #fff; }
        .badge-SYSTEM_ERROR { background-color: #1f2937; color: #fff; }
    </style>
</head>
<body>

<!-- Hidden input lưu ID người dùng đang đăng nhập thực tế từ Session -->
<input type="hidden" id="currentUser" value="<?= $currentUserId ?>">

<!-- Thanh Navbar kiểu HackerRank -->
<nav class="navbar hr-navbar px-4">
    <div class="hr-logo">
        <span>HackerRank</span>
        <span class="hr-logo-box"></span>
        <span class="text-secondary fw-normal fs-6 ms-2">| Đề tài 20 - Nhóm 11</span>
    </div>
    <div class="d-flex align-items-center gap-3 text-white">
        <span class="small">Xin chào, <strong><?= htmlspecialchars($currentFullName) ?></strong> (<span class="text-warning"><?= strtoupper($currentUserRole) ?></span>)</span>
        <?php if ($currentUserRole === 'teacher'): ?>
            <a href="teacher.php" class="btn btn-outline-success btn-sm py-0">Quản lý Đề</a>
        <?php elseif ($currentUserRole === 'admin'): ?>
            <a href="admin.php" class="btn btn-outline-warning btn-sm py-0">Trang Admin</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-outline-danger btn-sm py-0">Đăng xuất</a>
    </div>
</nav>

<!-- Thanh chọn bài tập (Sub-header) -->
<div class="hr-subbar d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3 w-50">
        <span class="text-muted small fw-semibold text-uppercase">Thử thách:</span>
        <select id="problemSelect" class="form-select form-select-sm fw-bold w-75" onchange="location.href='?problem_id='+this.value">
            <?php foreach ($problems as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ((int)$p['id'] === $selectedProblemId) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['title']) ?> (v<?= $p['version_number'] ?> - <?= $p['time_limit_ms'] ?>ms)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($currentProblem):
        $diffClass = 'diff-' . strtolower($currentProblem['difficulty']);
    ?>
    <div class="d-flex gap-4 small">
        <div><span class="text-muted">Mức độ:</span> <span class="<?= $diffClass ?>"><?= ucfirst($currentProblem['difficulty']) ?></span></div>
        <div><span class="text-muted">Chủ đề:</span> <strong><?= htmlspecialchars($currentProblem['topic']) ?></strong></div>
        <div><span class="text-muted">Giới hạn chạy:</span> <strong><?= $currentProblem['time_limit_ms'] ?> ms</strong></div>
        <div><span class="text-muted">Phiên bản dữ liệu:</span> <span class="badge bg-dark">v<?= $currentProblem['version_number'] ?></span></div>
    </div>
    <?php endif; ?>
</div>

<div class="container-fluid px-4">
    <div class="row g-3">
        <!-- CỘT TRÁI: Nội dung đề bài + Khung Giảng viên (nếu là teacher) + Chi tiết lịch sử chấm -->
        <div class="col-lg-5">
            <!-- Khung mô tả đề bài chuẩn HackerRank -->
            <div class="hr-card p-3 mb-3">
                <?php if ($currentProblem): ?>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($currentProblem['title']) ?></h5>
                    <div class="text-muted small mb-3">Nguồn bài tập: <?= htmlspecialchars($currentProblem['source_credit'] ?? 'Nhóm 11') ?></div>

                    <div class="hr-section-title">Mô tả bài toán (Problem)</div>
                    <p class="mb-2" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($currentProblem['description'])) ?></p>

                    <div class="hr-section-title">Định dạng Đầu vào / Đầu ra (Input & Output Format)</div>
                    <p class="small text-secondary mb-2" style="line-height: 1.5;"><?= nl2br(htmlspecialchars($currentProblem['io_description'])) ?></p>

                    <?php if (!empty($examples)): ?>
                        <?php foreach ($examples as $idx => $ex): ?>
                            <div class="row g-2 mt-1">
                                <div class="col-md-6">
                                    <div class="hr-section-title mt-1">Sample Input <?= $idx ?></div>
                                    <div class="hr-sample-box"><?= htmlspecialchars($ex['stdin']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="hr-section-title mt-1">Sample Output <?= $idx ?></div>
                                    <div class="hr-sample-box"><?= htmlspecialchars($ex['stdout']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Khung Giảng viên: Chỉ hiển thị khi đăng nhập bằng tài khoản Giảng viên -->
            <?php if ($currentUserRole === 'teacher'): ?>
            <div class="hr-card p-3 mb-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold text-dark">Bảng điều khiển Giảng viên (Chức năng 5 - Rejudge)</span>
                    <span class="badge bg-success">Versioning</span>
                </div>
                <p class="small text-muted mb-2">Tạo phiên bản bộ dữ liệu chấm mới (`problem_versions`) và tự động chấm lại toàn bộ bài nộp mà vẫn giữ nguyên lịch sử lần chấm trước.</p>
                <button class="btn btn-dark btn-sm fw-semibold" onclick="createNewVersionAndRejudge()">
                    + Tạo Phiên bản bộ dữ liệu mới & Chấm lại toàn bộ
                </button>
                <div id="rejudgeMsg" class="mt-2 small fw-bold text-success"></div>
            </div>
            <?php endif; ?>

            <!-- Khung Chi tiết bài nộp & Đối chiếu lịch sử -->
            <div class="hr-card p-3 mb-4">
                <div class="fw-bold mb-2 pb-2 border-bottom">Chi tiết bài nộp & Đối chiếu lịch sử các lần chấm</div>
                <div class="small" id="submissionDetail">
                    <span class="text-muted">Chọn một bài nộp ở bảng bên phải để xem mã nguồn và đối chiếu lịch sử các lần chấm...</span>
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: Trình soạn mã (Monaco Editor) + Console chạy thử + Bảng hàng đợi Submissions -->
        <div class="col-lg-7">
            <div class="hr-editor-wrapper shadow-sm mb-3">
                <!-- Thanh công cụ trên đầu Editor -->
                <div class="hr-editor-header d-flex justify-content-between align-items-center">
                    <div class="small fw-semibold">Code Editor (stdin / stdout)</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-secondary">Language:</span>
                        <select id="langSelect" class="form-select form-select-sm w-auto hr-user-select" onchange="changeLang()">
                            <option value="python">Python 3</option>
                            <option value="cpp">C++ (g++ 17)</option>
                        </select>
                    </div>
                </div>

                <!-- Khung Monaco Editor -->
                <div id="editor"></div>

                <!-- Bảng Console Test against custom input & Nút bấm dưới chân Editor -->
                <div class="hr-console-panel">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label small text-secondary mb-1">Test against custom input (stdin):</label>
                            <textarea id="customStdin" class="form-control form-control-sm hr-console-input" rows="3"><?= htmlspecialchars($defaultStdin) ?></textarea>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-secondary mb-1">Console Output / Compiler Message:</label>
                            <pre id="runOutput" class="form-control form-control-sm hr-console-output mb-0" style="height: 74px; overflow:auto;">Ready to run code...</pre>
                        </div>
                    </div>

                    <div class="mt-3 pt-2 border-top border-secondary d-flex align-items-center gap-2">
                        <button class="btn btn-hr-run btn-sm" onclick="runOrSave('save')">Save Draft</button>
                        <button class="btn btn-hr-run btn-sm" onclick="runOrSave('run')">Run Code</button>
                        <button class="btn btn-outline-warning btn-sm ms-auto" onclick="submitCode(true)" title="Phép thử 3: Gửi lại cùng idempotency_key">
                            Retry Same Request (Idempotency)
                        </button>
                        <button class="btn btn-hr-submit btn-sm" onclick="submitCode(false)">Submit Code</button>
                    </div>
                </div>
            </div>

            <!-- Bảng Submissions (Hàng đợi chấm & Lịch sử) -->
            <div class="hr-card overflow-hidden mb-4">
                <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Submissions & Judging Queue (Tự động cập nhật)</span>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="loadSubmissions()">Refresh</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">ID</th>
                                <th>Hacker (User)</th>
                                <th>Language</th>
                                <th>Dataset Ver</th>
                                <th>Result / Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody id="submissionList"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
<script>
let editor;
let lastIdempotencyKey = 'key-' + Date.now();

require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' }});
require(['vs/editor/editor.main'], function() {
    editor = monaco.editor.create(document.getElementById('editor'), {
        value: "import sys\n\ndata = sys.stdin.read().split()\nif len(data) >= 2:\n    print(int(data[0]) + int(data[1]))\n",
        language: 'python',
        theme: 'vs-dark',
        fontSize: 14,
        fontFamily: "'JetBrains Mono', monospace",
        minimap: { enabled: false },
        automaticLayout: true
    });
    loadSubmissions();
    setInterval(loadSubmissions, 2500);
});

function changeLang() {
    const lang = document.getElementById('langSelect').value;
    monaco.editor.setModelLanguage(editor.getModel(), lang);
    if (lang === 'cpp') {
        editor.setValue("#include <iostream>\nusing namespace std;\nint main() {\n    long long a, b;\n    if (cin >> a >> b) cout << a + b << endl;\n    return 0;\n}");
    } else {
        editor.setValue("import sys\n\ndata = sys.stdin.read().split()\nif len(data) >= 2:\n    print(int(data[0]) + int(data[1]))\n");
    }
    lastIdempotencyKey = 'key-' + Date.now();
}

async function runOrSave(action) {
    document.getElementById('runOutput').innerText = 'Processing...';
    const res = await fetch('api/run_draft.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: action,
            user_id: document.getElementById('currentUser').value,
            problem_id: document.getElementById('problemSelect').value,
            language: document.getElementById('langSelect').value,
            source_code: editor.getValue(),
            stdin: document.getElementById('customStdin').value
        })
    });
    const data = await res.json();
    if (action === 'save') {
        document.getElementById('runOutput').innerText = '✓ ' + data.message;
    } else {
        document.getElementById('runOutput').innerText = `[${data.status} - ${data.time_ms}ms]\nSTDOUT: ${data.stdout}\nSTDERR: ${data.stderr}`;
    }
}

async function submitCode(reuseKey) {
    if (!reuseKey) {
        lastIdempotencyKey = 'key-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7);
    }
    const res = await fetch('api/submit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            user_id: document.getElementById('currentUser').value,
            problem_id: document.getElementById('problemSelect').value,
            language: document.getElementById('langSelect').value,
            source_code: editor.getValue(),
            idempotency_key: lastIdempotencyKey
        })
    });
    const data = await res.json();
    alert(data.is_duplicate
        ? `Phép thử 3 (Chống nộp trùng): Hệ thống trả về cùng mã bài nộp #${data.submission_id}, không tạo bản trùng!`
        : `Đã tạo bài nộp mới #${data.submission_id} (Run #${data.judging_run_id})`);
    loadSubmissions();
}

async function loadSubmissions() {
    const res = await fetch('api/status.php');
    const data = await res.json();
    const tbody = document.getElementById('submissionList');
    tbody.innerHTML = (data.submissions || []).map(s => `
        <tr>
            <td class="ps-3 fw-bold">#${s.id}</td>
            <td>${s.username}</td>
            <td><span class="badge bg-light text-dark border">${s.language}</span></td>
            <td>v${s.judged_version || 1}</td>
            <td><span class="badge badge-${s.latest_status || 'secondary'}">${s.latest_status || 'queued'} (${s.passed_cases || 0}/${s.total_cases || 0})</span></td>
            <td class="text-end pe-3"><button class="btn btn-sm btn-outline-dark py-0 px-2" onclick="viewSubmission(${s.id})">View Results</button></td>
        </tr>
    `).join('');
}

async function viewSubmission(subId) {
    const viewerId = document.getElementById('currentUser').value;
    const res = await fetch(`api/status.php?submission_id=${subId}&viewer_id=${viewerId}`);
    const data = await res.json();
    const box = document.getElementById('submissionDetail');

    if (res.status !== 200) {
        box.innerHTML = `<div class="alert alert-danger mb-0">${data.error}</div>`;
        return;
    }

    const runsHtml = data.history_runs.map(r => `
        <div class="border rounded p-2 mb-2 bg-light">
            <div class="fw-bold d-flex justify-content-between">
                <span>Run #${r.id} (Dataset v${r.version_number}) ${r.is_rejudge == 1 ? '<span class="badge bg-info text-dark">Rejudged</span>' : '<span class="badge bg-secondary">Initial</span>'}</span>
                <span class="badge badge-${r.status}">${r.status} (${r.passed_cases}/${r.total_cases})</span>
            </div>
            <ul class="mb-0 mt-2 ps-3">
                ${(r.cases || []).map(c => `<li>Test case #${c.case_order} (${c.is_public == 1 ? 'Sample' : 'Hidden'}): <strong>${c.status}</strong> (${c.execution_time_ms}ms) - Output: <code>${c.actual_preview}</code></li>`).join('')}
            </ul>
        </div>
    `).join('');

    box.innerHTML = `
        <p class="mb-1"><strong>Submission #${data.submission.id}</strong> by <strong>${data.submission.username}</strong> (Initial Dataset v${data.submission.initial_version_id})</p>
        <pre class="bg-dark text-white p-2 rounded" style="font-family: 'JetBrains Mono', monospace;">${data.submission.source_code.replace(/</g, '&lt;')}</pre>
        <h6 class="fw-bold mt-3">Judging Runs History (Đối chiếu các lần chấm):</h6>
        ${runsHtml}
    `;
}

async function createNewVersionAndRejudge() {
    const res = await fetch('api/rejudge.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            user_id: document.getElementById('currentUser').value,
            problem_id: document.getElementById('problemSelect').value,
            time_limit_ms: 1000,
            test_cases: [
                {stdin: "3 5\n", stdout: "8\n", is_public: true},
                {stdin: "-10 10\n", stdout: "0\n", is_public: true},
                {stdin: "0 0\n", stdout: "0\n", is_public: false},
                {stdin: "1000000000 1000000000\n", stdout: "2000000000\n", is_public: false},
                {stdin: "999999999999 1\n", stdout: "1000000000000\n", is_public: false}
            ]
        })
    });
    const data = await res.json();
    if (res.status !== 200) {
        alert(data.error);
        return;
    }
    document.getElementById('rejudgeMsg').innerText = `✓ Đã tạo Phiên bản v${data.version_number} và chấm lại ${data.rejudged_submissions} bài nộp!`;
    loadSubmissions();
}
</script>
</body>
</html>