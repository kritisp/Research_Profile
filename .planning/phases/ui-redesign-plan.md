# GSD Implementation Plan: Full UI/UX Redesign — Departmental Scholar

**Design System Authority:** `design-system/departmental-scholar/MASTER.md`
**Aesthetic Style:** Oxford-Ivy Modernity × Swiss Academic Editorial
**Core Principle:** Presentation-layer redesign preserving all backend, authentication, authorization, and security controls.

---

## Phases Overview

| Phase | Description | Status | Verification Gate |
|-------|-------------|--------|-------------------|
| **Phase 1** | Design System Foundation (CSS tokens, typography, radii, base component styles, shared JS) | IN PROGRESS | CSS validated, tokens ready |
| **Phase 2** | Global Chrome (`includes/header.php`, `includes/footer.php`, navigation, mobile menu, flash toast) | PENDING | Responsive nav, accessible focus, PHP lint |
| **Phase 3** | Public Experience (`index.php`, `directory.php`, `departments.php`, filter chip cluster) | PENDING | Filter interactions, database query preservation, PHP lint |
| **Phase 4** | Researcher Profile (`profile.php`, hero, IDs, metrics ribbon, tabs, bibliography, citation modal, teaching bug fix) | PENDING | Citation modal copy, teaching title fix, bibliography view, PHP lint |
| **Phase 5** | Authentication (`login.php`, `register.php`) | PENDING | Form validation, CSRF, security preservation, PHP lint |
| **Phase 6** | Faculty Dashboard (`dashboard/index.php`, `dashboard/edit_profile.php`, add/edit forms) | PENDING | Responsive tables, CRUD functionality, CSRF, PHP lint |
| **Phase 7** | Delegates & Administration (`dashboard/delegates.php`, `assistant/index.php`, `admin/index.php`) | PENDING | POST-only switch, delegate revoke/add, role checks, PHP lint |
| **Phase 8** | Responsive & Accessibility QA | PENDING | Touch targets >=44px, contrast 4.5:1, keyboard nav, Escape close |
| **Phase 9** | Regression & Security Verification | PENDING | All 4 test suites pass, `php -l` 0 errors |

---

## Detailed Task Breakdown

### Phase 1: Design System Foundation
- Create `assets/css/scholar.css`:
  - Design tokens (`--oxford-navy`, `--oxford-blue`, `--academic-gold`, `--scholar-surface`, etc.)
  - Typography classes (`font-scholar-serif` EB Garamond, `font-scholar-sans` Plus Jakarta Sans, `font-scholar-mono` JetBrains Mono)
  - Architectural radii: 4px tags, 6px buttons/inputs, 10px cards
  - Component classes: `.btn-academic-primary`, `.btn-academic-secondary`, `.academic-card`, `.academic-badge`, `.academic-input`, `.academic-table`
- Create `assets/js/scholar.js`:
  - Mobile menu toggle
  - Modal manager with focus trapping and Escape-to-close
  - Citation generator (APA 7, MLA 9, Chicago, Harvard, BibTeX) and clipboard copy
  - Toast notifications and dismissals

### Phase 2: Global Chrome
- Update `includes/header.php`:
  - Include Google Fonts (`EB Garamond`, `Plus Jakarta Sans`, `JetBrains Mono`)
  - Configure Tailwind with design system color palette and typography
  - Build institutional top bar (Oxford Navy `#0E1F38`, gold accents)
  - Build main navigation with logo, active navigation underlines, accessible mobile toggle
  - Build flash message / toast alert presentation with semantic colors
  - Add Skip-to-content accessibility link
- Update `includes/footer.php`:
  - Structured academic layout (Directory, Departments, Academic Registries, Faculty Portal)
  - Institutional information and research support contact
  - Clean copyright notice and responsive alignment

### Phase 3: Public Experience
- Redesign `index.php`:
  - Editorial hero with Oxford Navy background, EB Garamond title, integrated search bar with filters
  - 4-metric institutional impact ribbon from real DB counts
  - Featured researcher showcase (3-column cards with portraits, citations, research tags, publication preview)
  - Department grid with faculty counts and direct directory filters
- Redesign `directory.php`:
  - Unified filter sidebar/bar with keyword, department, institution, sort
  - Active Filter Chip Cluster (removable badges + "Clear All")
  - Clean researcher card list/grid with academic typography
- Redesign `departments.php`:
  - Academic department list cards with research focus and faculty metrics

### Phase 4: Researcher Profile
- Redesign `profile.php`:
  - Scholar Hero with framed avatar, designation, department, verified badge, bio, action dock
  - Cohesive Identifier Ribbon (Google Scholar, ORCID, Scopus, ResearchGate, Web of Science)
  - Faculty Impact Ribbon (Citations, h-index, i10-index, Publications) with transparent self-reported notice
  - Sticky Academic Navigation Rail for smooth in-page tab switching
  - Publications Bibliography with proper academic layout, journal italicization, indexing tags, copy citation & abstract
  - **Fix teaching bug**: Replace `$t['course_name']` with `$t['course_title']`
  - Academic cards for Sponsored Projects, Patents, Honors & Awards, Education, Teaching
  - Editorial empty states with conditional "Add" actions for authorized owners
  - Implement full Academic Citation Modal (APA, MLA, Chicago, Harvard, BibTeX)

### Phase 5: Authentication
- Redesign `login.php`:
  - Elegant academic card layout, clear inputs, focus rings, CSRF token preserved
- Redesign `register.php`:
  - Structured faculty registration with institution field, department selection, forced faculty role intact

### Phase 6: Faculty Dashboard
- Redesign `dashboard/index.php`:
  - Metric summary cards, delegate status banner (if in switched mode, prominent exit button)
  - Responsive tables/cards for recent publications, projects, patents
- Redesign `dashboard/edit_profile.php`:
  - Tabbed or grouped profile settings (Personal, Institutional, Academic IDs, Metrics, Bio, Tags)
- Redesign `dashboard/add_publication.php` & `dashboard/edit_publication.php`:
  - Standardized form layout with clear section dividers and field guides
- Redesign `dashboard/add_project.php` & `dashboard/add_patent.php`:
  - Coherent inputs, funding agency, role, grant amounts

### Phase 7: Delegates & Administration
- Redesign `dashboard/delegates.php`:
  - Faculty delegation manager with active delegates list, invite form, revoke button
- Redesign `assistant/index.php`:
  - Research Assistant portal with assigned faculty cards, POST-only switch forms with CSRF
- Redesign `admin/index.php`:
  - Super Admin dashboard with system metrics, department table, user role management

### Phase 8: Responsive & Accessibility QA
- Verify 375px, 768px, 1024px, 1440px viewports
- Check touch targets (>=44px), focus outlines, escape keys, color contrast

### Phase 9: Regression & Security Verification
- Run `php -l` on all files
- Run `tests/test_switch_workflow.py`
- Run `tests/test_session_hardening.py`
- Run `tests/test_crud_idor.py`
- Run `tests/verify_audit.py`
