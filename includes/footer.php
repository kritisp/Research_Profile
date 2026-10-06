    </main>

    <!-- Institutional Academic Footer -->
    <footer class="bg-oxford-dark text-slate-300 text-sm mt-20 border-t border-oxford-navy relative z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                <!-- Col 1: Institutional Repository Summary -->
                <div class="space-y-4 md:pr-4">
                    <div class="flex items-center gap-2.5 text-white">
                        <div class="w-8 h-8 rounded-[4px] bg-slate-800 border border-slate-700 p-1 flex items-center justify-center">
                            <img src="<?= url('assets/img/scholar_hat.svg') ?>" alt="Scholar Logo" class="w-full h-full object-contain">
                        </div>
                        <span class="font-serif font-bold text-lg tracking-tight">Departmental Scholar</span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Institutional scholarly repository and faculty research indexing system showcasing peer-reviewed contributions, sponsored grants, and intellectual property.
                    </p>
                    <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px]">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[4px] bg-slate-800 text-amber-300 border border-slate-700 font-mono">
                            <i class="fa-solid fa-graduation-cap text-[10px] mr-1.5 opacity-80"></i>Peer-Reviewed
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-[4px] bg-slate-800 text-emerald-300 border border-slate-700 font-mono">
                            <i class="fa-solid fa-shield-check text-[10px] mr-1.5 opacity-80"></i>Verified Output
                        </span>
                    </div>
                </div>

                <!-- Col 2: Academic Directories -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-200 mb-4 font-mono">Academic Repositories</h3>
                    <ul class="space-y-2.5 text-xs text-slate-400">
                        <li>
                            <a href="<?= url('directory.php') ?>" class="hover:text-white transition flex items-center gap-2">
                                <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                                <span>Faculty Directory</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?= url('departments.php') ?>" class="hover:text-white transition flex items-center gap-2">
                                <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                                <span>Academic Departments</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://scholar.google.com" target="_blank" rel="noopener" class="hover:text-white transition flex items-center gap-2">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-600"></i>
                                <span>Google Scholar Citations</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://orcid.org" target="_blank" rel="noopener" class="hover:text-white transition flex items-center gap-2">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-600"></i>
                                <span>ORCID Academic Registry</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.scopus.com" target="_blank" rel="noopener" class="hover:text-white transition flex items-center gap-2">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-600"></i>
                                <span>Scopus Author Profiles</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Col 3: Faculty & Researcher Portal -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-200 mb-4 font-mono">Faculty Portal</h3>
                    <ul class="space-y-2.5 text-xs text-slate-400">
                        <?php if (is_logged_in()): ?>
                            <li>
                                <a href="<?= url('dashboard/index.php') ?>" class="hover:text-white transition flex items-center gap-2">
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                                    <span>Faculty Dashboard</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= url('dashboard/edit_profile.php') ?>" class="hover:text-white transition flex items-center gap-2">
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                                    <span>Edit Scholarly Profile</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= url('logout.php') ?>" class="hover:text-rose-400 transition flex items-center gap-2">
                                    <i class="fa-solid fa-right-from-bracket text-[10px] text-slate-600"></i>
                                    <span>Sign Out</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li>
                                <a href="<?= url('login.php') ?>" class="hover:text-white transition flex items-center gap-2">
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                                    <span>Faculty Sign In</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= url('register.php') ?>" class="hover:text-white transition flex items-center gap-2">
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-600"></i>
                                    <span>Register New Faculty Account</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a href="mailto:research@iter.ac.in" class="hover:text-white transition flex items-center gap-2">
                                <i class="fa-solid fa-envelope text-[10px] text-slate-600"></i>
                                <span>Research Cell Helpdesk</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Col 4: Research Ethics & Standards -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-200 mb-4 font-mono">Integrity & Governance</h3>
                    <p class="text-xs text-slate-400 leading-relaxed mb-3">
                        Curated under university research ethics standards. All scholarly contributions, indexing markers, and grant records are maintained under academic governance.
                    </p>
                    <div class="text-[11px] text-slate-500 space-y-1">
                        <div>ISSN / DOI Compliant Metadata</div>
                        <div>Open Access Scholarly Archiving</div>
                    </div>
                </div>
            </div>

            <!-- Bottom Institutional Bar -->
            <div class="mt-12 pt-6 border-t border-slate-800/80 flex flex-col sm:flex-row justify-between items-center text-xs text-slate-500 gap-4">
                <p>&copy; <?= date('Y') ?> Departmental Scholar. Institutional Academic Repository. All rights reserved.</p>
                <div class="flex items-center gap-4 text-[11px]">
                    <span class="text-slate-400">Peer-Reviewed Academic Repository</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- External Co-Author / Collaborator: Profile Not Available Modal -->
    <div id="profileNotFoundModal" 
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden transition-opacity duration-200" 
         role="dialog" 
         aria-modal="true" 
         aria-labelledby="pNotFoundTitle"
         onclick="if(event.target === this) closeProfileNotFoundModal()">
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 p-6 sm:p-7 text-center transform transition-all animate-in fade-in zoom-in-95 duration-150" 
             onclick="event.stopPropagation()">
            <!-- Close Button -->
            <button type="button" 
                    onclick="closeProfileNotFoundModal()" 
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 w-8 h-8 rounded-full flex items-center justify-center hover:bg-slate-100 transition-colors" 
                    aria-label="Close dialog">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>

            <!-- Academic Icon -->
            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-oxford-slate mb-4 shadow-xs">
                <i class="fa-solid fa-user-slash text-xl text-slate-500"></i>
            </div>

            <!-- Header -->
            <h3 id="pNotFoundTitle" class="font-serif font-bold text-xl text-oxford-navy mb-2">
                Profile Not Available
            </h3>

            <!-- Notice Message -->
            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-sans mb-6">
                <strong id="pNotFoundAuthorName" class="font-bold text-oxford-navy"></strong> is an external co-author / collaborator without an institutional profile in Departmental Scholar.
            </p>

            <!-- Actions -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-center gap-3">
                <button type="button" 
                        onclick="closeProfileNotFoundModal()" 
                        class="btn-academic-secondary text-xs !py-2.5 !px-5 shadow-xs">
                    Dismiss
                </button>
                <a id="pNotFoundScholarLink" 
                   href="#" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="btn-academic-primary text-xs !py-2.5 !px-5 shadow-xs inline-flex items-center gap-1.5">
                    <i class="fa-brands fa-google-scholar text-xs"></i>
                    <span>Search Scholar</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Shared Modular Frontend Script -->
    <script src="<?= url('assets/js/scholar.js') ?>"></script>
</body>
</html>
