# Phase 1: Database Architecture, Security Core & RBAC Authentication

## Objective
Establish the relational schema for the ITER Departmental Research Profile system in MySQL, build the core security/session layer (PDO, CSRF, XSS escaping, password hashing), and implement the multi-tier authentication system for Super Admin, Admin (Assistants/Delegates), and Faculty members.

---

## Tasks Breakdown

### Task 1.1: Database Schema & Migration
- [ ] Create `database/schema.sql` defining 11 normalized relational tables:
  1. `users`
  2. `departments`
  3. `faculty_profiles`
  4. `faculty_delegates`
  5. `publications`
  6. `projects`
  7. `patents`
  8. `awards`
  9. `education`
  10. `teaching`
  11. `audit_logs`
- [ ] Create `database/migrate.php` CLI/web migration script that creates the tables and seeds default ITER departments (CSE, CS&IT, ECE, EE, ME, CE) and a default Super Admin account.

### Task 1.2: Security & Utility Library
- [ ] Create `includes/helpers.php`:
  - `e($string)`: HTML escaping helper to prevent XSS.
  - `url($path)`: Base URL generator.
  - `flash($type, $message)`: Session flash notifications.
  - `slugify($text)`: URL slug generator.
- [ ] Create `includes/csrf.php`:
  - Anti-CSRF token generator and validator.
  - Form token input helper (`csrf_field()`).
- [ ] Create `includes/auth.php`:
  - `is_logged_in()`, `current_user()`, `has_role($role)`, `require_role($roles)`.
  - Session security (session fixation protection, secure cookies).

### Task 1.3: Responsive Academic Layout Shell
- [ ] Create `includes/header.php` and `includes/footer.php` with:
  - Modern academic theme (Tailwind CSS, Inter sans-serif + Merriweather serif accents).
  - Navigation bar with ITER branding, public search link, directory link, and Login/Dashboard buttons.
  - Flash message toast component.

### Task 1.4: Multi-Role Authentication System
- [ ] Create `login.php`:
  - Clean credentials form with CSRF protection and role-aware redirect:
    - Super Admin -> `admin/index.php`
    - Admin (Delegate) -> `assistant/index.php`
    - Faculty -> `dashboard/index.php`
- [ ] Create `register.php`:
  - Faculty / Assistant signup with department selector and validation.
- [ ] Create `logout.php`.

### Task 1.5: Verification & Testing
- [ ] Execute migration to create all tables in `research_profile_db`.
- [ ] Test login, registration, role checks, and database integrity.
