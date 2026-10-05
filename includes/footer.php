    </main>

    <!-- Institutional Academic Footer -->
    <footer class="bg-slate-900 text-slate-400 text-sm mt-16 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Col 1: Institute Info -->
                <div class="md:col-span-2 space-y-3">
                    <div class="flex items-center gap-2.5 text-white font-bold text-lg">
                        <i class="fa-solid fa-graduation-cap text-iter-400"></i>
                        <span>ITER Research Portal</span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed max-w-md">
                        Official scholarly repository and faculty research showcase of the 
                        <strong class="text-slate-300">Institute of Technical Education and Research (ITER)</strong>, 
                        Faculty of Engineering & Technology, Siksha 'O' Anusandhan (Deemed to be University), Bhubaneswar, Odisha, India.
                    </p>
                    <div class="flex items-center gap-2 pt-1 text-xs text-slate-400">
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 text-amber-300 border border-slate-700 font-mono">NIRF Top Ranked</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 text-emerald-300 border border-slate-700 font-mono">NAAC A++ Grade</span>
                    </div>
                </div>

                <!-- Col 2: Quick Links -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-200 mb-3">Academic Links</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="<?= url('directory.php') ?>" class="hover:text-white transition">Faculty Directory</a></li>
                        <li><a href="<?= url('departments.php') ?>" class="hover:text-white transition">Academic Departments</a></li>
                        <li><a href="https://scholar.google.com" target="_blank" rel="noopener" class="hover:text-white transition">Google Scholar</a></li>
                        <li><a href="https://orcid.org" target="_blank" rel="noopener" class="hover:text-white transition">ORCID Registry</a></li>
                        <li><a href="https://www.scopus.com" target="_blank" rel="noopener" class="hover:text-white transition">Scopus Database</a></li>
                    </ul>
                </div>

                <!-- Col 3: Portal Admin -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-200 mb-3">Portal Access</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="<?= url('login.php') ?>" class="hover:text-white transition">Faculty & Staff Login</a></li>
                        <li><a href="<?= url('register.php') ?>" class="hover:text-white transition">Create Faculty Profile</a></li>
                        <li><a href="<?= url('login.php?role=admin') ?>" class="hover:text-white transition">Assistant / Delegate Portal</a></li>
                        <li><a href="mailto:research@iter.ac.in" class="hover:text-white transition">Contact Research Cell</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-6 border-t border-slate-800 flex flex-col sm:flex-row justify-between items-center text-xs text-slate-500 gap-4">
                <p>&copy; <?= date('Y') ?> ITER, Siksha 'O' Anusandhan. All scholarly rights reserved.</p>
                <p class="flex items-center gap-3">
                    <span>Designed for Scholarly Excellence</span>
                    <span>•</span>
                    <a href="<?= url('admin/index.php') ?>" class="hover:text-slate-400">Administration</a>
                </p>
            </div>
        </div>
    </footer>

</body>
</html>
