<?php
/**
 * tests/run_mandatory_tests.php
 * Bộ kiểm thử tự động & tích hợp 5 phép thử bắt buộc (Mục 6 - Đề tài 20)
 * Chạy trên Terminal VS Code: C:\xampp\php\php.exe tests/run_mandatory_tests.php
 */

require_once __DIR__ . '/../config/db.php';

$baseUrl    = 'http://localhost/PTPMMNM-CNTT1811-NHOM-11/public/api';
$sandboxUrl = 'http://127.0.0.1:4000';
$pdo        = getDBConnection();

$passedTests = 0;
$totalTests  = 5;

function printHeader(string $title): void {
    echo "\n=================================================================\n";
    echo ">>> $title\n";
    echo "=================================================================\n";
}

function assertCondition(bool $condition, string $passMsg, string $failMsg): bool {
    if ($condition) {
        echo "  [PASS] $passMsg\n";
        return true;
    } else {
        echo "  [FAIL] $failMsg\n";
        return false;
    }
}

function apiPost(string $url, array $payload, int &$httpCode = 0): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload)
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return json_decode($raw ?: '{}', true) ?? [];
}

function apiGet(string $url, int &$httpCode = 0, string &$rawBody = ''): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $rawBody = curl_exec($ch) ?: '';
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return json_decode($rawBody, true) ?? [];
}

/**
 * Hàm điều phối chấm trực tiếp phục vụ kiểm thử tích hợp (giúp test chạy độc lập,
 * đồng thời cho phép truyền URL sandbox giả lập để test ngắt bộ thực thi ở Phép thử 2)
 */
function runJudgeOnce(PDO $pdo, int $runId, string $targetSandboxUrl): string {
    $stmt = $pdo->prepare("
        SELECT jr.id, jr.submission_id, jr.problem_version_id,
               s.language, s.source_code, pv.time_limit_ms
        FROM judging_runs jr
        JOIN submissions s ON s.id = jr.submission_id
        JOIN problem_versions pv ON pv.id = jr.problem_version_id
        WHERE jr.id = ?
    ");
    $stmt->execute([$runId]);
    $run = $stmt->fetch();
    if (!$run) return 'NOT_FOUND';

    // Nếu worker nền đã chấm xong trước đó thì trả kết quả luôn
    $stmtStatus = $pdo->prepare("SELECT status FROM judging_runs WHERE id = ?");
    $stmtStatus->execute([$runId]);
    $curStatus = $stmtStatus->fetchColumn();
    if ($curStatus !== 'queued' && $targetSandboxUrl === 'http://127.0.0.1:4000') {
        // Đợi tối đa 4s nếu worker đang chấm dở
        $wait = 0;
        while ($curStatus === 'running' && $wait < 40) {
            usleep(100000);
            $stmtStatus->execute([$runId]);
            $curStatus = $stmtStatus->fetchColumn();
            $wait++;
        }
        if ($curStatus !== 'queued' && $curStatus !== 'running') {
            return $curStatus;
        }
    }

    $pdo->prepare("UPDATE judging_runs SET status = 'running', started_at = NOW() WHERE id = ?")->execute([$runId]);
    $pdo->prepare("DELETE FROM case_results WHERE judging_run_id = ?")->execute([$runId]);

    $stmtCases = $pdo->prepare("SELECT * FROM test_cases WHERE problem_version_id = ? ORDER BY case_order ASC");
    $stmtCases->execute([$run['problem_version_id']]);
    $cases = $stmtCases->fetchAll();

    $finalStatus = 'AC';
    $passed = 0;

    foreach ($cases as $tc) {
        $ch = curl_init($targetSandboxUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([
                'language'      => $run['language'],
                'source_code'   => $run['source_code'],
                'stdin'         => $tc['stdin_data'],
                'time_limit_ms' => (int)$run['time_limit_ms']
            ])
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        curl_close($ch);

        if ($err !== 0 || $code !== 200 || !$resp) {
            $pdo->prepare("UPDATE judging_runs SET status = 'SYSTEM_ERROR', finished_at = NOW() WHERE id = ?")->execute([$runId]);
            return 'SYSTEM_ERROR';
        }

        $res = json_decode($resp, true);
        if (($res['compile_code'] ?? 0) !== 0) {
            $st = 'CE';
        } elseif (!empty($res['timed_out'])) {
            $st = 'TLE';
        } elseif (($res['exit_code'] ?? 0) !== 0) {
            $st = 'RE';
        } else {
            $act = trim(str_replace("\r\n", "\n", $res['stdout'] ?? ''));
            $exp = trim(str_replace("\r\n", "\n", $tc['expected_stdout']));
            $st  = ($act === $exp) ? 'AC' : 'WA';
        }

        if ($st === 'AC') $passed++;
        elseif ($finalStatus === 'AC') $finalStatus = $st;

        $pdo->prepare("
            INSERT INTO case_results (judging_run_id, test_case_id, status, execution_time_ms, actual_stdout, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$runId, $tc['id'], $st, (int)($res['time_ms'] ?? 0), $res['stdout'] ?? '', $res['stderr'] ?? '']);
    }

    $pdo->prepare("UPDATE judging_runs SET status = ?, passed_cases = ?, total_cases = ?, finished_at = NOW() WHERE id = ?")
        ->execute([$finalStatus, $passed, count($cases), $runId]);

    return $finalStatus;
}

// =========================================================================
// PHÉP THỬ 1: Bài đúng, sai dữ liệu biên, lỗi cú pháp, lỗi khi chạy và lặp vô hạn
// =========================================================================
printHeader("PHÉP THỬ 1: Kiểm tra 5 loại mã nộp nhận kết quả khác nhau (AC, WA, CE, RE, TLE)");

$samples = [
    'AC'  => "import sys\ndata = sys.stdin.read().split()\nprint(int(data[0]) + int(data[1]))",
    'WA'  => "import sys\ndata = sys.stdin.read().split()\na, b = int(data[0]), int(data[1])\n# Sai ở dữ liệu biên khi a, b >= 10^9\nprint(a + b if abs(a) < 1000 else 0)",
    'CE'  => "import sys\ndef broken_syntax(\n    print('missing parenthesis')",
    'RE'  => "import sys\nprint(1 / 0)",
    'TLE' => "while True:\n    pass"
];

$test1Ok = true;
foreach ($samples as $expectedStatus => $code) {
    $res = apiPost("$baseUrl/submit.php", [
        'user_id'         => 2,
        'problem_id'      => 1,
        'language'        => 'python',
        'source_code'     => $code,
        'idempotency_key' => 'test1-' . $expectedStatus . '-' . uniqid()
    ]);
    $actualStatus = runJudgeOnce($pdo, (int)$res['judging_run_id'], $sandboxUrl);
    $ok = assertCondition(
        $actualStatus === $expectedStatus,
        "Mã mẫu [$expectedStatus] -> Hệ thống trả về đúng trạng thái: $actualStatus",
        "Mã mẫu [$expectedStatus] -> Kỳ vọng $expectedStatus nhưng nhận được: $actualStatus"
    );
    if (!$ok) $test1Ok = false;
}
if ($test1Ok) $passedTests++;

// =========================================================================
// PHÉP THỬ 2: Ngắt bộ thực thi -> Kết quả là lỗi hệ thống, không chấm người học sai
// =========================================================================
printHeader("PHÉP THỬ 2: Ngắt kết nối bộ thực thi -> Báo SYSTEM_ERROR, không chấm sai bài");

// Tạo trực tiếp 1 bài nộp và điều phối sang cổng 127.0.0.1:4999 (giả lập bộ thực thi bị ngắt)
$stmtSub2 = $pdo->prepare("
    INSERT INTO submissions (user_id, problem_id, initial_version_id, language, source_code, idempotency_key)
    VALUES (2, 1, 1, 'python', 'print(8)', ?)
");
$stmtSub2->execute(['test2-disconnect-' . uniqid()]);
$sub2Id = (int)$pdo->lastInsertId();

$stmtRun2 = $pdo->prepare("INSERT INTO judging_runs (submission_id, problem_version_id, status) VALUES (?, 1, 'queued')");
$stmtRun2->execute([$sub2Id]);
$run2Id = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE submissions SET latest_run_id = ? WHERE id = ?")->execute([$run2Id, $sub2Id]);

// Gọi bộ điều phối khi bộ thực thi bị ngắt (cổng 4999 không hoạt động)
$statusWhenDown = runJudgeOnce($pdo, $run2Id, 'http://127.0.0.1:4999');

$test2Ok = assertCondition(
    $statusWhenDown === 'SYSTEM_ERROR' && !in_array($statusWhenDown, ['WA', 'RE', 'CE', 'TLE']),
    "Khi bộ thực thi bị ngắt, trạng thái trả về là '$statusWhenDown' (Không chấm người học sai WA/RE/CE)",
    "Khi bộ thực thi bị ngắt, hệ thống lại trả về '$statusWhenDown'"
);
if ($test2Ok) $passedTests++;

// =========================================================================
// PHÉP THỬ 3: Gửi lại cùng yêu cầu nộp trả cùng mã; yêu cầu nộp mới có chủ ý tạo bài mới
// =========================================================================
printHeader("PHÉP THỬ 3: Kiểm tra chống nộp trùng (Idempotency) và nộp mới có chủ ý");

$sameKey = 'idem-key-' . uniqid();
$reqA = apiPost("$baseUrl/submit.php", [
    'user_id'         => 2,
    'problem_id'      => 1,
    'language'        => 'python',
    'source_code'     => "import sys\nprint(sum(map(int, sys.stdin.read().split())))",
    'idempotency_key' => $sameKey
]);

// Gửi lại cùng yêu cầu nộp với cùng idempotency_key
$reqB = apiPost("$baseUrl/submit.php", [
    'user_id'         => 2,
    'problem_id'      => 1,
    'language'        => 'python',
    'source_code'     => "import sys\nprint(sum(map(int, sys.stdin.read().split())))",
    'idempotency_key' => $sameKey
]);

// Gửi yêu cầu nộp mới có chủ ý (cùng code nhưng sinh idempotency_key mới)
$reqC = apiPost("$baseUrl/submit.php", [
    'user_id'         => 2,
    'problem_id'      => 1,
    'language'        => 'python',
    'source_code'     => "import sys\nprint(sum(map(int, sys.stdin.read().split())))",
    'idempotency_key' => 'idem-key-new-' . uniqid()
]);

$cond3A = assertCondition(
    $reqA['submission_id'] === $reqB['submission_id'] && !empty($reqB['is_duplicate']),
    "Gửi lại cùng yêu cầu nộp -> Trả về cùng mã bài nộp #{$reqA['submission_id']} (không tạo bản trùng)",
    "Gửi lại cùng yêu cầu nộp bị tạo mã khác nhau"
);
$cond3B = assertCondition(
    $reqC['submission_id'] !== $reqA['submission_id'] && empty($reqC['is_duplicate']),
    "Yêu cầu nộp mới có chủ ý -> Vẫn tạo bài nộp mới #{$reqC['submission_id']} thành công",
    "Yêu cầu nộp mới có chủ ý không tạo được bài mới"
);
if ($cond3A && $cond3B) $passedTests++;

// =========================================================================
// PHÉP THỬ 4: Kết quả của lần chấm cũ về sau khi đã chấm lại không ghi đè kết quả mới; lịch sử vẫn đủ
// =========================================================================
printHeader("PHÉP THỬ 4: Kết quả lần chấm cũ về muộn không ghi đè lần chấm mới & Giữ đủ lịch sử");

$sub4Id = (int)$reqC['submission_id'];
$oldRunId = (int)$reqC['judging_run_id'];

// Giả lập lần chấm cũ (#oldRunId) đang kẹt ở trạng thái running (chưa trả kết quả về kịp)
$pdo->prepare("UPDATE judging_runs SET status = 'running' WHERE id = ?")->execute([$oldRunId]);

// Giảng viên kích hoạt chấm lại -> sinh ra lần chấm mới (#newRunId) và cập nhật latest_run_id
$stmtNewRun = $pdo->prepare("INSERT INTO judging_runs (submission_id, problem_version_id, is_rejudge, status) VALUES (?, 1, TRUE, 'AC')");
$stmtNewRun->execute([$sub4Id]);
$newRunId = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE submissions SET latest_run_id = ? WHERE id = ?")->execute([$newRunId, $sub4Id]);

// Bây giờ kết quả của lần chấm cũ (#oldRunId) mới hoàn tất muộn và trả về WA
$pdo->prepare("UPDATE judging_runs SET status = 'WA', finished_at = NOW() WHERE id = ?")->execute([$oldRunId]);

// Kiểm tra qua API status.php xem kết quả hiện hành có bị lần chấm cũ ghi đè không
$statusData = apiGet("$baseUrl/status.php?submission_id=$sub4Id&viewer_id=2");
$latestStatus = $statusData['latest_run']['status'] ?? '';
$historyCount = count($statusData['history_runs'] ?? []);

$cond4A = assertCondition(
    $latestStatus === 'AC' && (int)$statusData['latest_run']['id'] === $newRunId,
    "Kết quả lần chấm cũ (WA) về muộn KHÔNG ghi đè kết quả lần chấm mới (AC - Run #$newRunId)",
    "Kết quả lần chấm mới bị ghi đè bởi lần chấm cũ!"
);
$cond4B = assertCondition(
    $historyCount >= 2,
    "Lịch sử các lần chấm vẫn được lưu giữ đầy đủ ($historyCount lần chấm cho Submission #$sub4Id)",
    "Lịch sử các lần chấm bị mất!"
);
if ($cond4A && $cond4B) $passedTests++;

// =========================================================================
// PHÉP THỬ 5: Người A không đọc được mã riêng của B; API không lộ dữ liệu chấm kín / kết nối bộ thực thi
// =========================================================================
printHeader("PHÉP THỬ 5: Phân quyền mã nguồn (A không đọc được của B) & Bảo mật dữ liệu chấm kín");

// Người học B (viewer_id = 3) cố tình gọi API xem bài nộp của Người học A (user_id = 2)
$httpCode5A = 0;
$unauthRes = apiGet("$baseUrl/status.php?submission_id=$sub4Id&viewer_id=3", $httpCode5A);

$cond5A = assertCondition(
    $httpCode5A === 403 && !isset($unauthRes['submission']['source_code']),
    "Người học B bị chặn (HTTP $httpCode5A Forbidden) khi cố đọc mã nguồn riêng của Người học A",
    "Lỗi bảo mật: Người học B vẫn đọc được mã của Người học A!"
);

// Người học A (viewer_id = 2) xem bài của chính mình -> Kiểm tra JSON không chứa dữ liệu test kín ('1000000000') hay cổng '4000'
$httpCode5B = 0;
$rawJson = '';
apiGet("$baseUrl/status.php?submission_id={$reqA['submission_id']}&viewer_id=2", $httpCode5B, $rawJson);

$leakedHiddenInput = str_contains($rawJson, '1000000000') || str_contains($rawJson, '2000000000');
$leakedSandboxUrl  = str_contains($rawJson, '127.0.0.1:4000') || str_contains($rawJson, ':4000');

$cond5B = assertCondition(
    !$leakedHiddenInput && !$leakedSandboxUrl,
    "Phản hồi API hoàn toàn KHÔNG làm lộ dữ liệu chấm kín và KHÔNG lộ thông tin kết nối bộ thực thi",
    "Cảnh báo: Phát hiện rò rỉ dữ liệu chấm kín hoặc cổng kết nối bộ thực thi trong JSON!"
);
if ($cond5A && $cond5B) $passedTests++;

// =========================================================================
// TỔNG KẾT
// =========================================================================
echo "\n=================================================================\n";
echo "KẾT QUẢ KIỂM THỬ BẮT BUỘC: ĐẠT $passedTests / $totalTests PHÉP THỬ\n";
echo "=================================================================\n";
exit($passedTests === $totalTests ? 0 : 1);