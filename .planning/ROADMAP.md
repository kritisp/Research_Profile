# Project Roadmap & Execution Plan

## High-Level Architecture Flow
```
[Database & RBAC Foundation] ──▶ [Comprehensive Research Engine] ──▶ [Delegation Workflow] ──▶ [Academic Scholar UI & Directory] ──▶ [Super Admin & Verification]
```

---

## Phases & Deliverables

### Phase 1: Core Architecture, Database Schema & Multi-Role Authentication
- **Goal**: Establish the relational database schema, security layer (CSRF, sanitization, PDO wrappers), and authentication system with the 3 roles (`super_admin`, `admin`, `faculty`).
- **Deliverables**:
  - [ ] Migration script creating normalized tables: `users`, `departments`, `faculty_profiles`, `faculty_delegates`, `publications`, `projects`, `patents`, `awards`, `education`, `teaching`, `audit_logs`.
  - [ ] Seed default ITER departments (CSE, CSIT, ECE, EE, ME, CE, etc.) and an initial Super Admin account.
  - [ ] Security utilities: `auth.php`, `csrf.php`, `sanitizer.php`.
  - [ ] Session-based authentication with role redirection:
    - Faculty -> Faculty Dashboard
    - Admin / Delegate -> Assistant Management Dashboard
    - Super Admin -> Administration Console
  - [ ] Base layout and theme setup.

---

### Phase 2: Comprehensive Faculty Profile & Research Output Management
- **Goal**: Implement complete, robust data entry forms and management interfaces for all academic and research fields.
- **Deliverables**:
  - [ ] Profile Settings: Personal info, designation, department, cabin, photo upload, bio, research interest tags.
  - [ ] Scholarly IDs & Metrics: Google Scholar, ORCID, Scopus, Web of Science, citations, h-index, i10-index.
  - [ ] Publications Manager: Full CRUD for Journal Articles, Conferences, Book Chapters, Books with DOI, indexing (SCI/Scopus), and abstract.
  - [ ] Projects & Grants Manager: Sponsored research grants tracking (PI/Co-PI, funding agency, amount, status).
  - [ ] Patents & IP Manager: Filed/published/granted patents tracking.
  - [ ] Honors, Awards & Teaching: Qualifications, courses taught, and awards.

---

### Phase 3: Delegated Administration (Trusted Assistant Workflow)
- **Goal**: Allow busy faculty members to delegate profile update permissions to trusted coordinators/assistants (Admins).
- **Deliverables**:
  - [ ] Delegation linking interface: Faculty can invite/assign registered Admins/Assistants.
  - [ ] Super Admin delegation override: Super Admin can assign departmental coordinators to multiple faculty members.
  - [ ] Assistant Profile Switcher: When an Admin logs in, they see a list of assigned faculty members and can switch into their profile to add/update publications seamlessly.
  - [ ] Activity/Audit logging: Record who added or modified records (`created_by`, `updated_by`).

---

### Phase 4: World-Class Scholar UI & Public Faculty Directory
- **Goal**: Build a stunning, academic-first public interface inspired by Google Scholar and top institutional profiles.
- **Deliverables**:
  - [ ] Public Faculty Directory: Search by name, filter by department, filter by research interest tags, sort by citations or seniority.
  - [ ] Individual Scholar Profile Page:
    - Academic hero header with verified badge, photo, contact, and academic ID badges (ORCID, Scopus, Google Scholar).
    - Google Scholar-style metrics sidebar (Total Citations, h-index, i10-index, visual publication timeline).
    - Publications list with instant search, year filtering, and type badges.
    - One-click Citation Copy modal (APA, IEEE, BibTeX) and BibTeX download.
    - Dedicated tabs for Research Grants, Patents, Teaching, and Education.
  - [ ] Printable / Clean Academic CV view.

---

### Phase 5: Super Admin Control Center, Data Seeding & Hardening
- **Goal**: Provide complete institutional management and populate realistic sample data for ITER Bhubaneswar.
- **Deliverables**:
  - [ ] Super Admin management: Department management, user management, profile approval, audit trail viewer.
  - [ ] Realistic data seeder for ITER: Sample professors with realistic publications, citations, and grants for testing and demonstration.
  - [ ] Security hardening and automated verification checks.
