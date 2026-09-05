<?php
/**
 * United Five Construction - Root Entry Point
 */
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/assessments.php');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
