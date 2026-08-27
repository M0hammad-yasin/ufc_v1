<?php
/**
 * api/check_project_name.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Lightweight AJAX endpoint — returns whether a project name is available.
 *
 * Request:  POST  JSON body  { "name": "My Project", "exclude_id": 42 }
 * Response: JSON             { "available": true }   or  { "available": false }
 *
 * Can be called from any form on any page that needs project-name uniqueness.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/project_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only allow logged-in users
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
$name = trim($body['name'] ?? '');

if ($name === '') {
    echo json_encode(['available' => false, 'error' => 'name_required']);
    exit;
}

$excludeId = isset($body['exclude_id']) && is_numeric($body['exclude_id'])
    ? (int)$body['exclude_id']
    : null;

$pdo   = getDbConnection();
$taken = checkProjectExists($pdo, $name, $excludeId);

echo json_encode(['available' => !$taken]);
