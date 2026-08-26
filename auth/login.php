<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: /ufc_v1/admin/assessments.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (loginUser($email, $password)) {
        header('Location: /ufc_v1/admin/assessments.php');
        exit;
    } else {
        $error = 'Invalid email or password. Please verify your credentials.';
    }
}

$pageTitle = 'Sign In — UFC Client Pre-Assessment';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="/ufc_v1/assets/css/style.css">
</head>
<body class="min-h-screen bg-[#060f1e] flex flex-col justify-center py-12 sm:px-6 lg:px-8 text-slate-100">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="inline-flex w-16 h-16 rounded-xl bg-gradient-to-br from-[#c9a84c] to-[#997728] items-center justify-center font-serif font-bold text-2xl text-[#060f1e] shadow-xl mb-4">
            UFC
        </div>
        <h2 class="font-serif text-2xl font-bold tracking-tight text-white">
            UNITED FIVE CONSTRUCTION
        </h2>
        <p class="mt-2 text-xs tracking-widest text-[#c9a84c] uppercase font-semibold">
            Private Client Pre-Assessment · v5.0
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4">
        <div class="bg-[#0d1f3c] py-8 px-6 shadow-2xl rounded-xl border border-[#1e3e68] sm:px-10">
            <?php if ($error): ?>
                <div class="mb-5 p-3 rounded bg-red-900/50 border border-red-500 text-red-200 text-xs flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-5" action="" method="POST">
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                        Email Address
                    </label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? 'assessor@ufc.com') ?>" 
                               class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c] focus:ring-1 focus:ring-[#c9a84c]">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                        Password
                    </label>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" required value="assessor123"
                               class="w-full px-3 py-2 bg-[#060f1e] border border-[#1e3e68] rounded-md text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#c9a84c] focus:ring-1 focus:ring-[#c9a84c]">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-semibold text-[#060f1e] bg-[#c9a84c] hover:bg-[#d6b85e] focus:outline-none transition-colors">
                        Sign In to System
                    </button>
                </div>
            </form>

            <div class="mt-6 border-t border-[#1e3e68] pt-4 text-center">
                <div class="text-[11px] text-slate-400">
                    Default Credentials:
                    <span class="block text-slate-300 font-mono mt-1">assessor@ufc.com / assessor123</span>
                    <span class="block text-slate-300 font-mono">ceo@ufc.com / ceo123</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
