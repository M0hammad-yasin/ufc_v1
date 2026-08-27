<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: /ufc_v1/admin/assessments.php');
    exit;
}

$error = '';
$emailValue = $_POST['email'] ?? 'assessor@ufc.com';
$passwordValue = $_POST['password'] ?? 'assessor123';

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
    <!-- Tailwind CSS v4 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;900&family=Barlow:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Existing CSS for specific variables / classes -->
    <link rel="stylesheet" href="/ufc_v1/assets/css/style.css">
</head>
<body class="bg-[#060f1e] text-white antialiased min-h-screen font-sans">
    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Brand panel -->
        <div class="hidden lg:flex relative lg:w-[40%] flex-col justify-center items-center p-14 bg-[#0d1f3c] border-r border-[#1a3a5c] overflow-hidden" 
             style="background-image: repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.035) 0 1px, transparent 1px 48px), repeating-linear-gradient(90deg, rgba(255, 255, 255, 0.035) 0 1px, transparent 1px 48px);">
            <div class="max-w-[380px] w-full">
                <div class="text-[11px] font-bold tracking-[0.3em] text-[#8b0000] mb-4 uppercase">
                    UNITED FIVE CONSTRUCTION — CONFIDENTIAL
                </div>
                <h1 class="text-4xl lg:text-5xl font-bold font-serif-ufc text-white tracking-wide mb-1 select-none">
                    THE UFC
                </h1>
                <h2 class="text-2xl lg:text-3xl font-bold font-serif-ufc text-[#c9a84c] tracking-[0.15em] select-none">
                    MASTER FRAMEWORK
                </h2>
            </div>
        </div>

        <!-- Form panel -->
        <div class="flex-1 flex items-center justify-center p-6 sm:p-12 lg:p-24 bg-[#060f1e]">
            <div class="w-full max-w-[460px] bg-[#0d1f3c] border border-[#1a3a5c] rounded-xl p-8 sm:p-10 shadow-[0_20px_60px_rgba(0,0,0,0.5)]">
                <div class="text-[11px] font-bold tracking-[0.3em] text-[#8b0000] mb-2 uppercase">
                    ADMIN SIGN IN
                </div>
                <h2 class="text-2xl font-bold text-white tracking-normal mb-1.5 font-serif-ufc">
                    Welcome back
                </h2>
                <p class="text-[#9ca3af] leading-[1.7] text-sm mb-6 font-serif-ufc">
                    Sign in to review and finalize active assessments.
                </p>

                <?php if ($error): ?>
                    <div class="text-[13px] text-[#f87171] bg-[#f87171]/10 border border-[#f87171]/25 rounded-md py-3 px-4 mb-4 text-left leading-relaxed">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate class="space-y-4">
                    <div class="flex flex-col">
                        <label for="email" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 text-left uppercase font-bold">
                            EMAIL
                        </label>
                        <input
                            class="w-full px-4 py-3 rounded-[6px] border border-[#1a3a5c] bg-white/5 text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors font-sans placeholder-[#6b7280]"
                            type="email" id="email" name="email"
                            value="<?= htmlspecialchars($emailValue) ?>"
                            placeholder="Enter your email address"
                            autocomplete="username" required>
                    </div>
                    <div class="flex flex-col">
                        <label for="password" class="block text-[11px] tracking-[0.2em] text-[#6b7280] mb-2 text-left uppercase font-bold">
                            PASSWORD
                        </label>
                        <div class="relative">
                            <input
                                class="w-full px-4 py-3 rounded-[6px] border border-[#1a3a5c] bg-white/5 text-white text-[15px] outline-none focus:border-[#c9a84c] transition-colors font-sans placeholder-[#6b7280] pr-12"
                                type="password" id="password" name="password"
                                value="<?= htmlspecialchars($passwordValue) ?>"
                                placeholder="Enter your password"
                                required>
                            <button type="button" class="absolute right-1 top-1/2 -translate-y-1/2 bg-transparent border-none text-[#6b7280] hover:text-white cursor-pointer text-[14px] p-2 focus:outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a84c]" 
                                    data-toggle-target="password" aria-label="Show password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="block w-full p-4 rounded-[6px] border-none font-bold tracking-[0.25em] text-[14px] cursor-pointer font-serif-ufc uppercase bg-[#8b0000] text-white mt-6 transition-all hover:bg-[#a00000] active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-[#c9a84c]">
                        ACCESS FRAMEWORK
                    </button>
                </form>

                <div class="text-center text-[12px] text-[#6b7280] mt-6 tracking-[0.02em] font-sans">
                    Single-admin console — no public registration.
                </div>
            </div>
        </div>

    </div>

    <script>
        document.querySelectorAll('[data-toggle-target]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var input = document.getElementById(btn.dataset.toggleTarget);
                var icon = btn.querySelector('i');
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        });
    </script>
</body>
</html>
