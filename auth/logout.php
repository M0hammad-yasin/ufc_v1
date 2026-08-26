<?php
require_once __DIR__ . '/../includes/auth.php';
logoutUser();
header('Location: /ufc_v1/auth/login.php');
exit;
