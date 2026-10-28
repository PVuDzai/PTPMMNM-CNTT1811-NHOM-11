<?php
// public/api/submit.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$userId         = (int)($input['user_id'] ?? 2);
$problemId      = (int)($input['problem_id'] ?? 1);
$language       = $input['language'] ?? 'python';
$sourceCode     = $input['source_code'] ?? '';
$idempotencyKey = trim($input['idempotency_key'] ?? '');

if ($idempotencyKey === '' || trim($sourceCode) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu mã nguồn hoặc idempotency_key']);
    exit;
}

$pdo = getDBConnection();
$pdo->beginTransaction();

try {
    // 1. Kiểm tra Idempotency (Phép thử 3: Gửi lại cùng yêu cầu nộp -> trả cùng mã bài nộp)
    $stmtCheck = $pdo->prepare("SELECT id, latest_run_id FROM submissions WHERE user_id = ? AND idempotency_key = ?");
    $stmtCheck->execute([$userId, $idempotencyKey]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $pdo->commit();
        echo json_encode([
            'submission_id'  => (int)$existing['id'],
            'judging_run_id' => (int)$existing['latest_run_id'],
            'is_duplicate'   => true
        ]);
        exit;
    }

    // 2. Lấy phiên bản bộ dữ liệu hiện hành của đề bài
    $stmtVer = $pdo->prepare("SELECT current_version_id FROM problems WHERE id = ?");
    $stmtVer->execute([$problemId]);
    $versionId = (int)$stmtVer->fetchColumn();

    // 3. Tạo bài nộp mới gắn đúng đề, phiên bản bộ dữ liệu và ngôn ngữ
    $stmtSub = $pdo->prepare("
        INSERT INTO submissions (user_id, problem_id, initial_version_id, language, source_code, idempotency_key)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtSub->execute([$userId, $problemId, $versionId, $language, $sourceCode, $idempotencyKey]);
    $submissionId = (int)$pdo->lastInsertId();

    // 4. Đưa vào hàng đợi chấm (judging_runs)
    $stmtRun = $pdo->prepare("
        INSERT INTO judging_runs (submission_id, problem_version_id, is_rejudge, status)
        VALUES (?, ?, FALSE, 'queued')
    ");
    $stmtRun->execute([$submissionId, $versionId]);
    $runId = (int)$pdo->lastInsertId();

    // 5. Cập nhật latest_run_id trỏ tới lần chấm mới nhất
    $pdo->prepare("UPDATE submissions SET latest_run_id = ? WHERE id = ?")->execute([$runId, $submissionId]);

    $pdo->commit();
    echo json_encode([
        'submission_id'  => $submissionId,
        'judging_run_id' => $runId,
        'is_duplicate'   => false
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi tạo bài nộp']);
}