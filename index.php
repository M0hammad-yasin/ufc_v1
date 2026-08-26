<?php
/**
 * United Five Construction - Root Entry Point
 */
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /ufc_v1/admin/assessments.php');
} else {
    header('Location: /ufc_v1/auth/login.php');
}
exit;
