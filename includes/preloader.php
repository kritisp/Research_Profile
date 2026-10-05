<?php
/**
 * SOA University & ITER Institutional Splash Preloader
 * Displayed gracefully on landing page load with smooth fade-out.
 */
?>
<!-- SOA University Institutional Preloader -->
<div id="soa-preloader" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-slate-950 text-white transition-opacity duration-700 ease-out select-none">
    
    <!-- Background Ambient Glow -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-iter-700/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 flex flex-col items-center text-center px-4 max-w-md">

        <!-- SOA Emblem / Logo Container -->
        <div class="relative mb-6">
            <!-- Pulsing Ambient Ring -->
            <div class="absolute -inset-2 rounded-full bg-gradient-to-tr from-amber-500/30 to-iter-500/20 blur-md animate-pulse"></div>

            <!-- Outer Gold Rotary Ring -->
            <div class="relative w-28 h-28 sm:w-32 sm:h-32 rounded-full p-1 bg-gradient-to-tr from-amber-400 via-amber-200 to-amber-500 shadow-2xl flex items-center justify-center">
                <!-- Inner Navy Disc -->
                <div class="w-full h-full rounded-full bg-iter-950 border-2 border-amber-300/40 flex flex-col items-center justify-center p-3 relative overflow-hidden">
                    
                    <?php if (file_exists(__DIR__ . '/../assets/img/soa_logo.png')): ?>
                        <img src="<?= url('assets/img/soa_logo.png') ?>" alt="SOA Logo" class="w-full h-full object-contain">
                    <?php else: ?>
                        <!-- High-Craft Institutional Crest (SVG) -->
                        <svg class="w-16 h-16 sm:w-20 sm:h-20 text-amber-400 drop-shadow" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Outer decorative stars -->
                            <circle cx="50" cy="50" r="46" stroke="#fbbf24" stroke-width="1.5" stroke-dasharray="2 3" opacity="0.7"/>
                            <circle cx="50" cy="50" r="41" stroke="#d97706" stroke-width="1" opacity="0.5"/>
                            
                            <!-- Sun / Radiance of Knowledge -->
                            <path d="M50 14 L50 20 M50 80 L50 86 M14 50 L20 50 M80 50 L86 50 M25 25 L30 30 M70 70 L75 75 M25 75 L30 70 M70 30 L75 25" stroke="#fef3c7" stroke-width="1.5" stroke-linecap="round" opacity="0.6"/>

                            <!-- Academic Graduation Cap & Torch -->
                            <!-- Torch handle -->
                            <path d="M48 38 L52 38 L51 68 L49 68 Z" fill="#d97706"/>
                            <!-- Flame -->
                            <path d="M50 22 C47 28, 44 32, 50 37 C56 32, 53 28, 50 22 Z" fill="url(#flame-grad)"/>

                            <!-- Open Book of Wisdom -->
                            <path d="M22 62 C32 58, 44 59, 50 63 C56 59, 68 58, 78 62 L78 74 C68 70, 56 71, 50 75 C44 71, 32 70, 22 74 Z" fill="#1e293b" stroke="#fbbf24" stroke-width="1.5" stroke-linejoin="round"/>
                            <line x1="50" y1="63" x2="50" y2="75" stroke="#fbbf24" stroke-width="1.5"/>

                            <!-- Laurel branches -->
                            <path d="M20 50 C20 40, 26 32, 34 28" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" fill="none" opacity="0.8"/>
                            <path d="M80 50 C80 40, 74 32, 66 28" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" fill="none" opacity="0.8"/>

                            <defs>
                                <linearGradient id="flame-grad" x1="50" y1="22" x2="50" y2="37" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#fef08a"/>
                                    <stop offset="0.5" stop-color="#f59e0b"/>
                                    <stop offset="1" stop-color="#dc2626"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Typography -->
        <div class="space-y-1">
            <span class="inline-block px-2.5 py-0.5 rounded-full bg-amber-400/10 border border-amber-400/30 text-[10px] font-mono tracking-widest text-amber-300 uppercase font-semibold">
                NAAC A++ Accredited • NIRF Top Ranked
            </span>
            <h2 class="text-lg sm:text-xl font-bold font-serif-title tracking-wide text-white pt-1">
                SIKSHA 'O' ANUSANDHAN
            </h2>
            <p class="text-xs text-amber-200/80 font-mono tracking-wider uppercase">
                Deemed to be University • Bhubaneswar
            </p>
            <p class="text-xs font-semibold text-slate-300 pt-0.5">
                Institute of Technical Education and Research (ITER)
            </p>
        </div>

        <!-- Refined Progress Line & Indicator -->
        <div class="mt-7 w-48 flex flex-col items-center gap-2">
            <div class="w-full h-1 bg-slate-800 rounded-full overflow-hidden p-0.5 border border-slate-700/60">
                <div id="preloader-bar" class="h-full bg-gradient-to-r from-iter-500 via-amber-400 to-amber-200 rounded-full transition-all duration-500 ease-out" style="width: 15%;"></div>
            </div>
            <span id="preloader-status" class="text-[11px] font-mono text-slate-400 animate-pulse tracking-wide">
                Loading research repository...
            </span>
        </div>

    </div>
</div>

<script>
(function() {
    const preloader = document.getElementById('soa-preloader');
    const bar = document.getElementById('preloader-bar');
    const statusText = document.getElementById('preloader-status');

    if (!preloader) return;

    // Progressive simulated loading feel
    let progress = 20;
    const interval = setInterval(() => {
        if (progress < 85) {
            progress += Math.floor(Math.random() * 20) + 10;
            if (progress > 85) progress = 85;
            if (bar) bar.style.width = progress + '%';
        }
    }, 120);

    const finishLoading = () => {
        clearInterval(interval);
        if (bar) bar.style.width = '100%';
        if (statusText) statusText.textContent = 'Welcome to ITER Research';

        setTimeout(() => {
            preloader.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                preloader.remove();
            }, 700);
        }, 350);
    };

    // Ensure preloader completes on page load with minimum viewing window of 750ms for elegance
    const startTime = Date.now();
    window.addEventListener('load', () => {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, 750 - elapsed);
        setTimeout(finishLoading, remaining);
    });

    // Fallback safety timeout so page never gets blocked
    setTimeout(finishLoading, 2500);
})();
</script>
