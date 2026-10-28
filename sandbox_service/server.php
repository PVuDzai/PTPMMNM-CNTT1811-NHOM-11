<?php
// sandbox_service/server.php
// Chạy độc lập ở Terminal 1 bằng lệnh: C:\xampp\php\php.exe -S 127.0.0.1:4000 sandbox_service/server.php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$language    = $input['language'] ?? 'python';
$sourceCode  = $input['source_code'] ?? '';
$stdinData   = $input['stdin'] ?? '';
$timeLimitMs = (int)($input['time_limit_ms'] ?? 1000);

// Tạo thư mục thực thi cách ly bên ngoài thư mục Web (C:\oj_sandbox_temp)
$baseTempDir = 'C:\\oj_sandbox_temp';
if (!is_dir($baseTempDir)) {
    mkdir($baseTempDir, 0777, true);
}
$runDir = $baseTempDir . '\\run_' . uniqid('', true);
mkdir($runDir, 0777, true);

// Hàm dọn dẹp thư mục tạm sau khi chạy xong
function cleanupDir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $file) {
        if ($file !== '.' && $file !== '..') {
            @unlink("$dir\\$file");
        }
    }
    @rmdir($dir);
}

$stdinFile  = "$runDir\\input.txt";
$stdoutFile = "$runDir\\output.txt";
$stderrFile = "$runDir\\error.txt";
file_put_contents($stdinFile, $stdinData);

// 1. Bước kiểm tra cú pháp / Biên dịch (Compile)
if ($language === 'python') {
    $srcFile = "$runDir\\main.py";
    file_put_contents($srcFile, $sourceCode);

    // Kiểm tra lỗi cú pháp Python trước khi chạy
    exec("python -m py_compile \"$srcFile\" 2> \"$stderrFile\"", $compileOut, $compileCode);
    if ($compileCode !== 0) {
        $err = file_get_contents($stderrFile);
        cleanupDir($runDir);
        echo json_encode([
            'compile_code' => $compileCode,
            'exit_code'    => 0,
            'timed_out'    => false,
            'time_ms'      => 0,
            'stdout'       => '',
            'stderr'       => $err
        ]);
        exit;
    }
    $runCmd = "python \"$srcFile\"";
} elseif ($language === 'cpp') {
    $srcFile = "$runDir\\main.cpp";
    $exeFile = "$runDir\\main.exe";
    file_put_contents($srcFile, $sourceCode);

    exec("g++ \"$srcFile\" -O2 -o \"$exeFile\" 2> \"$stderrFile\"", $compileOut, $compileCode);
    if ($compileCode !== 0) {
        $err = file_get_contents($stderrFile);
        cleanupDir($runDir);
        echo json_encode([
            'compile_code' => $compileCode,
            'exit_code'    => 0,
            'timed_out'    => false,
            'time_ms'      => 0,
            'stdout'       => '',
            'stderr'       => $err
        ]);
        exit;
    }
    $runCmd = "\"$exeFile\"";
} else {
    cleanupDir($runDir);
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported language']);
    exit;
}

// 2. Bước thực thi chương trình với giới hạn thời gian (Time Limit)
$descriptors = [
    0 => ['file', $stdinFile, 'r'],
    1 => ['file', $stdoutFile, 'w'],
    2 => ['file', $stderrFile, 'w']
];

$startTime = microtime(true);
$process = proc_open($runCmd, $descriptors, $pipes, $runDir);

$timedOut = false;
$exitCode = -1;
$elapsedMs = 0;

if (is_resource($process)) {
    while (true) {
        $status = proc_get_status($process);
        $elapsedMs = (int)round((microtime(true) - $startTime) * 1000);

        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }

        if ($elapsedMs > $timeLimitMs) {
            $timedOut = true;
            // Diệt tiến trình bị lặp vô hạn trên Windows
            $pid = $status['pid'];
            exec("taskkill /F /T /PID $pid 2>NUL");
            break;
        }
        usleep(10000); // Kiểm tra mỗi 10ms
    }
    proc_close($process);
}

$stdout = file_exists($stdoutFile) ? substr(file_get_contents($stdoutFile), 0, 65536) : '';
$stderr = file_exists($stderrFile) ? substr(file_get_contents($stderrFile), 0, 65536) : '';
cleanupDir($runDir);

echo json_encode([
    'compile_code' => 0,
    'exit_code'    => $exitCode,
    'timed_out'    => $timedOut,
    'time_ms'      => $elapsedMs,
    'stdout'       => $stdout,
    'stderr'       => $stderr
]);