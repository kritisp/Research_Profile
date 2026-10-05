/**
 * Departmental Scholar — Modular Frontend Utilities
 * Modern Vanilla JS: Mobile Nav, Accessible Modals, Citation Generator, Toast System
 */

document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initCitationModal();
  initTabs();
  initToastDismiss();
});

/**
 * Mobile Navigation Toggle with ARIA Support
 */
function initMobileNav() {
  const toggleBtn = document.getElementById('mobileNavToggle');
  const mobileMenu = document.getElementById('mobileNavMenu');
  if (!toggleBtn || !mobileMenu) return;

  toggleBtn.addEventListener('click', () => {
    const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
    toggleBtn.setAttribute('aria-expanded', !isExpanded);
    mobileMenu.classList.toggle('hidden');
  });

  // Close mobile nav on resize above mobile breakpoint
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 768 && !mobileMenu.classList.contains('hidden')) {
      mobileMenu.classList.add('hidden');
      toggleBtn.setAttribute('aria-expanded', 'false');
    }
  });
}

/**
 * Toast Notification Dismissal
 */
function initToastDismiss() {
  document.querySelectorAll('[data-dismiss="toast"]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const toast = e.currentTarget.closest('.scholar-toast');
      if (toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-6px)';
        toast.style.transition = 'all 180ms ease';
        setTimeout(() => toast.remove(), 200);
      }
    });
  });
}

/**
 * Profile Section Sticky Tabs
 */
function initTabs() {
  const tabLinks = document.querySelectorAll('[data-scholar-tab]');
  if (!tabLinks.length) return;

  tabLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = link.getAttribute('data-scholar-tab');
      const targetElement = document.getElementById(targetId);
      if (!targetElement) return;

      // Update active tab styles
      tabLinks.forEach(l => {
        l.classList.remove('border-oxford-blue', 'text-oxford-blue', 'font-semibold');
        l.classList.add('border-transparent', 'text-slate-500');
        l.setAttribute('aria-selected', 'false');
      });
      link.classList.add('border-oxford-blue', 'text-oxford-blue', 'font-semibold');
      link.classList.remove('border-transparent', 'text-slate-500');
      link.setAttribute('aria-selected', 'true');

      // Smooth scroll into view accounting for sticky header
      const headerOffset = 130;
      const elementPosition = targetElement.getBoundingClientRect().top;
      const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

      window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth'
      });
    });
  });
}

/**
 * Academic Citation Modal with Multi-Format Generator
 */
function initCitationModal() {
  const modal = document.getElementById('citationModal');
  if (!modal) return;

  const closeBtns = modal.querySelectorAll('[data-close-modal]');
  const copyBtn = document.getElementById('copyCitationBtn');
  const citationContent = document.getElementById('citationContentText');
  const formatTabs = modal.querySelectorAll('[data-citation-format]');
  let lastActiveElement = null;
  let currentPublicationData = null;

  // Open modal on click of any cite button
  document.querySelectorAll('[data-cite-btn]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      lastActiveElement = btn;
      try {
        const rawData = btn.getAttribute('data-publication');
        currentPublicationData = JSON.parse(rawData);
      } catch (err) {
        currentPublicationData = {
          title: btn.getAttribute('data-title') || 'Untitled',
          authors: btn.getAttribute('data-authors') || '',
          venue: btn.getAttribute('data-venue') || '',
          year: btn.getAttribute('data-year') || '',
          volume: btn.getAttribute('data-volume') || '',
          issue: btn.getAttribute('data-issue') || '',
          pages: btn.getAttribute('data-pages') || '',
          doi: btn.getAttribute('data-doi') || ''
        };
      }

      renderCitation('apa');
      openModal();
    });
  });

  function openModal() {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    modal.setAttribute('aria-hidden', 'false');
    const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (firstFocusable) firstFocusable.focus();
  }

  function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    modal.setAttribute('aria-hidden', 'true');
    if (lastActiveElement) lastActiveElement.focus();
  }

  closeBtns.forEach(b => b.addEventListener('click', closeModal));

  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeModal();
    }
  });

  // Switch format
  formatTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      formatTabs.forEach(t => {
        t.classList.remove('bg-oxford-navy', 'text-white');
        t.classList.add('bg-slate-100', 'text-slate-700', 'hover:bg-slate-200');
        t.setAttribute('aria-selected', 'false');
      });
      tab.classList.remove('bg-slate-100', 'text-slate-700', 'hover:bg-slate-200');
      tab.classList.add('bg-oxford-navy', 'text-white');
      tab.setAttribute('aria-selected', 'true');

      const format = tab.getAttribute('data-citation-format');
      renderCitation(format);
    });
  });

  // Generate citation string based on genuine publication data
  function renderCitation(format) {
    if (!currentPublicationData || !citationContent) return;
    const d = currentPublicationData;
    const authors = d.authors ? d.authors.trim() : 'Author';
    const title = d.title ? d.title.trim().replace(/\.$/, '') : 'Untitled Publication';
    const venue = d.venue ? d.venue.trim() : '';
    const year = d.year ? d.year : '';
    const vol = d.volume ? `vol. ${d.volume}` : '';
    const iss = d.issue ? `no. ${d.issue}` : '';
    const pages = d.pages ? `pp. ${d.pages}` : '';
    const doi = d.doi ? (d.doi.startsWith('http') ? d.doi : `https://doi.org/${d.doi}`) : '';

    let formatted = '';

    switch (format) {
      case 'apa':
        // APA 7: Author, A. A. (Year). Title of article. Title of Periodical, xx(x), pp-pp. https://doi.org/xx
        formatted = `${authors}${year ? ` (${year})` : ''}. ${title}.`;
        if (venue) formatted += ` ${venue}`;
        if (d.volume) formatted += `, ${d.volume}`;
        if (d.issue) formatted += `(${d.issue})`;
        if (d.pages) formatted += `, ${d.pages}`;
        formatted += '.';
        if (doi) formatted += ` ${doi}`;
        break;

      case 'mla':
        // MLA 9: Author. "Title." Title of Periodical, vol. x, no. x, Year, pp. xx-xx.
        formatted = `${authors}. "${title}."`;
        if (venue) formatted += ` ${venue}`;
        if (vol) formatted += `, ${vol}`;
        if (iss) formatted += `, ${iss}`;
        if (year) formatted += `, ${year}`;
        if (pages) formatted += `, ${pages}`;
        formatted += '.';
        if (doi) formatted += ` ${doi}`;
        break;

      case 'chicago':
        // Chicago: Author. "Title." Venue vol, no. iss (Year): pages.
        formatted = `${authors}. "${title}."`;
        if (venue) formatted += ` ${venue}`;
        if (d.volume) formatted += ` ${d.volume}`;
        if (d.issue) formatted += `, no. ${d.issue}`;
        if (year) formatted += ` (${year})`;
        if (d.pages) formatted += `: ${d.pages}`;
        formatted += '.';
        if (doi) formatted += ` ${doi}`;
        break;

      case 'harvard':
        // Harvard: Authors, Year. Title. Venue, Volume(Issue), pp.Pages.
        formatted = `${authors}${year ? `, ${year}` : ''}. ${title}.`;
        if (venue) formatted += ` ${venue}`;
        if (d.volume) formatted += `, ${d.volume}`;
        if (d.issue) formatted += `(${d.issue})`;
        if (pages) formatted += `, ${pages}`;
        formatted += '.';
        if (doi) formatted += ` Available at: ${doi}`;
        break;

      case 'bibtex':
        // BibTeX: Clean structured entry
        const citeKey = (authors.split(/[\s,]+/)[0] || 'Scholar').toLowerCase() + (year || '2026') + (title.split(' ')[0] || '').toLowerCase();
        formatted = `@article{${citeKey},\n` +
          `  title = {${title}},\n` +
          `  author = {${authors}},\n` +
          (venue ? `  journal = {${venue}},\n` : '') +
          (year ? `  year = {${year}},\n` : '') +
          (d.volume ? `  volume = {${d.volume}},\n` : '') +
          (d.issue ? `  number = {${d.issue}},\n` : '') +
          (d.pages ? `  pages = {${d.pages}},\n` : '') +
          (d.doi ? `  doi = {${d.doi}}\n` : '') +
          `}`;
        break;

      default:
        formatted = `${authors} (${year}). ${title}. ${venue}.`;
    }

    citationContent.textContent = formatted.trim();
  }

  // Copy to clipboard
  if (copyBtn && citationContent) {
    copyBtn.addEventListener('click', async () => {
      const textToCopy = citationContent.textContent;
      try {
        await navigator.clipboard.writeText(textToCopy);
        const originalHtml = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fa-solid fa-check mr-1.5 text-emerald-300"></i> Copied!';
        copyBtn.classList.remove('btn-academic-primary');
        copyBtn.classList.add('bg-emerald-700', 'text-white');
        setTimeout(() => {
          copyBtn.innerHTML = originalHtml;
          copyBtn.classList.add('btn-academic-primary');
          copyBtn.classList.remove('bg-emerald-700', 'text-white');
        }, 2000);
      } catch (err) {
        // Fallback for older browsers or non-secure contexts
        const textarea = document.createElement('textarea');
        textarea.value = textToCopy;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copyBtn.innerText = 'Copied!';
        setTimeout(() => { copyBtn.innerText = 'Copy Citation'; }, 2000);
      }
    });
  }
}
