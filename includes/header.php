<?php
/**
 * Global Academic Header Component
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Faculty Research Profile Portal';
$activeNav = $activeNav ?? '';
$flashes   = get_flashes();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | ITER Bhubaneswar</title>

    <!-- Google Fonts: Inter (UI) & Merriweather (Academic serif) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Merriweather', 'serif'],
                    },
                    colors: {
                        iter: {
                            50: '#f0f5fa',
                            100: '#dce8f3',
                            200: '#bcd4e7',
                            300: '#90b7d7',
                            400: '#5e94c3',
                            500: '#3c78ad',
                            600: '#2b5f90',
                            700: '#1d4872',
                            800: '#16395b',
                            900: '#0e263f',
                            950: '#081728',
                        },
                        scholar: {
                            blue: '#1a0dab',
                            green: '#006621',
                        }
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .font-serif-title { font-family: 'Merriweather', serif; }
        .scholar-link { color: #1a0dab; text-decoration: none; }
        .scholar-link:hover { text-decoration: underline; }
    </style>
</head>
<body class="flex flex-col min-h-full font-sans text-slate-800 antialiased selection:bg-iter-100 selection:text-iter-900">

    <!-- Institutional Top Bar -->
    <div class="bg-iter-950 text-slate-300 text-xs py-1.5 px-4 border-b border-iter-900">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-2">
            <div class="flex items-center gap-3">
                <span class="font-medium tracking-wide text-amber-300">DEPARTMENTAL RESEARCH PROFILE</span>
                <span class="text-slate-500">|</span>
                <span>Faculty Scholarly Directory & Academic Repository</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-slate-400">Institutional Faculty Showcase</span>
            </div>
        </div>
    </div>

    <!-- Main Academic Navigation -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Branding -->
                <div class="flex items-center">
                    <a href="<?= url() ?>" class="flex items-center gap-3 group">
                        <div class="w-10 h-10 rounded-full bg-white p-0.5 shadow-sm ring-1 ring-slate-200 overflow-hidden flex items-center justify-center">
                            <img src="<?= url('assets/img/scholar_logo.png') ?>" alt="Scholar Logo" class="w-full h-full object-contain">
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-lg text-slate-900 tracking-tight leading-tight group-hover:text-iter-700 transition">ResearchProfile</span>
                            <span class="text-[11px] font-medium text-slate-500 tracking-wider uppercase">Departmental Faculty Directory</span>
                        </div>
                    </a>

                    <!-- Nav Links -->
                    <div class="hidden md:flex md:ml-10 md:space-x-1">
                        <a href="<?= url() ?>" class="px-3 py-2 rounded-md text-sm font-medium transition <?= $activeNav === 'home' ? 'text-iter-700 bg-iter-50 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-house-chimney text-xs mr-1.5 opacity-70"></i> Home
                        </a>
                        <a href="<?= url('directory.php') ?>" class="px-3 py-2 rounded-md text-sm font-medium transition <?= $activeNav === 'directory' ? 'text-iter-700 bg-iter-50 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-users-viewfinder text-xs mr-1.5 opacity-70"></i> Faculty Directory
                        </a>
                        <a href="<?= url('departments.php') ?>" class="px-3 py-2 rounded-md text-sm font-medium transition <?= $activeNav === 'departments' ? 'text-iter-700 bg-iter-50 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-building-columns text-xs mr-1.5 opacity-70"></i> Departments
                        </a>
                    </div>
                </div>

                <!-- Right Side: Auth / Profile Controls -->
                <div class="flex items-center gap-3">
                    <?php if (is_logged_in()): ?>
                        <?php 
                            $u = current_user();
                            $dashboardUrl = 'dashboard/index.php';
                            if ($u['role'] === 'super_admin') {
                                $dashboardUrl = 'admin/index.php';
                            } elseif ($u['role'] === 'admin') {
                                $dashboardUrl = 'assistant/index.php';
                            }
                        ?>
                        <div class="flex items-center gap-3">
                            <a href="<?= url($dashboardUrl) ?>" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-medium bg-iter-800 text-white hover:bg-iter-900 transition shadow-sm">
                                <i class="fa-solid fa-gauge-high text-xs"></i>
                                <span>Dashboard</span>
                            </a>

                            <div class="hidden sm:flex flex-col text-right">
                                <span class="text-xs font-semibold text-slate-800 leading-tight"><?= e($u['full_name']) ?></span>
                                <span class="text-[10px] text-slate-500 uppercase tracking-wider font-mono">
                                    <?= e(str_replace('_', ' ', $u['role'])) ?>
                                </span>
                            </div>

                            <a href="<?= url('logout.php') ?>" title="Sign Out" class="p-2 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="<?= url('login.php') ?>" class="text-sm font-medium text-slate-700 hover:text-iter-800 px-3 py-2 transition">
                            Sign In
                        </a>
                        <a href="<?= url('register.php') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-sm font-medium bg-iter-800 text-white hover:bg-iter-900 transition shadow-sm">
                            <i class="fa-solid fa-user-plus text-xs"></i>
                            <span>Register</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages Container -->
    <?php if (!empty($flashes)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 space-y-2">
            <?php foreach ($flashes as $flash): ?>
                <?php 
                    $colors = match($flash['type']) {
                        'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        'danger'  => 'bg-rose-50 text-rose-800 border-rose-200',
                        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                        default   => 'bg-blue-50 text-blue-800 border-blue-200',
                    };
                    $icon = match($flash['type']) {
                        'success' => 'fa-circle-check text-emerald-600',
                        'danger'  => 'fa-circle-xmark text-rose-600',
                        'warning' => 'fa-triangle-exclamation text-amber-600',
                        default   => 'fa-circle-info text-blue-600',
                    };
                ?>
                <div class="flex items-center justify-between p-3.5 rounded-lg border text-sm <?= $colors ?>">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid <?= $icon ?>"></i>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-700 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Slot -->
    <main class="flex-grow">
