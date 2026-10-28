<?php
// public/api/rejudge.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$teacherId   = (int)($input['user_id'] ?? 1);
$problemId   = (int)($input['problem_id'] ?? 1);
$timeLimitMs = (int)($input['time_limit_ms'] ?? 1000);
$testCases   = $input['test_cases'] ?? [];

$pdo = getDBConnection();

// Kiểm tra quyền Giảng viên
$stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmtRole->execute([$teacherId]);
if ($stmtRole->fetchColumn() !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Chỉ giảng viên mới được tạo phiên bản dữ liệu mới và chấm lại']);
    exit;
}

$pdo->beginTransaction();
try {
    // 1. Tính số phiên bản tiếp theo (version_number + 1)
    $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 FROM problem_versions WHERE problem_id = ?");
    $stmtMax->execute([$problemId]);
    $nextVerNum = (int)$stmtMax->fetchColumn();

    // 2. Tạo phiên bản bộ dữ liệu mới (problem_versions)
    $stmtVer = $pdo->prepare("
        INSERT INTO problem_versions (problem_id, version_number, time_limit_ms, memory_limit_kb, created_by)
        VALUES (?, ?, ?, 65536, ?)
    ");
    $stmtVer->execute([$problemId, $nextVerNum, $timeLimitMs, $teacherId]);
    $newVersionId = (int)$pdo->lastInsertId();

    // 3. Thêm danh sách test_cases mới vào phiên bản mới
    $stmtTc = $pdo->prepare("
        INSERT INTO test_cases (problem_version_id, case_order, stdin_data, expected_stdout, is_public)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($testCases as $idx => $tc) {
        $stmtTc->execute([
            $newVersionId,
            $idx + 1,
            $tc['stdin'],
            $tc['stdout'],
            !empty($tc['is_public']) ? 1 : 0
        ]);
    }

    // 4. Cập nhật current_version_id cho bài tập
    $pdo->prepare("UPDATE problems SET current_version_id = ? WHERE id = ?")->execute([$newVersionId, $problemId]);

    // 5. Kích hoạt chấm lại (Tạo judging_runs mới cho tất cả bài nộp của đề này, KHÔNG ghi đè lần chấm cũ)
    $stmtSubs = $pdo->prepare("SELECT id FROM submissions WHERE problem_id = ?");
    $stmtSubs->execute([$problemId]);
    $subs = $stmtSubs->fetchAll();

    $rejudgedCount = 0;
    foreach ($subs as $s) {
        $stmtRun = $pdo->prepare("
            INSERT INTO judging_runs (submission_id, problem_version_id, is_rejudge, status)
            VALUES (?, ?, TRUE, 'queued')
        ");
        $stmtRun->execute([$s['id'], $newVersionId]);
        $newRunId = (int)$pdo->lastInsertId();

        // Trỏ latest_run_id sang lần chấm mới nhất (Phép thử 4: Kết quả cũ về muộn không ghi đè lần mới)
        $pdo->prepare("UPDATE submissions SET latest_run_id = ? WHERE id = ?")->execute([$newRunId, $s['id']]);
        $rejudgedCount++;
    }

    $pdo->commit();
    echo json_encode([
        'new_version_id' => $newVersionId,
        'version_number' => $nextVerNum,
        'rejudged_submissions' => $rejudgedCount
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}