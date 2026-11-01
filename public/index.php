<?php
// public/index.php - Luyện tập & Soạn mã (CyberRank Glassmorphism Style - Việt Hóa 100% & Bỏ trong suốt bảng)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId   = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];
$currentFullName = $_SESSION['full_name'] ?? $currentUsername;
$currentUserRole = $_SESSION['role'];

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();
$problems = $pdo->query("
    SELECT p.*, pv.version_number, pv.time_limit_ms
    FROM problems p
    LEFT JOIN problem_versions pv ON pv.id = p.current_version_id
    ORDER BY p.id ASC
")->fetchAll();

// 1. Lấy danh sách ID các bài tập người dùng ĐÃ GIẢI ĐÚNG (AC)
$stmtSolved = $pdo->prepare("
    SELECT DISTINCT s.problem_id
    FROM submissions s
    JOIN judging_runs jr ON s.latest_run_id = jr.id
    WHERE s.user_id = ? AND jr.status = 'AC'
");
$stmtSolved->execute([$currentUserId]);
$solvedProblemIds = $stmtSolved->fetchAll(PDO::FETCH_COLUMN);

// 2. Lấy danh sách Chủ đề duy nhất để làm bộ lọc
$topics = array_unique(array_column($problems, 'topic'));

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
    <title>Khu Vực Luyện Tập | CyberRank</title>
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

        /* Subbar */
        .cyber-subbar {
            background: rgba(15, 23, 42, 0.8);
            border-bottom: 1px solid var(--c-border);
            padding: 12px 24px;
            backdrop-filter: blur(8px);
        }

        /* Form Controls */
        .form-control, .form-select { 
            background-color: rgba(15, 23, 42, 0.6); 
            border: 1px solid var(--c-border); 
            color: var(--c-text-main); 
            border-radius: 8px;
            padding: 8px 12px;
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
            color: #fff; font-weight: 600; border: none; border-radius: 6px; 
            padding: 8px 16px; transition: all 0.2s; 
        }
        .btn-cyber:hover { transform: translateY(-1px); box-shadow: 0 4px 12px var(--c-primary-glow); color: #fff; }
        
        .btn-outline-glass { border: 1px solid var(--c-border); color: var(--c-text-muted); background: transparent; border-radius: 6px; font-weight: 500;}
        .btn-outline-glass:hover { border-color: var(--c-text-main); color: var(--c-text-main); background: rgba(255,255,255,0.05); }

        /* Khung Đề bài */
        .problem-section-title { font-weight: 700; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--c-text-muted); margin-top: 20px; margin-bottom: 8px; }
        .sample-box { 
            background-color: rgba(0, 0, 0, 0.3); 
            border: 1px solid var(--c-border); 
            border-left: 3px solid var(--c-primary); 
            border-radius: 6px; padding: 12px 16px; 
            font-family: 'JetBrains Mono', monospace; 
            font-size: 0.85rem; color: #6ee7b7; white-space: pre-wrap; 
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Monaco Editor Wrapper */
        .editor-wrapper { 
            border: 1px solid var(--c-border); 
            border-radius: 8px; 
            overflow: hidden; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
        }
        .editor-header { background: rgba(0, 0, 0, 0.4); padding: 10px 16px; border-bottom: 1px solid var(--c-border); }
        #editor { height: 380px; width: 100%; }
        
        .console-panel { background: rgba(15, 23, 42, 0.8); border-top: 1px solid var(--c-border); padding: 16px; }
        .console-io { background-color: #000 !important; border: 1px solid var(--c-border) !important; color: #10b981 !important; }

        /* Bảng Submissions (ĐÃ BỎ TRONG SUỐT THEO YÊU CẦU) */
        .table-custom { color: var(--c-text-main); margin-bottom: 0; }
        .table-custom th { background-color: #0f172a; color: var(--c-text-muted); border-bottom: 1px solid var(--c-border); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; padding: 14px 16px; }
        .table-custom td { background-color: #1e293b; color: var(--c-text-main); border-bottom: 1px solid var(--c-border); padding: 12px 16px; vertical-align: middle; }
        .table-custom tbody tr:hover td { background-color: #334155; }

        .run-history-box { background: rgba(0,0,0,0.2); border: 1px dashed var(--c-border); border-radius: 8px; padding: 12px 16px; margin-bottom: 10px; }
        
        /* Badges Trạng thái Đầy đủ (MÀU ĐẶC - SOLID) */
        .badge-AC { background-color: #10b981; color: #fff; border: none; }
        .badge-WA { background-color: #ef4444; color: #fff; border: none; }
        .badge-TLE { background-color: #f59e0b; color: #fff; border: none; }
        .badge-CE, .badge-RE { background-color: #8b5cf6; color: #fff; border: none; }
        .badge-SYSTEM_ERROR { background-color: #475569; color: #fff; border: none; }
        .badge-queued { background-color: #64748b; color: #fff; border: none; }
        .badge-running { background-color: #3b82f6; color: #fff; border: none; }

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

<input type="hidden" id="currentUser" value="<?= $currentUserId ?>">

<nav class="navbar glass-nav px-4">
    <div class="d-flex align-items-center gap-3">
        <div class="brand-icon"></div>
        <span class="fw-bold text-white fs-5 tracking-wide">CyberRank</span>
    </div>
    <div class="d-flex align-items-center gap-3 text-white">
        <div class="d-none d-md-flex flex-column text-end me-2" style="line-height: 1.2;">
            <span class="small text-white fw-bold"><?= htmlspecialchars($currentFullName) ?></span>
            <span class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Hồ Sơ Học Viên</span>
        </div>
        <?php if ($currentUserRole === 'teacher'): ?>
            <a href="teacher.php" class="btn btn-outline-glass btn-sm px-3 py-2">Cổng Giảng viên</a>
        <?php elseif ($currentUserRole === 'admin'): ?>
            <a href="admin.php" class="btn btn-outline-glass btn-sm px-3 py-2">Trung tâm Quản trị</a>
        <?php endif; ?>
        <a href="leaderboard.php" class="btn btn-outline-glass btn-sm px-3 py-2" style="border-color: rgba(59,130,246,0.5); color: #93c5fd;">Bảng Xếp Hạng</a>
        <a href="logout.php" class="btn btn-cyber btn-sm px-3 py-2" style="background: rgba(239,68,68,0.2); color: #fca5a5; box-shadow: none;">Đăng xuất</a>
    </div>
</nav>

<div class="cyber-subbar mb-4">
    <div class="container-fluid max-w-7xl mx-auto" style="max-width: 1400px;">
        <div class="row align-items-center">
            <div class="col-md-7 d-flex align-items-center gap-3">
                <select id="filterTopic" class="form-select form-select-sm" style="width: 160px;" onchange="filterProblems()">
                    <option value="ALL">Mọi Chủ Đề</option>
                    <?php foreach($topics as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterDiff" class="form-select form-select-sm" style="width: 120px;" onchange="filterProblems()">
                    <option value="ALL">Mọi Cấp Độ</option>
                    <option value="easy">Dễ (Easy)</option>
                    <option value="medium">Vừa (Medium)</option>
                    <option value="hard">Khó (Hard)</option>
                </select>
                
                <!-- Dropdown Đề bài -->
                <select id="problemSelect" class="form-select form-select-sm fw-bold flex-grow-1" style="font-size: 0.95rem;" onchange="location.href='?problem_id='+this.value">
                    <?php foreach ($problems as $p): 
                        $isSolved = in_array($p['id'], $solvedProblemIds);
                    ?>
                        <option value="<?= $p['id'] ?>" 
                                data-topic="<?= htmlspecialchars($p['topic']) ?>" 
                                data-diff="<?= $p['difficulty'] ?>"
                                <?= ((int)$p['id'] === $selectedProblemId) ? 'selected' : '' ?>>
                            <?= $isSolved ? '✓ ' : '' ?><?= htmlspecialchars($p['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($currentProblem): $diffClass = 'diff-' . strtolower($currentProblem['difficulty']); ?>
            <div class="col-md-5 d-flex justify-content-end gap-4 small align-items-center">
                <div class="px-3 py-1 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid var(--c-border);">
                    <span class="text-muted">Cấp độ:</span> <span class="<?= $diffClass ?> ms-1 text-uppercase"><?= $currentProblem['difficulty'] ?></span>
                </div>
                <div class="px-3 py-1 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid var(--c-border);">
                    <span class="text-muted">Bộ Test:</span> <strong class="text-white ms-1 mono">v<?= $currentProblem['version_number'] ?></strong>
                </div>
                <div class="px-3 py-1 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid var(--c-border);">
                    <span class="text-muted">Giới hạn thời gian:</span> <strong class="text-white ms-1 mono"><?= $currentProblem['time_limit_ms'] ?>ms</strong>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5 max-w-7xl mx-auto" style="max-width: 1400px;">
    <div class="row g-4">
        
        <!-- CỘT TRÁI: Đề bài & Chi tiết -->
        <div class="col-lg-5 d-flex flex-column gap-4">
            <!-- Khung Đề Bài -->
            <div class="glass-card">
                <div class="glass-header d-flex justify-content-between align-items-center">
                    <span class="fs-6"><?= htmlspecialchars($currentProblem['title'] ?? 'Nội dung Thử thách') ?></span>
                    <span class="badge" style="background: rgba(255,255,255,0.1); color: var(--c-text-muted);">ID: #<?= $currentProblem['id'] ?? 1 ?></span>
                </div>
                <div class="p-4" style="max-height: 500px; overflow-y: auto;">
                    <?php if ($currentProblem): ?>
                        <div class="problem-section-title mt-0">Mô Tả Bài Toán</div>
                        <p class="mb-4 text-white" style="line-height: 1.7; font-size: 0.95rem;"><?= nl2br(htmlspecialchars($currentProblem['description'])) ?></p>
                        
                        <div class="problem-section-title">Định Dạng Dữ Liệu (I/O Format)</div>
                        <p class="text-muted mb-4" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($currentProblem['io_description'])) ?></p>

                        <?php if (!empty($examples)): ?>
                            <?php foreach ($examples as $idx => $ex): ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-xl-6">
                                        <div class="d-flex justify-content-between align-items-end mb-2">
                                            <div class="problem-section-title m-0">Ví Dụ Đầu Vào <?= $idx ?></div>
                                            <button class="btn btn-sm btn-outline-glass py-0 px-2" style="font-size:0.7rem; border-color: rgba(16,185,129,0.3); color: #10b981;" onclick="copyToStdin(`<?= htmlspecialchars(addslashes($ex['stdin'])) ?>`)">Chép vào Stdin</button>
                                        </div>
                                        <div class="sample-box"><?= htmlspecialchars($ex['stdin']) ?></div>
                                    </div>
                                    <div class="col-12 col-xl-6">
                                        <div class="problem-section-title m-0 mb-2">Ví Dụ Đầu Ra <?= $idx ?></div>
                                        <div class="sample-box text-white" style="border-left-color: rgba(255,255,255,0.2);"><?= htmlspecialchars($ex['stdout']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Khung Chi Tiết Submissions -->
            <div class="glass-card flex-grow-1">
                <div class="glass-header">Chi Tiết Kết Quả Chấm</div>
                <div class="p-4 small text-muted" id="submissionDetail">
                    Vui lòng chọn thẻ <span class="badge" style="background: rgba(255,255,255,0.1);">Xem</span> của một bài nộp ở bảng bên phải để hiển thị mã nguồn và đối chiếu các Test Cases...
                </div>
            </div>
        </div>

        <!-- CỘT PHẢI: Trình Soạn Mã & Lịch Sử -->
        <div class="col-lg-7 d-flex flex-column gap-4">
            
            <!-- Trình Soạn Thảo (Editor) -->
            <div class="editor-wrapper">
                <div class="editor-header d-flex justify-content-between align-items-center">
                    <div class="fw-bold d-flex align-items-center gap-2">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="text-primary"><path d="M10.478 1.647a.5.5 0 1 0-.956-.294l-4 13a.5.5 0 0 0 .956.294l4-13zM4.854 4.146a.5.5 0 0 1 0 .708L1.707 8l3.147 3.146a.5.5 0 0 1-.708.708l-3.5-3.5a.5.5 0 0 1 0-.708l3.5-3.5a.5.5 0 0 1 .708 0zm6.292 0a.5.5 0 0 0 0 .708L14.293 8l-3.147 3.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708 0z"/></svg>
                        Không Gian Làm Việc
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted fw-medium">Ngôn ngữ:</span>
                        <select id="langSelect" class="form-select form-select-sm w-auto fw-bold" style="background: rgba(0,0,0,0.5);" onchange="changeLang()">
                            <option value="python">Python 3 (3.10)</option>
                            <option value="cpp">C++ (GCC 11)</option>
                        </select>
                    </div>
                </div>
                
                <div id="editor"></div>
                
                <div class="console-panel">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small text-muted mb-2 fw-medium">Đầu Vào Tự Chọn (Custom Input):</label>
                            <textarea id="customStdin" class="form-control form-control-sm console-io mono" rows="3"><?= htmlspecialchars($defaultStdin) ?></textarea>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-muted mb-2 fw-medium">Kết Quả Trình Biên Dịch (Output):</label>
                            <pre id="runOutput" class="form-control form-control-sm console-io mono mb-0" style="height: 78px; overflow:auto;">[Hệ thống] Khởi tạo không gian làm việc thành công.</pre>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top d-flex align-items-center gap-3" style="border-color: rgba(255,255,255,0.05) !important;">
                        <button class="btn btn-outline-glass btn-sm px-3" onclick="runOrSave('save')">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4.207a1 1 0 0 0-.293-.707l-2.5-2.5A1 1 0 0 0 10.5 1H2zm13 10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h8.086a.25.25 0 0 1 .177.073l2.5 2.5a.25.25 0 0 1 .073.177V11z"/><path d="M4 4h4v3H4V4zm4 4H4v3h4V8z"/></svg>
                            Lưu Bản Nháp
                        </button>
                        <button class="btn btn-outline-glass btn-sm px-3" style="color: #6ee7b7; border-color: rgba(16,185,129,0.3);" onclick="runOrSave('run')">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z"/></svg>
                            Chạy Code
                        </button>
                        <button class="btn btn-outline-glass btn-sm px-3 ms-auto" style="color: #fcd34d; border-color: rgba(245,158,11,0.3);" onclick="submitCode(true)">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>
                            Thử Lại (Retry)
                        </button>
                        <button class="btn btn-cyber btn-sm px-4 py-2" style="font-size: 0.95rem; letter-spacing: 0.5px;" onclick="submitCode(false)">
                            NỘP BÀI
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bảng Lịch Sử (Submissions) -->
            <div class="glass-card flex-grow-1">
                <div class="glass-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 11a1 1 0 1 1 2 0v1a1 1 0 1 1-2 0v-1zm4-4a1 1 0 1 1 2 0v5a1 1 0 1 1-2 0V7zm4-3a1 1 0 0 1 1 1v8a1 1 0 1 1-2 0V5a1 1 0 0 1 1-1zM2 3a1 1 0 0 1 1 1v10a1 1 0 1 1-2 0V4a1 1 0 0 1 1-1z"/></svg>
                        Lịch Sử Nộp Bài
                    </div>
                    <div class="d-flex gap-2">
                        <select id="subStatusFilter" class="form-select form-select-sm" style="width: 130px; background: rgba(0,0,0,0.3);" onchange="renderSubmissions()">
                            <option value="ALL">Mọi Kết Quả</option>
                            <option value="AC">Đúng (AC)</option>
                            <option value="WA">Sai (WA)</option>
                            <option value="TLE">Quá giờ (TLE)</option>
                            <option value="CE">Lỗi biên dịch (CE)</option>
                            <option value="RE">Lỗi thực thi (RE)</option>
                        </select>
                        <button class="btn btn-sm btn-outline-glass px-2 py-0" onclick="loadSubmissions()">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>
                        </button>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-custom align-middle text-nowrap">
                        <thead class="sticky-top" style="z-index: 5;">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>TÀI KHOẢN</th>
                                <th>NGÔN NGỮ</th>
                                <th>BỘ TEST</th>
                                <th>KẾT QUẢ / TRẠNG THÁI</th>
                                <th class="text-end pe-4">THAO TÁC</th>
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
let allSubmissionsData = [];

// 1. Khởi tạo Monaco và Auto-load Draft
require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' }});
require(['vs/editor/editor.main'], function() {
    editor = monaco.editor.create(document.getElementById('editor'), {
        value: "[Hệ thống] Đang tải bản nháp từ bộ nhớ...",
        language: 'python',
        theme: 'vs-dark', 
        fontSize: 14,
        fontFamily: "'JetBrains Mono', monospace",
        minimap: { enabled: false },
        automaticLayout: true,
        scrollBeyondLastLine: false,
        padding: { top: 16 }
    });
    
    loadDraftData('python');
    loadSubmissions();
    setInterval(loadSubmissions, 2500);
});

// 2. Fetch Code Draft API
async function loadDraftData(lang) {
    const problemId = document.getElementById('problemSelect').value;
    const userId = document.getElementById('currentUser').value;
    try {
        const res = await fetch(`api/get_draft.php?user_id=${userId}&problem_id=${problemId}&language=${lang}`);
        const data = await res.json();
        if (data.success && data.draft.trim() !== '') {
            editor.setValue(data.draft);
        } else {
            if (lang === 'cpp') {
                editor.setValue("#include <iostream>\nusing namespace std;\n\nint main() {\n    // Bắt đầu viết thuật toán của bạn tại đây\n    return 0;\n}");
            } else {
                editor.setValue("import sys\n\ndef main():\n    # Bắt đầu viết thuật toán của bạn tại đây\n    pass\n\nif __name__ == '__main__':\n    main()\n");
            }
        }
    } catch(e) {
        console.error("Lỗi khi tải bản nháp");
    }
}

function changeLang() {
    const lang = document.getElementById('langSelect').value;
    monaco.editor.setModelLanguage(editor.getModel(), lang);
    loadDraftData(lang);
    lastIdempotencyKey = 'key-' + Date.now();
}

function copyToStdin(text) {
    document.getElementById('customStdin').value = text;
}

function filterProblems() {
    const topic = document.getElementById('filterTopic').value;
    const diff = document.getElementById('filterDiff').value;
    const select = document.getElementById('problemSelect');
    
    let firstVisible = null;
    Array.from(select.options).forEach(opt => {
        const t = opt.getAttribute('data-topic');
        const d = opt.getAttribute('data-diff');
        let show = true;
        if (topic !== 'ALL' && t !== topic) show = false;
        if (diff !== 'ALL' && d !== diff) show = false;
        
        opt.style.display = show ? '' : 'none';
        if (show && !firstVisible) firstVisible = opt;
    });
    
    if (select.options[select.selectedIndex].style.display === 'none' && firstVisible) {
        select.value = firstVisible.value;
        location.href = '?problem_id=' + select.value;
    }
}

async function runOrSave(action) {
    document.getElementById('runOutput').innerText = '[Hệ thống] Đang thực thi mã lệnh...';
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
        document.getElementById('runOutput').innerText = `[Trạng thái: ${data.status} | Thời gian chạy: ${data.time_ms}ms]\n\nĐẦU RA (STDOUT):\n${data.stdout}\n\nLỖI (STDERR):\n${data.stderr}`;
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
    if(data.is_duplicate) {
        alert(`[Chống Gửi Trùng Lặp] Hệ thống phát hiện Yêu cầu trùng lặp.\nĐã trả về mã bài nộp cũ: #${data.submission_id}`);
    } else {
        alert(`[Nộp Bài Thành Công]\nHệ thống đã tiếp nhận bài nộp #${data.submission_id} và đưa vào hàng đợi chấm.`);
    }
    loadSubmissions();
}

async function loadSubmissions() {
    const res = await fetch('api/status.php');
    const data = await res.json();
    allSubmissionsData = data.submissions || [];
    renderSubmissions();
}

function renderSubmissions() {
    const filter = document.getElementById('subStatusFilter').value;
    const tbody = document.getElementById('submissionList');
    
    let filtered = allSubmissionsData;
    if (filter !== 'ALL') {
        filtered = filtered.filter(s => s.latest_status === filter);
    }
    
    tbody.innerHTML = filtered.map(s => `
        <tr>
            <td class="ps-4 fw-bold font-monospace text-muted">#${s.id}</td>
            <td class="fw-bold text-white">${s.username}</td>
            <td><span class="badge badge-cyber" style="background: rgba(255,255,255,0.05); color: #94a3b8; border: 1px solid rgba(255,255,255,0.1); font-family: 'JetBrains Mono';">${s.language}</span></td>
            <td class="mono text-muted">v${s.judged_version || 1}</td>
            <td>
                <span class="badge-cyber badge-${s.latest_status || 'queued'}">
                    ${s.latest_status || 'queued'} <span style="opacity:0.8; margin-left:2px;">(${s.passed_cases || 0}/${s.total_cases || 0})</span>
                </span>
            </td>
            <td class="text-end pe-4"><button class="btn btn-sm btn-outline-glass py-1 px-3" style="font-size: 0.75rem;" onclick="viewSubmission(${s.id})">Xem</button></td>
        </tr>
    `).join('');
}

async function viewSubmission(subId) {
    const viewerId = document.getElementById('currentUser').value;
    const res = await fetch(`api/status.php?submission_id=${subId}&viewer_id=${viewerId}`);
    const data = await res.json();
    const box = document.getElementById('submissionDetail');

    if (res.status !== 200) {
        box.innerHTML = `<div class="alert alert-error mb-0 py-3 rounded-3 border-0"><span class="fw-medium">${data.error}</span></div>`;
        return;
    }

    const runsHtml = data.history_runs.map(r => `
        <div class="run-history-box">
            <div class="fw-bold d-flex justify-content-between align-items-center mb-2">
                <span class="text-white d-flex align-items-center gap-2">
                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="text-muted"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>
                    Lần chấm #${r.id} <span class="badge rounded-pill bg-dark border border-secondary text-muted mono mx-1">v${r.version_number}</span>
                    ${r.is_rejudge == 1 ? '<span class="badge" style="background:rgba(59,130,246,0.15); color:#93c5fd; border:1px solid rgba(59,130,246,0.3); font-size:0.65rem;">CHẤM LẠI</span>' : '<span class="badge" style="background:rgba(255,255,255,0.05); color:#94a3b8; border:1px solid rgba(255,255,255,0.1); font-size:0.65rem;">LẦN ĐẦU</span>'}
                </span>
                <span class="badge-cyber badge-${r.status}">${r.status} (${r.passed_cases}/${r.total_cases})</span>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                ${(r.cases || []).map(c => `
                    <div class="px-2 py-1 rounded" style="background: rgba(0,0,0,0.3); border: 1px solid var(--c-border); font-size: 0.75rem;">
                        <span class="text-muted me-1">#${c.case_order}</span>
                        <strong class="${c.status === 'AC' ? 'text-success' : 'text-danger'}">${c.status}</strong>
                        <span class="text-muted mono ms-1">${c.execution_time_ms}ms</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `).join('');

    box.innerHTML = `
        <div class="mb-3 d-flex align-items-center gap-2 text-white">
            <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16" class="text-primary"><path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/><path d="M6.854 4.646a.5.5 0 0 1 0 .708L4.207 8l2.647 2.646a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 0 1 .708 0zm2.292 0a.5.5 0 0 0 0 .708L11.793 8l-2.647 2.646a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708 0z"/></svg>
            <strong class="fs-6">Bài nộp #${data.submission.id}</strong> 
            <span class="text-muted fw-normal ms-1">từ</span> 
            <strong style="color: #10b981;">${data.submission.username}</strong>
        </div>
        <pre class="p-3 rounded border border-secondary text-white mono mb-4" style="background-color:#0b0f17; font-size: 0.85rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">${data.submission.source_code.replace(/</g, '&lt;')}</pre>
        <div class="problem-section-title mb-3">Lịch Sử Đối Chiếu (Evaluation History)</div>
        ${runsHtml}
    `;
}
</script>
</body>
</html>