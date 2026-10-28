<?php
// public/api/run_draft.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$userId     = (int)($input['user_id'] ?? 2);
$problemId  = (int)($input['problem_id'] ?? 1);
$language   = $input['language'] ?? 'python';
$sourceCode = $input['source_code'] ?? '';
$customIn   = $input['stdin'] ?? '';
$action     = $input['action'] ?? 'run'; // 'save' hoặc 'run'

$pdo = getDBConnection();

// Lưu bản nháp trên trình duyệt vào CSDL
$stmt = $pdo->prepare("
    INSERT INTO code_drafts (user_id, problem_id, language, source_code)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE source_code = VALUES(source_code), updated_at = NOW()
");
$stmt->execute([$userId, $problemId, $language, $sourceCode]);

if ($action === 'save') {
    echo json_encode(['saved' => true, 'message' => 'Đã lưu bản nháp']);
    exit;
}

// Chạy thử với dữ liệu tự nhập qua dịch vụ thực thi cách ly
$ch = curl_init('http://127.0.0.1:4000');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        'language'      => $language,
        'source_code'   => $sourceCode,
        'stdin'         => $customIn,
        'time_limit_ms' => 1500
    ])
]);
$resRaw   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$resRaw || $httpCode !== 200) {
    echo json_encode(['status' => 'SYSTEM_ERROR', 'stdout' => '', 'stderr' => 'Dịch vụ thực thi đang tạm ngắt hoặc bận.']);
    exit;
}

$res = json_decode($resRaw, true);
echo json_encode([
    'status'  => ($res['compile_code'] !== 0) ? 'CE' : ($res['timed_out'] ? 'TLE' : ($res['exit_code'] !== 0 ? 'RE' : 'OK')),
    'time_ms' => $res['time_ms'] ?? 0,
    'stdout'  => substr($res['stdout'] ?? '', 0, 4096),
    'stderr'  => substr($res['stderr'] ?? '', 0, 4096)
]);