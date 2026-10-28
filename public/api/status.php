<?php
// public/api/status.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$pdo = getDBConnection();
$viewerId     = (int)($_GET['viewer_id'] ?? 2);
$submissionId = (int)($_GET['submission_id'] ?? 0);

// Lấy thông tin người đang xem
$stmtUser = $pdo->prepare("SELECT id, role, class_code FROM users WHERE id = ?");
$stmtUser->execute([$viewerId]);
$viewer = $stmtUser->fetch();

if ($submissionId > 0) {
    $stmtSub = $pdo->prepare("
        SELECT s.*, u.username, u.class_code, p.title AS problem_title
        FROM submissions s
        JOIN users u ON u.id = s.user_id
        JOIN problems p ON p.id = s.problem_id
        WHERE s.id = ?
    ");
    $stmtSub->execute([$submissionId]);
    $sub = $stmtSub->fetch();

    if (!$sub) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy bài nộp']);
        exit;
    }

    // Phép thử 5: Người A không đọc được mã riêng của B (Chỉ chủ bài nộp hoặc Giảng viên cùng lớp được xem)
    $canViewCode = ($viewer['role'] === 'teacher' && $viewer['class_code'] === $sub['class_code'])
                   || ((int)$sub['user_id'] === $viewerId);

    if (!$canViewCode) {
        http_response_code(403);
        echo json_encode(['error' => '403 Forbidden: Bạn không có quyền xem mã nguồn riêng của người học khác!']);
        exit;
    }

    // Lấy toàn bộ lịch sử các lần chấm (judging_runs) của bài nộp này
    $stmtRuns = $pdo->prepare("
        SELECT jr.*, pv.version_number
        FROM judging_runs jr
        JOIN problem_versions pv ON pv.id = jr.problem_version_id
        WHERE jr.submission_id = ?
        ORDER BY jr.id DESC
    ");
    $stmtRuns->execute([$submissionId]);
    $runs = $stmtRuns->fetchAll();

    foreach ($runs as &$r) {
        // Phép thử 5: Tuyệt đối KHÔNG trả dữ liệu chấm kín (stdin, expected_stdout, actual_stdout của test kín)
        $stmtCases = $pdo->prepare("
            SELECT cr.id, cr.status, cr.execution_time_ms, tc.case_order, tc.is_public,
                   CASE WHEN tc.is_public = 1 THEN tc.stdin_data ELSE '[Dữ liệu chấm kín]' END AS stdin_preview,
                   CASE WHEN tc.is_public = 1 THEN tc.expected_stdout ELSE '[Ẩn]' END AS expected_preview,
                   CASE WHEN tc.is_public = 1 THEN cr.actual_stdout ELSE '[Ẩn để bảo vệ test kín]' END AS actual_preview
            FROM case_results cr
            JOIN test_cases tc ON tc.id = cr.test_case_id
            WHERE cr.judging_run_id = ?
            ORDER BY tc.case_order ASC
        ");
        $stmtCases->execute([$r['id']]);
        $r['cases'] = $stmtCases->fetchAll();
    }

    echo json_encode([
        'submission' => $sub,
        'latest_run' => $runs[0] ?? null,
        'history_runs' => $runs
    ]);
    exit;
}

// Nếu không truyền submission_id -> Trả về danh sách lịch sử bài nộp & tiến độ
$stmtList = $pdo->query("
    SELECT s.id, s.user_id, u.username, s.problem_id, p.title, s.language, s.initial_version_id,
           s.latest_run_id, s.created_at,
           jr.status AS latest_status, jr.passed_cases, jr.total_cases, pv.version_number AS judged_version
    FROM submissions s
    JOIN users u ON u.id = s.user_id
    JOIN problems p ON p.id = s.problem_id
    LEFT JOIN judging_runs jr ON jr.id = s.latest_run_id
    LEFT JOIN problem_versions pv ON pv.id = jr.problem_version_id
    ORDER BY s.id DESC
");
echo json_encode(['submissions' => $stmtList->fetchAll()]);