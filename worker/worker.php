<?php
// worker/worker.php
// Chạy độc lập ở Terminal 2 bằng lệnh: C:\xampp\php\php.exe worker/worker.php

require_once __DIR__ . '/../config/db.php';

$sandboxUrl = 'http://127.0.0.1:4000';
echo "[Judge Worker] Đang lắng nghe hàng đợi chấm bài...\n";

// Hàm chuẩn hóa đầu ra (bỏ khoảng trắng thừa cuối dòng và cuối file)
function normalizeOutput(string $str): string {
    $str = str_replace("\r\n", "\n", trim($str));
    if ($str === '') return '';
    $lines = explode("\n", $str);
    return implode("\n", array_map('rtrim', $lines));
}

while (true) {
    try {
        $pdo = getDBConnection();

        // Lấy 1 lần chấm đang ở trạng thái queued
        $stmt = $pdo->query("
            SELECT jr.id, jr.submission_id, jr.problem_version_id,
                   s.language, s.source_code,
                   pv.time_limit_ms, pv.memory_limit_kb
            FROM judging_runs jr
            JOIN submissions s ON s.id = jr.submission_id
            JOIN problem_versions pv ON pv.id = jr.problem_version_id
            WHERE jr.status = 'queued'
            ORDER BY jr.id ASC
            LIMIT 1
        ");
        $run = $stmt->fetch();

        if (!$run) {
            usleep(500000); // Nghỉ 0.5 giây nếu chưa có bài mới
            continue;
        }

        $runId = (int)$run['id'];
        echo "[Judge Worker] Bắt đầu chấm judging_run #$runId (Submission #{$run['submission_id']})...\n";

        $pdo->prepare("UPDATE judging_runs SET status = 'running', started_at = NOW() WHERE id = ?")
            ->execute([$runId]);

        // Lấy danh sách test cases của đúng phiên bản bộ dữ liệu
        $stmtCases = $pdo->prepare("SELECT * FROM test_cases WHERE problem_version_id = ? ORDER BY case_order ASC");
        $stmtCases->execute([$run['problem_version_id']]);
        $testCases = $stmtCases->fetchAll();

        // Xóa case_results cũ nếu lần chấm này được retry
        $pdo->prepare("DELETE FROM case_results WHERE judging_run_id = ?")->execute([$runId]);

        $finalStatus = 'AC';
        $passedCount = 0;
        $systemError = false;

        foreach ($testCases as $tc) {
            $ch = curl_init($sandboxUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode([
                    'language'      => $run['language'],
                    'source_code'   => $run['source_code'],
                    'stdin'         => $tc['stdin_data'],
                    'time_limit_ms' => (int)$run['time_limit_ms']
                ])
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_errno($ch);
            curl_close($ch);

            // Phép thử 2: Ngắt bộ thực thi -> Báo SYSTEM_ERROR, không chấm người học sai
            if ($curlErr !== 0 || $httpCode !== 200 || !$response) {
                echo "[Judge Worker] LỖI HỆ THỐNG: Không kết nối được bộ thực thi cho run #$runId!\n";
                $pdo->prepare("UPDATE judging_runs SET status = 'SYSTEM_ERROR', finished_at = NOW() WHERE id = ?")
                    ->execute([$runId]);
                $systemError = true;
                break;
            }

            $res = json_decode($response, true);

            // Phân loại kết quả theo đúng quy tắc công bố (Phép thử 1)
            if (($res['compile_code'] ?? 0) !== 0) {
                $caseStatus = 'CE';
            } elseif (!empty($res['timed_out'])) {
                $caseStatus = 'TLE';
            } elseif (($res['exit_code'] ?? 0) !== 0) {
                $caseStatus = 'RE';
            } else {
                $actual   = normalizeOutput($res['stdout'] ?? '');
                $expected = normalizeOutput($tc['expected_stdout']);
                $caseStatus = ($actual === $expected) ? 'AC' : 'WA';
            }

            if ($caseStatus === 'AC') {
                $passedCount++;
            } elseif ($finalStatus === 'AC') {
                $finalStatus = $caseStatus;
            }

            $pdo->prepare("
                INSERT INTO case_results (judging_run_id, test_case_id, status, execution_time_ms, actual_stdout, error_message)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $runId,
                $tc['id'],
                $caseStatus,
                (int)($res['time_ms'] ?? 0),
                $res['stdout'] ?? '',
                $res['stderr'] ?? ''
            ]);
        }

        if (!$systemError) {
            $pdo->prepare("
                UPDATE judging_runs
                SET status = ?, passed_cases = ?, total_cases = ?, finished_at = NOW()
                WHERE id = ?
            ")->execute([$finalStatus, $passedCount, count($testCases), $runId]);

            echo "[Judge Worker] Hoàn tất run #$runId -> Kết quả: $finalStatus ($passedCount/" . count($testCases) . ")\n";
        }
    } catch (Exception $e) {
        echo "[Judge Worker] Lỗi ngoại lệ: " . $e->getMessage() . "\n";
        sleep(2);
    }
}