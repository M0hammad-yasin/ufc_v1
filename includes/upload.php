<?php
/**
 * United Five Construction - Secure Evidence Upload Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE_BYTES', 25 * 1024 * 1024); // 25 MB

$allowedMimeTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/acad',
    'image/vnd.dwg',
    'image/x-dwg'
];

$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'dwg'];

function handleEvidenceUpload(int $assessmentId, int $questionId, ?int $explainBlockId, array $fileArray, ?int $userId = null): array {
    global $allowedMimeTypes, $allowedExtensions;

    if (!isset($fileArray['error']) || is_array($fileArray['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload parameters.'];
    }

    if ($fileArray['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $fileArray['error']];
    }

    if ($fileArray['size'] > MAX_FILE_SIZE_BYTES) {
        return ['success' => false, 'error' => 'File exceeds the 25MB maximum limit.'];
    }

    $originalName = basename($fileArray['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExtensions, true)) {
        return ['success' => false, 'error' => "File extension .{$ext} is not permitted."];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileArray['tmp_name']);

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $storedFilename = sprintf(
        '%s_%s_%s.%s',
        $assessmentId,
        $questionId,
        bin2hex(random_bytes(8)),
        $ext
    );
    $targetPath = UPLOAD_DIR . $storedFilename;

    if (!move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO `evidence_files` 
        (`assessment_id`, `question_id`, `explain_block_id`, `original_name`, `stored_filename`, `mime_type`, `file_size`, `uploaded_by_user_id`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $assessmentId,
        $questionId,
        $explainBlockId,
        $originalName,
        $storedFilename,
        $mimeType,
        $fileArray['size'],
        $userId
    ]);
    $fileId = (int)$pdo->lastInsertId();

    logAudit($assessmentId, 'FILE_UPLOADED', [
        'question_id' => $questionId,
        'original_name' => $originalName,
        'file_id' => $fileId
    ], $userId);

    return [
        'success' => true,
        'file_id' => $fileId,
        'original_name' => $originalName,
        'stored_filename' => $storedFilename,
        'file_size' => $fileArray['size'],
        'mime_type' => $mimeType
    ];
}

function getEvidenceFilesForQuestion(int $assessmentId, int $questionId): array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM evidence_files WHERE assessment_id = ? AND question_id = ? ORDER BY created_at DESC");
    $stmt->execute([$assessmentId, $questionId]);
    return $stmt->fetchAll();
}
