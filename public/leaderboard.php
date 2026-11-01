<?php
// public/leaderboard.php - Bảng xếp hạng & Ma trận tiến độ (CyberRank Style - Việt Hóa 100% & Bảng nền đặc)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId   = (int)$_SESSION['user_id'];
$currentFullName = $_SESSION['full_name'] ?? $_SESSION['username'];
$currentUserRole = $_SESSION['role'];

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

// Lọc theo lớp
$classFilter = $_GET['class_code'] ?? 'ALL';
$classes = $pdo->query("SELECT DISTINCT class_code FROM users WHERE role = 'student' AND class_code IS NOT NULL AND class_code != '' ORDER BY class_code")->fetchAll(PDO::FETCH_COLUMN);

// Lấy danh sách toàn bộ Bài tập để dựng cột Ma trận
$problems = $pdo->query("SELECT id, title, difficulty FROM problems ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Xây dựng câu truy vấn Lấy danh sách Học viên
$userSql = "SELECT id, username, full_name, class_code FROM users WHERE role = 'student'";
if ($classFilter !== 'ALL') {
    $userSql .= " AND class_code = " . $pdo->quote($classFilter);
}
$users = $pdo->query($userSql)->fetchAll(PDO::FETCH_ASSOC);

// Khởi tạo mảng điểm và ma trận
$scores = [];
$matrix = [];
foreach ($users as $u) {
    $scores[$u['id']] = ['user' => $u, 'total_score' => 0, 'total_ac' => 0];
    $matrix[$u['id']] = [];
}

// Truy vấn toàn bộ các bài nộp ĐÃ ĐÚNG (AC) để tính điểm
$stmt = $pdo->query("
    SELECT DISTINCT s.user_id, s.problem_id, p.difficulty
    FROM submissions s
    JOIN judging_runs jr ON s.latest_run_id = jr.id
    JOIN problems p ON s.problem_id = p.id
    WHERE jr.status = 'AC'
");
$acSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($acSubmissions as $ac) {
    $uid = $ac['user_id'];
    $pid = $ac['problem_id'];
    $diff = $ac['difficulty'];

    if (isset($scores[$uid])) {
        $scores[$uid]['total_ac']++;
        
        // Quy đổi điểm theo độ khó
        $pts = 10; // Dễ
        if ($diff === 'medium') $pts = 20; // Vừa
        if ($diff === 'hard') $pts = 30;   // Khó
        
        $scores[$uid]['total_score'] += $pts;
        $matrix[$uid][$pid] = true; // Đánh dấu đã giải
    }
}

// Sắp xếp Leaderboard: Điểm giảm dần -> Số AC giảm dần -> ID tăng dần
usort($scores, function($a, $b) {
    if ($a['total_score'] !== $b['total_score']) return $b['total_score'] - $a['total_score'];
    if ($a['total_ac'] !== $b['total_ac']) return $b['total_ac'] - $a['total_ac'];
    return $a['user']['id'] - $b['user']['id'];
});
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng Xếp Hạng | CyberRank</title>
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

        /* Nút bấm (Buttons) */
        .btn-cyber { 
            background: linear-gradient(to right, #10b981, #059669); 
            color: #fff; font-weight: 600; border: none; border-radius: 6px; 
            padding: 8px 16px; transition: all 0.2s; 
        }
        .btn-cyber:hover { transform: translateY(-1px); box-shadow: 0 4px 12px var(--c-primary-glow); color: #fff; }
        .btn-outline-glass { border: 1px solid var(--c-border); color: var(--c-text-muted); background: transparent; border-radius: 6px; font-weight: 500;}
        .btn-outline-glass:hover { border-color: var(--c-text-main); color: var(--c-text-main); background: rgba(255,255,255,0.05); }

        /* Tabs Style */
        .nav-tabs { border-bottom: 1px solid var(--c-border); }
        .nav-tabs .nav-link { color: var(--c-text-muted); border: none; padding: 14px 24px; font-weight: 600; letter-spacing: 0.5px; border-bottom: 3px solid transparent; transition: all 0.2s; }
        .nav-tabs .nav-link:hover { color: #fff; background: rgba(255,255,255,0.03); }
        .nav-tabs .nav-link.active { background: rgba(16, 185, 129, 0.1); color: var(--c-primary); border-bottom: 3px solid var(--c-primary); text-shadow: 0 0 10px rgba(16,185,129,0.5); }
        
        .form-select { background-color: rgba(15, 23, 42, 0.6); border: 1px solid var(--c-border); color: var(--c-text-main); border-radius: 8px; transition: all 0.3s ease; }
        .form-select:focus { background-color: rgba(15, 23, 42, 0.9); border-color: var(--c-primary); color: #fff; box-shadow: 0 0 0 3px var(--c-primary-glow); }
        
        /* Bảng dữ liệu nền đặc (KHÔNG TRONG SUỐT) */
        .table-custom { color: var(--c-text-main); margin-bottom: 0; }
        .table-custom th { background-color: #0f172a; color: var(--c-text-muted); border-bottom: 1px solid var(--c-border); font-size: 0.75rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; padding: 14px 16px; }
        .table-custom td { background-color: #1e293b; color: var(--c-text-main); border-bottom: 1px solid var(--c-border); padding: 12px 16px; vertical-align: middle; }
        .table-hover tbody tr:hover td { background-color: #334155; }
        
        /* Cột Ma trận Tiến độ gọn gàng */
        .prob-header { cursor: help; padding: 12px 8px !important; text-align: center; }
        .matrix-cell { width: 50px; text-align: center; font-family: 'JetBrains Mono', monospace; font-weight: bold; font-size: 1.1rem; }
        .matrix-ac { color: var(--c-primary); text-shadow: 0 0 8px rgba(16,185,129,0.6); }
        .matrix-none { color: #334155; font-size: 0.9rem; }
        
        /* Xếp Hạng Top 3 */
        .rank-1 { color: #fbbf24; font-weight: 800; font-size: 1.2rem; text-shadow: 0 0 10px rgba(251, 191, 36, 0.6); }
        .rank-2 { color: #cbd5e1; font-weight: 700; font-size: 1.1rem; text-shadow: 0 0 8px rgba(203, 213, 225, 0.5); }
        .rank-3 { color: #d97706; font-weight: 700; font-size: 1.1rem; }

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
        <span class="fw-bold text-white fs-5 tracking-wide">CyberRank</span>
    </div>
    <div class="d-flex align-items-center gap-3 text-white">
        <div class="d-none d-md-flex flex-column text-end me-2" style="line-height: 1.2;">
            <span class="small text-white fw-bold"><?= htmlspecialchars($currentFullName) ?></span>
            <span class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Thành Viên Trực Tuyến</span>
        </div>
        <?php if ($currentUserRole === 'admin'): ?>
            <a href="admin.php" class="btn btn-outline-glass btn-sm px-3 py-2">Trung tâm Quản trị</a>
        <?php endif; ?>
        <?php if ($currentUserRole === 'teacher'): ?>
            <a href="teacher.php" class="btn btn-outline-glass btn-sm px-3 py-2">Cổng Giảng viên</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-glass btn-sm px-3 py-2">Luyện Tập Ngay</a>
        <a href="logout.php" class="btn btn-cyber btn-sm px-3 py-2" style="background: rgba(239,68,68,0.2); color: #fca5a5; box-shadow: none;">Đăng xuất</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5 max-w-7xl mx-auto" style="max-width: 1400px;">
    
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1 d-flex align-items-center gap-2">
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16" class="text-primary"><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/><path d="M6 5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5zm-2 4a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm4 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm4 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-8 3a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm4 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm4 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>
                Thống Kê Học Tập Toàn Hệ Thống
            </h4>
            <span class="text-muted small">Cập nhật trực tiếp kết quả chấm bài của học viên.</span>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="small text-muted fw-bold text-nowrap">Bộ lọc Lớp:</label>
            <select name="class_code" class="form-select form-select-sm fw-bold mono" onchange="this.form.submit()" style="width: 160px;">
                <option value="ALL">Toàn Hệ Thống</option>
                <?php foreach($classes as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $classFilter === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="glass-card">
        <!-- Tabs Nav -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-flex align-items-center gap-2" id="leaderboard-tab" data-bs-toggle="tab" data-bs-target="#leaderboard" type="button" role="tab">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5c0 .538-.012 1.05-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33.076 33.076 0 0 1 2.5.5zm.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935zm10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935z"/></svg>
                    Bảng Xếp Hạng Điểm
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center gap-2" id="matrix-tab" data-bs-toggle="tab" data-bs-target="#matrix" type="button" role="tab">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 0h16v16H0V0zm1 1v14h14V1H1zm3 3h2v10H4V4zm4 3h2v7H8V7zm4-4h2v11h-2V3z"/></svg>
                    Ma Trận Tiến Độ (Progress)
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="myTabContent">
            <!-- TAB 1: LEADERBOARD -->
            <div class="tab-pane fade show active" id="leaderboard" role="tabpanel">
                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-custom table-hover mb-0">
                        <thead class="sticky-top" style="z-index: 5;">
                            <tr>
                                <th class="ps-4 text-center" style="width: 100px;">XẾP HẠNG</th>
                                <th>HỌC VIÊN (HACKER)</th>
                                <th>LỚP HỌC</th>
                                <th class="text-center">SỐ BÀI ĐÃ GIẢI (AC)</th>
                                <th class="text-end pe-5">TỔNG ĐIỂM SỐ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($scores as $s): 
                                $rankClass = '';
                                if ($rank === 1 && $s['total_score'] > 0) $rankClass = 'rank-1';
                                elseif ($rank === 2 && $s['total_score'] > 0) $rankClass = 'rank-2';
                                elseif ($rank === 3 && $s['total_score'] > 0) $rankClass = 'rank-3';
                            ?>
                                <tr>
                                    <td class="ps-4 text-center mono <?= $rankClass ?>" style="font-size: 1.15rem;">#<?= $rank ?></td>
                                    <td>
                                        <div class="fw-bold text-white mb-1" style="font-size: 1.05rem;"><?= htmlspecialchars($s['user']['username']) ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($s['user']['full_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill mono" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #cbd5e1; padding: 6px 12px;">
                                            <?= htmlspecialchars($s['user']['class_code']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center px-3 py-1 rounded" style="background: rgba(0,0,0,0.3); border: 1px inset rgba(255,255,255,0.05);">
                                            <span class="mono fw-bold text-white" style="font-size: 1.1rem;"><?= $s['total_ac'] ?></span>
                                            <span class="text-muted ms-2 small">/ <?= count($problems) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-5">
                                        <span class="mono fw-bold" style="color: var(--c-primary); font-size: 1.5rem; text-shadow: 0 0 12px rgba(16,185,129,0.3);">
                                            <?= $s['total_score'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                            
                            <?php if (empty($scores)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">Chưa có dữ liệu học viên trong danh sách này.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: PROGRESS MATRIX -->
            <div class="tab-pane fade" id="matrix" role="tabpanel">
                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-custom table-hover table-bordered mb-0" style="border-color: var(--c-border);">
                        <thead class="sticky-top" style="z-index: 5;">
                            <tr>
                                <th class="ps-4 align-middle bg-dark" style="min-width: 250px;">DANH SÁCH HỌC VIÊN</th>
                                <?php foreach ($problems as $p): ?>
                                    <th class="prob-header bg-dark text-center align-middle" title="<?= htmlspecialchars($p['title']) ?>" style="width: 50px;">
                                        <span class="text-white fw-bold">P<?= $p['id'] ?></span>
                                    </th>
                                <?php endforeach; ?>
                                <th class="text-center align-middle bg-dark pe-4" style="width: 100px;">TIẾN ĐỘ (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scores as $s): 
                                $uid = $s['user']['id'];
                                $acCount = $s['total_ac'];
                                $total = count($problems);
                                $percent = $total > 0 ? round(($acCount / $total) * 100) : 0;
                            ?>
                                <tr>
                                    <td class="ps-4 border-end" style="border-color: rgba(255,255,255,0.05) !important;">
                                        <div class="fw-bold text-white mb-1"><?= htmlspecialchars($s['user']['username']) ?></div>
                                        <span class="badge rounded-pill mono" style="background: rgba(255,255,255,0.05); color: #94a3b8; font-size: 0.7rem;">
                                            <?= htmlspecialchars($s['user']['class_code']) ?>
                                        </span>
                                    </td>
                                    <?php foreach ($problems as $p): 
                                        $hasAC = isset($matrix[$uid][$p['id']]);
                                    ?>
                                        <td class="matrix-cell border-end <?= $hasAC ? 'matrix-ac' : 'matrix-none' ?>" style="border-color: rgba(255,255,255,0.05) !important; background: <?= $hasAC ? 'rgba(16,185,129,0.05)' : 'transparent' ?>;">
                                            <?= $hasAC ? '✓' : '-' ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center align-middle pe-4">
                                        <div class="progress mb-2 rounded-pill" style="height: 6px; background-color: rgba(0,0,0,0.4); box-shadow: inset 0 1px 2px rgba(0,0,0,0.5);">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $percent ?>%; background: linear-gradient(90deg, #10b981, #34d399); box-shadow: 0 0 8px rgba(16,185,129,0.5);"></div>
                                        </div>
                                        <div class="mono fw-bold" style="font-size: 0.85rem; color: <?= $percent > 0 ? 'var(--c-primary)' : 'var(--c-text-muted)' ?>;"><?= $percent ?>%</div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>