<?php
/**
 * United Five Construction - Reusable Header Component
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUser = $_SESSION['user'] ?? null;
$pageTitle = $pageTitle ?? 'United Five Construction - Client Pre-Assessment';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="/ufc_v1/assets/css/style.css">
    <style>
      :root {
        --navy: #0d1f3c;
        --navy-mid: #1a3a5c;
        --navy-light: #234d7a;
        --red: #8b0000;
        --gold: #c9a84c;
        --bg: #060f1e;
        --green: #4ade80;
        --amber: #c9a84c;
        --rederr: #f87171;
      }
    </style>
</head>
<body class="flex flex-col min-h-screen bg-[#060f1e] text-slate-100 antialiased selection:bg-[#c9a84c] selection:text-[#060f1e]">

<!-- UFC Top Navigation Bar -->
<header class="bg-[#0d1f3c] border-b border-[#1e3e68] sticky top-0 z-40 shadow-lg no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Brand Logo & Title -->
            <div class="flex items-center space-x-3">
                <a href="/ufc_v1/admin/assessments.php" class="flex items-center space-x-3 group">
                    <div class="w-10 h-10 rounded bg-gradient-to-br from-[#c9a84c] to-[#997728] flex items-center justify-center font-serif font-bold text-lg text-[#060f1e] shadow-md group-hover:scale-105 transition-transform">
                        UFC
                    </div>
                    <div>
                        <div class="font-serif font-bold tracking-wider text-base text-slate-100 group-hover:text-[#c9a84c] transition-colors">
                            UNITED FIVE CONSTRUCTION
                        </div>
                        <div class="text-[10px] tracking-widest text-[#c9a84c] uppercase font-semibold">
                            Client Pre-Assessment System · v5.0
                        </div>
                    </div>
                </a>
            </div>

            <!-- Navigation Links -->
            <?php if ($currentUser): ?>
            <nav class="hidden md:flex items-center space-x-1">
                <a href="/ufc_v1/admin/assessments.php" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-[#1a3a5c] transition-colors">
                    Assessments
                </a>
                <a href="/ufc_v1/assessment/start.php" class="px-3 py-2 rounded-md text-sm font-medium text-[#c9a84c] hover:bg-[#1a3a5c] transition-colors">
                    + New Lead
                </a>
                <a href="/ufc_v1/admin/tasks.php" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-[#1a3a5c] transition-colors">
                    Follow-Up Tasks
                </a>
                <a href="/ufc_v1/admin/decline-log.php" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-[#1a3a5c] transition-colors">
                    Decline Log
                </a>
                <a href="/ufc_v1/admin/metrics.php" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-[#1a3a5c] transition-colors">
                    Conversion Metrics
                </a>
            </nav>

            <!-- User Menu -->
            <div class="flex items-center space-x-4">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-semibold text-slate-200"><?= htmlspecialchars($currentUser['name']) ?></div>
                    <div class="text-[10px] uppercase font-bold text-[#c9a84c] tracking-wider"><?= strtoupper($currentUser['role']) ?></div>
                </div>
                <a href="/ufc_v1/auth/logout.php" class="px-3 py-1.5 text-xs font-medium text-slate-300 hover:text-red-400 bg-[#1a3a5c] hover:bg-[#234d7a] rounded border border-[#1e3e68] transition-colors">
                    Sign Out
                </a>
            </div>
            <?php else: ?>
            <div>
                <a href="/ufc_v1/auth/login.php" class="px-4 py-1.5 text-xs font-semibold text-[#060f1e] bg-[#c9a84c] hover:bg-[#d6b85e] rounded shadow transition-all">
                    Sign In
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Container -->
<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
<?php
// Display Flash Messages
if (function_exists('getFlashMessages')) {
    $flashMessages = getFlashMessages();
    foreach ($flashMessages as $msg):
        $alertBg = 'bg-blue-900/40 border-blue-600 text-blue-200';
        if ($msg['type'] === 'success') $alertBg = 'bg-emerald-900/40 border-emerald-500 text-emerald-200';
        if ($msg['type'] === 'danger') $alertBg = 'bg-red-900/40 border-red-500 text-red-200';
        if ($msg['type'] === 'warning') $alertBg = 'bg-amber-900/40 border-amber-500 text-amber-200';
?>
    <div class="mb-6 p-4 rounded-lg border <?= $alertBg ?> flex items-center justify-between shadow-md">
        <span><?= htmlspecialchars($msg['message']) ?></span>
    </div>
<?php endforeach; } ?>
