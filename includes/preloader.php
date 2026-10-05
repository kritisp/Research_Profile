<?php
/**
 * SOA University Institutional Splash Preloader
 * High clarity, clean aesthetic matching the official SOA crimson & emerald logo theme.
 */
?>
<!-- SOA University Clear & Smooth Preloader -->
<div id="soa-preloader" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-white text-slate-900 transition-all duration-600 ease-out select-none">
    
    <!-- Subtle Ambient Glow echoing Logo's Crimson & Emerald -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-80 h-80 bg-rose-100/50 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-80 h-80 bg-emerald-100/40 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 flex flex-col items-center text-center px-6 max-w-sm">

        <!-- Official Circular SOA University Logo -->
        <div class="relative mb-5 group">
            <!-- Delicate Theme Glow (Rose & Emerald) -->
            <div class="absolute -inset-2 rounded-full bg-gradient-to-tr from-rose-500/20 to-emerald-500/20 blur-md animate-pulse"></div>

            <div class="relative w-28 h-28 sm:w-32 sm:h-32 rounded-full p-1 bg-white shadow-xl ring-1 ring-slate-100 flex items-center justify-center">
                <img src="<?= url('assets/img/soa_logo.png') ?>" 
                     alt="Siksha 'O' Anusandhan (SOA) Deemed to be University" 
                     class="w-full h-full object-contain rounded-full transition-transform duration-500 hover:scale-105">
            </div>
        </div>

        <!-- Typography -->
        <div class="space-y-1">
            <h2 class="text-base sm:text-lg font-bold tracking-tight text-slate-900 font-serif-title uppercase">
                Siksha 'O' Anusandhan
            </h2>
            <p class="text-[11px] font-mono tracking-widest text-slate-500 uppercase">
                Deemed to be University • Bhubaneswar
            </p>
            <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 rounded-full bg-slate-50 border border-slate-200/80 text-[10px] font-semibold text-slate-700 shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-ping"></span>
                <span>ITER Faculty Research Directory</span>
            </div>
        </div>

        <!-- Smooth Progress Line themed to SOA Crimson & Emerald -->
        <div class="mt-7 w-52 flex flex-col items-center gap-2">
            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden p-0.5 border border-slate-200">
                <div id="preloader-bar" 
                     class="h-full bg-gradient-to-r from-rose-600 via-rose-500 to-emerald-500 rounded-full transition-all duration-300 ease-out" 
                     style="width: 25%;"></div>
            </div>
            <span id="preloader-status" class="text-[11px] font-mono text-slate-400 tracking-wide">
                Initializing repository...
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

    // Smooth progressive load feel
    let progress = 25;
    const interval = setInterval(() => {
        if (progress < 85) {
            progress += Math.floor(Math.random() * 15) + 8;
            if (progress > 85) progress = 85;
            if (bar) bar.style.width = progress + '%';
        }
    }, 100);

    const finishLoading = () => {
        clearInterval(interval);
        if (bar) bar.style.width = '100%';
        if (statusText) statusText.textContent = 'Welcome to ITER Research';

        setTimeout(() => {
            preloader.style.opacity = '0';
            preloader.style.pointerEvents = 'none';
            preloader.style.transform = 'scale(1.01)';
            setTimeout(() => {
                preloader.remove();
            }, 650);
        }, 300);
    };

    // Complete on page load with minimum 700ms visibility for clear viewing
    const startTime = Date.now();
    window.addEventListener('load', () => {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, 700 - elapsed);
        setTimeout(finishLoading, remaining);
    });

    // Fallback safety timeout (max 2 seconds)
    setTimeout(finishLoading, 2000);
})();
</script>
