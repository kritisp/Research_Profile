<?php
/**
 * Global Academic Header Component
 * Design System: Oxford-Ivy Modernity x Swiss Academic Editorial
 * Focus: High Legibility, Typographic Hierarchy, Refined Borders, Subdued Academic Elevation
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
    <title><?= e($pageTitle) ?> | Departmental Scholar</title>

    <!-- Google Fonts: EB Garamond (Scholar Serif), Plus Jakarta Sans (UI Body), JetBrains Mono (Identifiers) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400..700;1,400..700&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN with Custom Extended Theme -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                        serif: ['"EB Garamond"', 'Georgia', 'serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        oxford: {
                            navy: '#0A192F',
                            dark: '#060F1E',
                            blue: '#162C4E',
                            slate: '#1E3A5F',
                            gold: '#926315',
                            goldLight: '#FDF9F0',
                        },
                        academic: {
                            gold: '#926315',
                            goldLight: '#FDF9F0',
                            goldBorder: '#E8D5B0',
                            amber: '#B45309',
                        },
                        scholar: {
                            bg: '#F8FAFC',
                            surface: '#FFFFFF',
                            text: '#0F172A',
                            muted: '#64748B',
                            subtle: '#94A3B8',
                            border: '#E2E8F0',
                            borderLight: '#F1F5F9',
                            green: '#0D7A53',
                            danger: '#BE123C',
                            link: '#1D4ED8',
                        }
                    },
                    borderRadius: {
                        tag: '4px',
                        control: '6px',
                        card: '8px',
                    },
                    boxShadow: {
                        xs: '0 1px 2px 0 rgba(10, 25, 47, 0.04)',
                        academic: '0 1px 3px 0 rgba(10, 25, 47, 0.06), 0 1px 2px -1px rgba(10, 25, 47, 0.04)',
                        academicHover: '0 4px 6px -1px rgba(10, 25, 47, 0.07), 0 2px 4px -2px rgba(10, 25, 47, 0.04)',
                        modal: '0 20px 25px -5px rgba(10, 25, 47, 0.12), 0 8px 10px -6px rgba(10, 25, 47, 0.04)',
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Design System CSS Foundation -->
    <link rel="stylesheet" href="<?= url('assets/css/scholar.css') ?>">
</head>
<body class="flex flex-col min-h-full font-sans text-scholar-text bg-scholar-bg antialiased selection:bg-slate-200 selection:text-oxford-navy">

    <!-- Accessibility Skip Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Institutional Masthead Strip -->
    <div class="bg-oxford-navy text-slate-300 text-xs py-2 px-4 border-b border-oxford-blue/50">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-2">
            <div class="flex items-center gap-3">
                <span class="font-bold tracking-wider text-amber-300 font-mono text-[11px] uppercase">Departmental Scholar</span>
                <span class="text-slate-600">|</span>
                <span class="text-slate-300 font-medium text-[11px] hidden sm:inline">Faculty Scholarly Directory &amp; Research Repository</span>
            </div>
            <div class="flex items-center gap-4 text-slate-400 text-[11px]">
                <span class="hidden sm:inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-graduation-cap text-amber-400 text-xs"></i>
                    <span>Peer-Reviewed Index</span>
                </span>
                <span class="hidden md:inline text-slate-600">|</span>
                <span class="inline-flex items-center gap-1.5 text-emerald-400 font-medium">
                    <i class="fa-solid fa-shield-check text-xs"></i>
                    <span>Institutional Registry</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Academic Navigation Bar -->
    <header class="bg-white border-b border-scholar-border sticky top-0 z-40 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                
                <!-- Brand / Seal -->
                <div class="flex items-center gap-6">
                    <a href="<?= url() ?>" class="flex items-center gap-3 group focus:outline-none" aria-label="Departmental Scholar Home">
                        <div class="w-10 h-10 rounded-[6px] bg-slate-50 border border-scholar-border flex items-center justify-center p-1.5 shadow-xs transition group-hover:border-oxford-slate group-hover:bg-slate-100">
                            <img src="<?= url('assets/img/scholar_hat.svg') ?>" alt="Departmental Scholar Logo" class="w-full h-full object-contain">
                        </div>
                        <div class="flex flex-col">
                            <span class="font-serif font-bold text-lg text-oxford-navy tracking-tight leading-tight group-hover:text-oxford-blue transition">Departmental Scholar</span>
                            <span class="text-[10px] font-semibold text-scholar-muted tracking-wider uppercase font-mono">Faculty Research Repository</span>
                        </div>
                    </a>

                    <!-- Desktop Navigation Links -->
                    <nav class="hidden md:flex items-center space-x-1 pl-4 border-l border-scholar-border" aria-label="Primary Navigation">
                        <a href="<?= url() ?>" class="px-3 py-2 rounded-[6px] text-sm font-medium transition <?= $activeNav === 'home' ? 'text-oxford-navy bg-slate-100 font-semibold border-b-2 border-oxford-navy' : 'text-slate-600 hover:text-oxford-navy hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-house-chimney text-xs mr-1.5 opacity-60"></i>Home
                        </a>
                        <a href="<?= url('directory.php') ?>" class="px-3 py-2 rounded-[6px] text-sm font-medium transition <?= $activeNav === 'directory' ? 'text-oxford-navy bg-slate-100 font-semibold border-b-2 border-oxford-navy' : 'text-slate-600 hover:text-oxford-navy hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-users text-xs mr-1.5 opacity-60"></i>Faculty Directory
                        </a>
                        <a href="<?= url('departments.php') ?>" class="px-3 py-2 rounded-[6px] text-sm font-medium transition <?= $activeNav === 'departments' ? 'text-oxford-navy bg-slate-100 font-semibold border-b-2 border-oxford-navy' : 'text-slate-600 hover:text-oxford-navy hover:bg-slate-50' ?>">
                            <i class="fa-solid fa-building-columns text-xs mr-1.5 opacity-60"></i>Departments
                        </a>
                    </nav>
                </div>

                <!-- Right Side: Auth / Dashboard Controls -->
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
                            <a href="<?= url($dashboardUrl) ?>" class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                                <i class="fa-solid fa-gauge text-xs"></i>
                                <span>Dashboard</span>
                            </a>

                            <div class="hidden lg:flex flex-col text-right">
                                <span class="text-xs font-semibold text-oxford-navy leading-tight"><?= e($u['full_name']) ?></span>
                                <span class="text-[10px] text-slate-500 uppercase tracking-wider font-mono">
                                    <?= e(str_replace('_', ' ', $u['role'])) ?>
                                </span>
                            </div>

                            <a href="<?= url('logout.php') ?>" title="Sign Out" class="p-2 text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-[6px] transition border border-transparent hover:border-rose-200" aria-label="Sign Out">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="hidden sm:flex items-center gap-2">
                            <a href="<?= url('login.php') ?>" class="btn-academic-secondary text-xs !py-1.5 !px-3 shadow-xs">
                                <i class="fa-solid fa-arrow-right-to-bracket text-xs opacity-70"></i>
                                <span>Sign In</span>
                            </a>
                            <a href="<?= url('register.php') ?>" class="btn-academic-primary text-xs !py-1.5 !px-3 shadow-xs">
                                <i class="fa-solid fa-user-plus text-xs"></i>
                                <span>Register</span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Mobile Menu Hamburger Button -->
                    <button type="button" id="mobileNavToggle" aria-expanded="false" aria-controls="mobileNavMenu" class="md:hidden p-2 rounded-[6px] text-slate-600 hover:text-oxford-navy hover:bg-slate-100 border border-slate-200 transition focus:outline-none min-h-[40px] min-w-[40px] flex items-center justify-center" aria-label="Toggle navigation menu">
                        <i class="fa-solid fa-bars text-base"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobileNavMenu" class="hidden md:hidden border-t border-scholar-border bg-white px-4 pt-3 pb-4 space-y-2 shadow-md">
            <a href="<?= url() ?>" class="block px-3 py-2.5 rounded-[6px] text-sm font-medium transition <?= $activeNav === 'home' ? 'text-oxford-navy bg-slate-100 font-semibold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <i class="fa-solid fa-house-chimney text-xs mr-2 opacity-60"></i>Home
            </a>
            <a href="<?= url('directory.php') ?>" class="block px-3 py-2.5 rounded-[6px] text-sm font-medium transition <?= $activeNav === 'directory' ? 'text-oxford-navy bg-slate-100 font-semibold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <i class="fa-solid fa-users text-xs mr-2 opacity-60"></i>Faculty Directory
            </a>
            <a href="<?= url('departments.php') ?>" class="block px-3 py-2.5 rounded-[6px] text-sm font-medium transition <?= $activeNav === 'departments' ? 'text-oxford-navy bg-slate-100 font-semibold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <i class="fa-solid fa-building-columns text-xs mr-2 opacity-60"></i>Academic Departments
            </a>
            <?php if (!is_logged_in()): ?>
                <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                    <a href="<?= url('login.php') ?>" class="btn-academic-secondary w-full justify-center text-center">
                        <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                        <span>Sign In</span>
                    </a>
                    <a href="<?= url('register.php') ?>" class="btn-academic-primary w-full justify-center text-center">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span>Register Profile</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Global Toast & Flash Messages -->
    <?php if (!empty($flashes)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 space-y-2.5" role="status" aria-live="polite">
            <?php foreach ($flashes as $flash): ?>
                <?php 
                    $colors = match($flash['type']) {
                        'success' => 'bg-emerald-50 text-emerald-900 border-emerald-200',
                        'danger'  => 'bg-rose-50 text-rose-900 border-rose-200',
                        'warning' => 'bg-amber-50 text-amber-900 border-amber-200',
                        default   => 'bg-slate-50 text-oxford-navy border-slate-300',
                    };
                    $icon = match($flash['type']) {
                        'success' => 'fa-circle-check text-emerald-600',
                        'danger'  => 'fa-circle-xmark text-rose-600',
                        'warning' => 'fa-triangle-exclamation text-amber-600',
                        default   => 'fa-circle-info text-oxford-slate',
                    };
                ?>
                <div class="scholar-toast flex items-center justify-between p-3.5 rounded-[6px] border text-sm <?= $colors ?> shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid <?= $icon ?> text-base flex-shrink-0"></i>
                        <span class="font-medium"><?= e($flash['message']) ?></span>
                    </div>
                    <button type="button" data-dismiss="toast" class="text-slate-400 hover:text-slate-700 transition p-1 ml-2 focus:outline-none" aria-label="Dismiss message">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main id="main-content" class="flex-grow">
