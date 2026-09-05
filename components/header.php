<?php

/**
 * United Five Construction - Reusable Header Component with left sidebar
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUser = $_SESSION['user'] ?? null;
$pageTitle = $pageTitle ?? 'United Five Construction - Client Pre-Assessment';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;900&family=Barlow:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <?php if ($currentUser): ?>
        <!-- ══ HAMBURGER BUTTON (md:hidden — visible only on mobile/tablet) ═════════ -->
        <button
            id="hamburger-btn"
            aria-label="Open navigation menu"
            aria-expanded="false"
            aria-controls="mobile-sidebar"
            class="md:hidden fixed top-4 left-4 z-[1100] flex flex-col items-center justify-center gap-[5px] w-11 h-11 p-0 cursor-pointer rounded-xl border border-white/10 bg-[#0d1f3c]/90 backdrop-blur-md shadow-[0_4px_16px_rgba(0,0,0,0.4)] hover:bg-[#1a3a5c]/95 hover:border-[#c9a84c]/40 transition-colors duration-200">
            <span class="ham-bar block h-[2px] w-[22px] rounded-sm bg-white origin-center transition-all duration-300"></span>
            <span class="ham-bar block h-[2px] w-[22px] rounded-sm bg-white origin-center transition-all duration-300"></span>
            <span class="ham-bar block h-[2px] w-[22px] rounded-sm bg-white origin-center transition-all duration-300"></span>
        </button>

        <!-- ══ OVERLAY ═══════════════════════════════════════════════════════════════ -->
        <div id="sidebar-overlay" class="md:hidden fixed inset-0 z-[1050] bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300" aria-hidden="true"></div>

        <!-- ══ DESKTOP SIDEBAR ═══════════════════════════════════════════════════════ -->
        <aside class="hidden md:flex fixed top-0 left-0 h-screen w-[250px] bg-[#0d1f3c] border-r border-[#1a3a5c] p-6 flex-col z-50">
            <!-- Logo -->
            <div class="mb-8 flex items-center space-x-2">
                <div class="w-8 h-8 rounded bg-gradient-to-br from-[#c9a84c] to-[#997728] flex items-center justify-center font-serif font-bold text-sm text-[#060f1e] shadow-md">
                    UFC
                </div>
                <h2 class="text-[#c9a84c] text-xs font-bold tracking-widest m-0 font-sans">UFC FRAMEWORK</h2>
            </div>

            <!-- Nav links -->
            <nav class="flex flex-col space-y-1">
                <?php
                $isNewActive = ($current_page === 'start.php' || $current_page === 'question.php');
                $isListActive = ($current_page === 'assessments.php' || $current_page === 'assessment.php' || $current_page === 'phase-result.php');
                ?>
                <a href="/ufc_v1/admin/metrics.php"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors font-sans <?= $current_page === 'metrics.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-chart-pie w-6 mr-3 text-[#c9a84c]"></i> Dashboard
                </a>
                <a href="/ufc_v1/assessment/start.php"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors font-sans <?= $isNewActive ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-chart-line w-6 mr-3 text-[#c9a84c]"></i> New Assessment
                </a>
                <a href="/ufc_v1/admin/assessments.php"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors font-sans <?= $isListActive ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-list w-6 mr-3 text-[#c9a84c]"></i> Assessment List
                </a>
                <a href="/ufc_v1/admin/tasks.php"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors font-sans <?= $current_page === 'tasks.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-list-check w-6 mr-3 text-[#c9a84c]"></i> Follow-Up Tasks
                </a>
                <!-- <a href="/ufc_v1/admin/decline-log.php"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors font-sans <?= $current_page === 'decline-log.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-ban w-6 mr-3 text-[#c9a84c]"></i> Decline Log
                </a> -->
                <a href="/ufc_v1/admin/email-logs.php"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-md transition-colors font-sans <?= $current_page === 'email-logs.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-envelope-open-text w-6 mr-3 text-[#c9a84c]"></i> Email Logs
                </a>
            </nav>

            <!-- User info / sign-out -->
            <div class="mt-auto pt-4 border-t border-[#1a3a5c]">
                <p class="text-[11px] text-slate-500 tracking-widest mb-0.5 uppercase font-bold">SIGNED IN AS</p>
                <p class="text-sm font-bold text-white mb-0.5 break-all">
                    <?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?>
                </p>
                <p class="text-[10px] uppercase font-bold text-[#c9a84c] tracking-wider mb-4">
                    <?= strtoupper($currentUser['role'] ?? 'ASSESSOR') ?>
                </p>
                <a href="/ufc_v1/auth/logout.php"
                    class="flex items-center px-4 py-2.5 text-xs font-semibold text-[#f87171] hover:text-white hover:bg-red-900/20 border border-[#f87171]/20 hover:border-[#f87171] rounded-md transition-colors">
                    <i class="fa-solid fa-right-from-bracket mr-2"></i> Sign Out
                </a>
            </div>
        </aside>

        <!-- ══ MOBILE / TABLET DRAWER ════════════════════════════════════════════════ -->
        <aside id="mobile-sidebar"
            class="md:hidden fixed top-0 left-0 h-dvh w-[min(280px,85vw)] z-[1080] bg-[#0d1f3c] border-r border-[#1a3a5c] flex flex-col overflow-y-auto -translate-x-full transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] shadow-[4px_0_32px_rgba(0,0,0,0.55)]"
            role="dialog" aria-modal="true" aria-label="Navigation menu">

            <!-- Drawer header -->
            <div class="flex items-center space-x-2 px-5 pt-5 pb-4 border-b border-[#1a3a5c] shrink-0">
                <div class="w-8 h-8 rounded bg-gradient-to-br from-[#c9a84c] to-[#997728] flex items-center justify-center font-serif font-bold text-sm text-[#060f1e] shadow-md">
                    UFC
                </div>
                <h2 class="text-[#c9a84c] text-xs font-bold tracking-widest m-0 font-sans">UFC FRAMEWORK</h2>
            </div>

            <!-- Nav links -->
            <nav class="flex flex-col p-3 space-y-1 flex-1">
                <a href="/ufc_v1/admin/metrics.php"
                    class="flex items-center px-4 py-3.5 text-sm font-medium rounded-lg transition-colors font-sans <?= $current_page === 'metrics.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-chart-pie w-6 mr-3 text-[#c9a84c]"></i> Dashboard
                </a>
                <a href="/ufc_v1/assessment/start.php"
                    class="flex items-center px-4 py-3.5 text-sm font-medium rounded-lg transition-colors font-sans <?= $isNewActive ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-chart-line w-6 mr-3 text-[#c9a84c]"></i> New Assessment
                </a>
                <a href="/ufc_v1/admin/assessments.php"
                    class="flex items-center px-4 py-3.5 text-sm font-medium rounded-lg transition-colors font-sans <?= $isListActive ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-list w-6 mr-3 text-[#c9a84c]"></i> Assessment List
                </a>
                <a href="/ufc_v1/admin/tasks.php"
                    class="flex items-center px-4 py-3.5 text-sm font-medium rounded-lg transition-colors font-sans <?= $current_page === 'tasks.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-list-check w-6 mr-3 text-[#c9a84c]"></i> Follow-Up Tasks
                </a>
                <!-- <a href="/ufc_v1/admin/decline-log.php"
                    class="flex items-center px-4 py-3.5 text-sm font-medium rounded-lg transition-colors font-sans <?= $current_page === 'decline-log.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-ban w-6 mr-3 text-[#c9a84c]"></i> Decline Log
                </a> -->
                <a href="/ufc_v1/admin/email-logs.php"
                    class="flex items-center px-4 py-3.5 text-sm font-medium rounded-lg transition-colors font-sans <?= $current_page === 'email-logs.php' ? 'text-white bg-[#1a3a5c]' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-envelope-open-text w-6 mr-3 text-[#c9a84c]"></i> Email Logs
                </a>
            </nav>

            <!-- User / sign-out footer -->
            <div class="mt-auto px-5 pt-4 pb-5 border-t border-[#1a3a5c] shrink-0">
                <p class="text-[10px] text-slate-500 tracking-widest mb-1 uppercase font-bold">SIGNED IN AS</p>
                <p class="text-sm font-bold text-white mb-0.5 break-all">
                    <?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?>
                </p>
                <p class="text-[10px] uppercase font-bold text-[#c9a84c] tracking-wider mb-4">
                    <?= strtoupper($currentUser['role'] ?? 'ASSESSOR') ?>
                </p>
                <a href="/ufc_v1/auth/logout.php"
                    class="flex items-center justify-center px-4 py-2.5 text-xs font-semibold text-[#f87171] hover:text-white hover:bg-red-900/20 border border-[#f87171]/20 hover:border-[#f87171] rounded-md transition-colors">
                    <i class="fa-solid fa-right-from-bracket mr-2"></i> Sign Out
                </a>
            </div>
        </aside>
    <?php endif; ?>

    <!-- Offset layout wrapper to push page content right on desktop -->
    <div class="flex-1 flex flex-col <?= $currentUser ? 'md:pl-[250px]' : '' ?>">

        <?php if (!$currentUser): ?>
            <!-- Fallback header if user is not logged in -->
            <header class="bg-[#0d1f3c] border-b border-[#1e3e68] py-4 px-6 flex items-center justify-between no-print">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded bg-gradient-to-br from-[#c9a84c] to-[#997728] flex items-center justify-center font-serif font-bold text-sm text-[#060f1e] shadow-md">
                        UFC
                    </div>
                    <span class="font-serif font-bold text-base tracking-wider text-slate-100">UNITED FIVE CONSTRUCTION</span>
                </div>
                <a href="/ufc_v1/auth/login.php" class="px-4 py-1.5 text-xs font-semibold text-[#060f1e] bg-[#c9a84c] hover:bg-[#d6b85e] rounded shadow transition-all">
                    Sign In
                </a>
            </header>
        <?php endif; ?>

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
            <?php endforeach;
            } ?>