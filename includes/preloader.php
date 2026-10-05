<?php
/**
 * SOA University & ITER Atmospheric Institutional Preloader
 * Crafted under GSD Phase 4: deep academic navy palette with subtle crimson & emerald accents,
 * real visible progression counter, and guaranteed smooth transition.
 */
?>
<style>
@keyframes soa-orbital-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes soa-pulse-glow {
    0%, 100% { opacity: 0.35; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.04); }
}
.soa-orbital-ring {
    animation: soa-orbital-spin 8s linear infinite;
}
.soa-glow-pulse {
    animation: soa-pulse-glow 3s ease-in-out infinite;
}
#soa-preloader {
    background-color: #080d1a;
    transition: opacity 0.65s cubic-bezier(0.16, 1, 0.3, 1), 
                filter 0.65s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.65s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, filter, transform;
}
#soa-preloader.fade-out {
    opacity: 0 !important;
    filter: blur(6px) !important;
    transform: scale(1.02) !important;
    pointer-events: none !important;
}
</style>

<!-- Atmospheric Institutional Preloader Overlay -->
<div id="soa-preloader" class="fixed inset-0 z-[99999] flex flex-col items-center justify-center text-white select-none overflow-hidden">
    
    <!-- Ambient Radial Gradients (Subtle Crimson & Emerald) -->
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full blur-3xl opacity-20 bg-rose-600"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 rounded-full blur-3xl opacity-15 bg-emerald-600"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] rounded-full blur-[100px] opacity-10 bg-iter-700"></div>
    </div>

    <div class="relative z-10 flex flex-col items-center text-center px-6 max-w-md w-full">

        <!-- Logo Container with Orbital Ring -->
        <div class="relative w-32 h-32 sm:w-36 sm:h-36 mb-6 flex items-center justify-center">
            
            <!-- Soft Ambient Glow -->
            <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-rose-600/30 to-emerald-500/20 blur-xl soa-glow-pulse"></div>

            <!-- Rotating Dual-Accent Orbital Ring (Crimson to Emerald) -->
            <div class="absolute -inset-2 rounded-full p-[2px] bg-gradient-to-tr from-rose-600 via-amber-400 to-emerald-500 opacity-70 soa-orbital-ring">
                <div class="w-full h-full rounded-full bg-[#080d1a]"></div>
            </div>

            <!-- Crisp Logo Circular Badge -->
            <div class="relative w-28 h-28 sm:w-32 sm:h-32 rounded-full p-2 bg-white/95 shadow-2xl ring-1 ring-white/20 flex items-center justify-center overflow-hidden">
                <img src="<?= url('assets/img/soa_logo.png') ?>" 
                     alt="Siksha 'O' Anusandhan" 
                     class="w-full h-full object-contain drop-shadow">
            </div>
        </div>

        <!-- Institutional Typography -->
        <div class="space-y-1.5 mb-7">
            <span class="inline-block px-3 py-0.5 rounded-full bg-white/5 border border-white/10 text-[10px] font-mono tracking-widest text-amber-300 uppercase font-semibold">
                NAAC A++ • NIRF Ranked Institution
            </span>
            <h1 class="text-lg sm:text-xl font-bold font-serif tracking-wider text-slate-100 uppercase">
                Siksha 'O' Anusandhan
            </h1>
            <p class="text-[11px] font-mono tracking-widest text-slate-400 uppercase">
                (Deemed to be University) • Bhubaneswar
            </p>
            <p class="text-xs font-semibold text-rose-400/90 pt-0.5 tracking-wide">
                Institute of Technical Education and Research (ITER)
            </p>
        </div>

        <!-- Visible & Active Progress Tracker -->
        <div class="w-64 max-w-full flex flex-col items-center gap-2.5">
            
            <!-- Progress Bar with Glow -->
            <div class="w-full h-1.5 bg-slate-900 rounded-full overflow-hidden p-0.5 border border-slate-800 shadow-inner">
                <div id="soa-preloader-bar" 
                     class="h-full bg-gradient-to-r from-rose-600 via-amber-400 to-emerald-500 rounded-full transition-all duration-150 ease-out shadow-[0_0_12px_rgba(225,29,72,0.5)]" 
                     style="width: 10%;"></div>
            </div>

            <!-- Percentage Counter & Dynamic Status Message -->
            <div class="w-full flex items-center justify-between text-[11px] font-mono text-slate-400">
                <span id="soa-preloader-status" class="truncate text-left pr-2 text-slate-300">
                    Connecting to repository...
                </span>
                <span id="soa-preloader-percent" class="font-bold text-amber-300">10%</span>
            </div>
        </div>

    </div>

    <!-- Subtle Quick-Access Bypass Button -->
    <button type="button" onclick="dismissPreloader()" 
        class="absolute bottom-6 text-[10px] font-mono text-slate-500 hover:text-slate-300 tracking-wider uppercase transition">
        Press to continue &rarr;
    </button>
</div>

<script>
(function() {
    const preloader   = document.getElementById('soa-preloader');
    const bar         = document.getElementById('soa-preloader-bar');
    const percentText = document.getElementById('soa-preloader-percent');
    const statusText  = document.getElementById('soa-preloader-status');

    if (!preloader) return;

    let currentPercent = 10;
    let isFinished = false;

    const stages = [
        { upTo: 30, text: 'Connecting to repository...' },
        { upTo: 65, text: 'Indexing ITER faculty directory...' },
        { upTo: 88, text: 'Loading research publications...' },
        { upTo: 100, text: 'Welcome to ITER Research Portal' }
    ];

    const updateDisplay = (p) => {
        if (bar) bar.style.width = p + '%';
        if (percentText) percentText.textContent = p + '%';

        if (statusText) {
            for (let stage of stages) {
                if (p <= stage.upTo) {
                    statusText.textContent = stage.text;
                    break;
                }
            }
        }
    };

    // Smooth progressive movement
    const stepInterval = setInterval(() => {
        if (!isFinished && currentPercent < 88) {
            currentPercent += Math.floor(Math.random() * 8) + 4;
            if (currentPercent > 88) currentPercent = 88;
            updateDisplay(currentPercent);
        }
    }, 70);

    window.dismissPreloader = function() {
        if (isFinished) return;
        isFinished = true;
        clearInterval(stepInterval);
        
        updateDisplay(100);

        setTimeout(() => {
            preloader.classList.add('fade-out');
            setTimeout(() => {
                if (preloader && preloader.parentNode) {
                    preloader.parentNode.removeChild(preloader);
                }
            }, 680);
        }, 250);
    };

    // Minimum display time of 1000ms so the user can clearly view the brand & animation
    const startTime = Date.now();
    const handleReady = () => {
        const elapsed = Date.now() - startTime;
        const delay = Math.max(0, 1000 - elapsed);
        setTimeout(window.dismissPreloader, delay);
    };

    if (document.readyState === 'complete') {
        handleReady();
    } else {
        window.addEventListener('load', handleReady);
    }

    // Safety fallback: maximum 2.8s
    setTimeout(window.dismissPreloader, 2800);
})();
</script>
