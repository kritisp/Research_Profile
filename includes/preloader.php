<?php
/**
 * SOA University & ITER Atmospheric Institutional Preloader
 * Crafted under GSD Phase 4: Cinematic lighting, unclipped luminous bar glow,
 * travelling light shimmer, orbiting comet ring, and smooth 2.4s pacing.
 */
?>
<style>
/* --- Keyframe Animations --- */
@keyframes soa-orbital-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes soa-shimmer-sweep {
    0% { transform: translateX(-150%); }
    100% { transform: translateX(250%); }
}

@keyframes soa-logo-float {
    0%, 100% { transform: translateY(0px) scale(1); }
    50% { transform: translateY(-4px) scale(1.02); }
}

@keyframes soa-aura-pulse {
    0%, 100% { opacity: 0.4; transform: scale(1); filter: blur(25px); }
    50% { opacity: 0.85; transform: scale(1.12); filter: blur(35px); }
}

@keyframes soa-ambient-drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(25px, -20px) scale(1.08); }
}

@keyframes soa-tip-pulse {
    0%, 100% { transform: scale(1); opacity: 0.9; }
    50% { transform: scale(1.4); opacity: 1; }
}

/* --- Container & Elements Styling --- */
#soa-preloader {
    background: radial-gradient(circle at 50% 40%, #0d1628 0%, #080d1a 60%, #04070e 100%);
    transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), 
                filter 0.75s cubic-bezier(0.16, 1, 0.3, 1),
                transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, filter, transform;
}

#soa-preloader.fade-out {
    opacity: 0 !important;
    filter: blur(8px) !important;
    transform: scale(1.03) !important;
    pointer-events: none !important;
}

.soa-logo-card {
    animation: soa-logo-float 4s ease-in-out infinite;
}

.soa-aura {
    animation: soa-aura-pulse 3.5s ease-in-out infinite;
}

.soa-orbit-ring {
    animation: soa-orbital-spin 6s linear infinite;
}

.soa-bg-orb-1 {
    animation: soa-ambient-drift 10s ease-in-out infinite alternate;
}

.soa-bg-orb-2 {
    animation: soa-ambient-drift 12s ease-in-out infinite alternate-reverse;
}

/* Vivid Luminous Glowing Bar */
.soa-glow-track {
    position: relative;
    height: 8px;
    background: rgba(15, 23, 42, 0.85);
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.6), 0 0 15px rgba(225, 29, 72, 0.2);
}

.soa-glow-fill {
    position: relative;
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(90deg, #be123c 0%, #e11d48 35%, #f59e0b 70%, #10b981 100%);
    box-shadow: 0 0 16px rgba(225, 29, 72, 0.8), 
                0 0 30px rgba(245, 158, 11, 0.5), 
                0 0 45px rgba(16, 185, 129, 0.35);
    transition: width 0.18s cubic-bezier(0.2, 0.8, 0.2, 1);
    overflow: hidden;
}

/* Travelling Laser Light Beam */
.soa-shimmer-beam {
    position: absolute;
    top: 0;
    left: 0;
    width: 60%;
    height: 100%;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.95) 50%, transparent 100%);
    animation: soa-shimmer-sweep 1.4s ease-in-out infinite;
}

/* Bright Glowing Head at the Leading Edge */
.soa-glow-tip {
    position: absolute;
    right: -4px;
    top: 50%;
    margin-top: -6px;
    width: 12px;
    height: 12px;
    background: #ffffff;
    border-radius: 9999px;
    box-shadow: 0 0 10px #ffffff, 0 0 20px #fbbf24, 0 0 30px #e11d48;
    animation: soa-tip-pulse 1s ease-in-out infinite;
    pointer-events: none;
}
</style>

<!-- Atmospheric Institutional Preloader Overlay -->
<div id="soa-preloader" class="fixed inset-0 z-[99999] flex flex-col items-center justify-center text-white select-none overflow-hidden">
    
    <!-- Moving Ambient Radial Glows (SOA Crimson & Emerald) -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="soa-bg-orb-1 absolute -top-32 -left-32 w-[450px] h-[450px] rounded-full blur-[90px] opacity-25 bg-rose-600"></div>
        <div class="soa-bg-orb-2 absolute -bottom-32 -right-32 w-[450px] h-[450px] rounded-full blur-[90px] opacity-20 bg-emerald-600"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full blur-[110px] opacity-15 bg-blue-700"></div>
    </div>

    <div class="relative z-10 flex flex-col items-center text-center px-6 max-w-md w-full">

        <!-- Logo Container with Orbital Ring and Aura -->
        <div class="relative w-36 h-36 sm:w-40 sm:h-40 mb-6 flex items-center justify-center soa-logo-card">
            
            <!-- Intense Breathing Aura -->
            <div class="absolute inset-2 rounded-full bg-gradient-to-tr from-rose-600/40 via-amber-500/30 to-emerald-500/30 soa-aura"></div>

            <!-- Rotating Dual-Accent Orbital Ring with Orbiting Comet Head -->
            <div class="absolute -inset-2.5 rounded-full p-[2px] bg-gradient-to-tr from-rose-600 via-amber-400 to-emerald-500 opacity-80 soa-orbit-ring shadow-[0_0_20px_rgba(225,29,72,0.4)]">
                <!-- Inner cutout -->
                <div class="w-full h-full rounded-full bg-[#080d1a]/90 relative">
                    <!-- Comet Head particle -->
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full bg-white shadow-[0_0_10px_#ffffff,0_0_15px_#f59e0b]"></div>
                </div>
            </div>

            <!-- Crisp White Disc Housing Official SOA Logo -->
            <div class="relative w-30 h-30 sm:w-34 sm:h-34 rounded-full p-2 bg-white shadow-2xl ring-2 ring-white/30 flex items-center justify-center overflow-hidden">
                <img src="<?= url('assets/img/soa_logo.png') ?>" 
                     alt="Siksha 'O' Anusandhan" 
                     class="w-full h-full object-contain drop-shadow">
            </div>
        </div>

        <!-- Institutional Typography -->
        <div class="space-y-1.5 mb-7">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-white/5 border border-white/10 text-[10px] font-mono tracking-widest text-amber-300 uppercase font-semibold shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span>NAAC A++ • NIRF Ranked Institution</span>
            </span>
            <h1 class="text-xl sm:text-2xl font-bold font-serif tracking-wider text-slate-100 uppercase drop-shadow-sm pt-1">
                Siksha 'O' Anusandhan
            </h1>
            <p class="text-[11px] font-mono tracking-widest text-slate-400 uppercase">
                (Deemed to be University) • Bhubaneswar
            </p>
            <p class="text-xs font-semibold text-rose-400/90 pt-0.5 tracking-wide">
                Institute of Technical Education and Research (ITER)
            </p>
        </div>

        <!-- Distinct, Visible, and Truly Glowing Progress Track -->
        <div class="w-72 max-w-full flex flex-col items-center gap-3">
            
            <!-- Progress Bar with Active Shimmer and Glowing Tip -->
            <div class="w-full soa-glow-track">
                <div id="soa-preloader-bar" class="soa-glow-fill" style="width: 12%;">
                    <!-- Travelling Shimmer Light Beam -->
                    <div class="soa-shimmer-beam"></div>
                    <!-- Leading Glowing Tip -->
                    <div class="soa-glow-tip"></div>
                </div>
            </div>

            <!-- Percentage Counter & Dynamic Stage Feedback -->
            <div class="w-full flex items-center justify-between text-xs font-mono">
                <span id="soa-preloader-status" class="truncate text-left pr-2 text-slate-300 font-medium">
                    Connecting to academic repository...
                </span>
                <span id="soa-preloader-percent" class="font-bold text-amber-300 font-mono tracking-wider drop-shadow-[0_0_8px_rgba(245,158,11,0.6)]">
                    12%
                </span>
            </div>
        </div>

    </div>

    <!-- Subtle Quick-Access Bypass Button -->
    <button type="button" onclick="dismissPreloader()" 
        class="absolute bottom-6 text-[11px] font-mono text-slate-500 hover:text-slate-300 tracking-wider uppercase transition flex items-center gap-1.5">
        <span>Press to continue</span>
        <i class="fa-solid fa-arrow-right text-[10px]"></i>
    </button>
</div>

<script>
(function() {
    const preloader   = document.getElementById('soa-preloader');
    const bar         = document.getElementById('soa-preloader-bar');
    const percentText = document.getElementById('soa-preloader-percent');
    const statusText  = document.getElementById('soa-preloader-status');

    if (!preloader) return;

    let currentPercent = 12;
    let isFinished = false;

    // Rich narrative stages matching university scholarly portal
    const stages = [
        { upTo: 28, text: 'Connecting to academic repository...' },
        { upTo: 52, text: 'Synchronizing ITER faculty directory...' },
        { upTo: 78, text: 'Loading indexed research publications...' },
        { upTo: 95, text: 'Preparing scholar analytics...' },
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

    // Smooth, organic non-linear progressive load (approx 2.2 - 2.5 seconds total)
    const stepInterval = setInterval(() => {
        if (!isFinished && currentPercent < 94) {
            // Slower at start, accelerating in middle, graceful slow at 90s
            let increment = Math.floor(Math.random() * 4) + 2;
            if (currentPercent > 30 && currentPercent < 75) {
                increment = Math.floor(Math.random() * 6) + 3;
            } else if (currentPercent >= 80) {
                increment = Math.floor(Math.random() * 3) + 1;
            }
            currentPercent += increment;
            if (currentPercent > 94) currentPercent = 94;
            updateDisplay(currentPercent);
        }
    }, 75);

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
            }, 750);
        }, 300);
    };

    // Intentional 2.4-second cinematic presentation so motion is enjoyed and readable
    const startTime = Date.now();
    const handleReady = () => {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, 2400 - elapsed);
        setTimeout(window.dismissPreloader, remaining);
    };

    if (document.readyState === 'complete') {
        handleReady();
    } else {
        window.addEventListener('load', handleReady);
    }

    // Safety fallback
    setTimeout(window.dismissPreloader, 4000);
})();
</script>
