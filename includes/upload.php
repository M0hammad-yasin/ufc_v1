<?php
/**
 * United Five Construction - Secure Evidence Upload Handler
 * 
 * Folder Structure:
 * uploads/
 * └── {Project_Name}/
 *     └── Phase_{Phase_Number}/
 *         └── {Question_Number}.{ext}
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/questions.php';

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
    'image/x-dwg',
    'application/octet-stream'
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

    // 1. Fetch Assessment & Question details for structured folder naming
    $assessment = getAssessmentDetails($assessmentId);
    $question   = getQuestionById($questionId);

    if (!$assessment || !$question) {
        return ['success' => false, 'error' => 'Assessment or Question context not found.'];
    }

    // 2. Project Folder Name: Use project_name (or client_name / assessment_number fallback)
    $rawProjectName = trim((string)($assessment['project_name'] ?? ''));
    if ($rawProjectName === '') {
        $rawProjectName = trim((string)($assessment['client_name'] ?? '')) ?: ('Assessment_' . $assessmentId);
    }
    // Sanitize project folder name for safe filesystem usage across Windows & Linux
    $safeProjectFolder = preg_replace('/[\/\\\\:*?"<>|]/', '_', $rawProjectName);
    $safeProjectFolder = trim($safeProjectFolder, '. ');
    if ($safeProjectFolder === '') {
        $safeProjectFolder = 'Project_' . $assessmentId;
    }

    $baseUploadDir = rtrim(UPLOAD_DIR, '/\\');
    if (!is_dir($baseUploadDir)) {
        mkdir($baseUploadDir, 0755, true);
    }

    $projectDirPath = $baseUploadDir . DIRECTORY_SEPARATOR . $safeProjectFolder;
    if (!is_dir($projectDirPath)) {
        mkdir($projectDirPath, 0755, true);
    }

    // 3. Phase Folder: e.g. Phase_1, Phase_2, Phase_3, Phase_4
    $phaseNumber = (int)($question['phase_number'] ?? 1);
    $phaseFolderName = "Phase_" . $phaseNumber;
    $phaseDirPath = $projectDirPath . DIRECTORY_SEPARATOR . $phaseFolderName;

    // Check if phase folder is already created, create if not
    if (!is_dir($phaseDirPath)) {
        mkdir($phaseDirPath, 0755, true);
    }

    // 4. Question File Name: e.g. 1.1.pdf, 1.8.dwg, 2.3.png
    $qNum = trim((string)($question['question_number'] ?? (string)$questionId));
    $safeQNum = preg_replace('/[\/\\\\:*?"<>|]/', '_', $qNum);
    $fileName = "{$safeQNum}.{$ext}";

    $targetPath = $phaseDirPath . DIRECTORY_SEPARATOR . $fileName;

    // Move uploaded file to structured path
    if (!move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file to destination folder.'];
    }

    // Relative path stored in database (using standard web-friendly forward slashes)
    $storedFilename = "{$safeProjectFolder}/{$phaseFolderName}/{$fileName}";

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
        'question_number' => $qNum,
        'original_name'   => $originalName,
        'stored_filename' => $storedFilename,
        'file_id'         => $fileId
    ], $userId);

    return [
        'success'         => true,
        'file_id'         => $fileId,
        'original_name'   => $originalName,
        'stored_filename' => $storedFilename,
        'file_size'       => $fileArray['size'],
        'mime_type'       => $mimeType
    ];
}

function getEvidenceFilesForQuestion(int $assessmentId, int $questionId): array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM evidence_files WHERE assessment_id = ? AND question_id = ? ORDER BY created_at DESC");
    $stmt->execute([$assessmentId, $questionId]);
    return $stmt->fetchAll();
}

