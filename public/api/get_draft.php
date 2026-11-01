<?php
// public/api/get_draft.php - API lấy bản nháp mã nguồn
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$userId    = (int)($_GET['user_id'] ?? 0);
$problemId = (int)($_GET['problem_id'] ?? 0);
$language  = $_GET['language'] ?? 'python';

if ($userId > 0 && $problemId > 0) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT source_code FROM code_drafts WHERE user_id = ? AND problem_id = ? AND language = ?");
    $stmt->execute([$userId, $problemId, $language]);
    $draft = $stmt->fetchColumn();
    
    if ($draft) {
        echo json_encode(['success' => true, 'draft' => $draft]);
        exit;
    }
}
echo json_encode(['success' => false, 'draft' => '']);