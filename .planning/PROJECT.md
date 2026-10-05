# Project Specification: ITER Departmental Research Profile Portal

## Vision & Overview
The **ITER Departmental Research Profile Portal** is a premier academic research profile and directory system designed specifically for the faculties of **ITER (Institute of Technical Education and Research), SOA University, Bhubaneswar**.

Inspired by platforms like **Google Scholar**, **ResearchGate**, and Ivy-League faculty portals, the platform provides:
1. **Public Academic Showcase**: A public directory and individual researcher profiles featuring citation metrics, research interests, publication feeds with BibTeX/citation export, funded projects, patents, and teaching records.
2. **Comprehensive Research Management**: A granular, multi-section management interface enabling researchers to record all academic outputs without omitting critical scholarly metadata.
3. **Multi-Tier Delegated Administration (RBAC)**: A permission model accommodating busy professors by allowing assigned trusted assistants/coordinators (Admins) to manage publications and achievements on behalf of faculty members, supervised by Super Admins.
4. **Zero-Slop, High-Craft Aesthetics**: A typography-driven, academic UI that avoids generic boilerplate styles in favor of a timeless, clean scholarly design.

- **Status**: Planning & Architecture Approved
- **Target Organization**: ITER, Siksha 'O' Anusandhan (SOA Deemed to be University), Bhubaneswar
- **Last Updated**: 2026-10-05

---

## Core Goals
1. **Accreditation & Institutional Visibility**: Provide verified, searchable, and exportable research records for NBA, NAAC, NIRF, and institutional reporting.
2. **Effortless Scholarly Tracking**: Support full tracking of journals, conferences, book chapters, citations, h-index, grants, patents, awards, and qualifications.
3. **Faculty Delegation Workflow**: Allow professors to delegate profile management to trusted student researchers, lab assistants, or departmental coordinators.
4. **Google Scholar Parity & Exportability**: Clean citation metrics, automatic BibTeX formatting, one-click citation copy (APA, IEEE, Chicago), and printable/exportable CV view.

---

## Roles & Access Control Model (RBAC)
| Role | Permissions & Responsibilities |
| :--- | :--- |
| **Super Admin** | Full platform authority: manage departments, manage users & roles, assign delegates to faculties, verify/activate profiles, site-wide audit logs, global announcements. |
| **Admin / Delegate** | Department coordinators or trusted lab assistants/scholars: permitted to create, edit, and update research outputs for assigned faculty members without needing faculty passwords. |
| **Faculty Member** | Self-managed profile: full CRUD over personal bio, publications, grants, patents, teaching, and awards. Can nominate or approve delegates. |
| **Public Visitor** | Read-only directory: search faculty by name, department, or research keywords; browse publications; copy citations; view citation metrics. |

---

## Architectural Principles & Non-Negotiables
- **Zero AI Slop**: Custom layout inspired by world-class academic institutions (Google Scholar + Stanford/MIT faculty profiles). Distinct typography, clean contrast, crisp badges, no generic purple-gradient cards.
- **Security & Integrity**:
  - Prepared statements with PDO for 100% of database interactions.
  - Strict input validation and context-aware output escaping (`htmlspecialchars`).
  - Secure session management with regeneration on login and privilege escalation checks.
  - BCrypt password hashing (`password_hash` with `PASSWORD_DEFAULT`).
  - CSRF token validation on all POST/mutation requests.
  - Role-based route protection middleware.
- **Maintainable Architecture**:
  - Clean modular PHP architecture without heavy framework bloat.
  - Reusable layout components (header, footer, sidebar, profile cards, publication item).
  - Explicit database schema with relational foreign keys and indexes.

---

## Tech Stack
- **Server Runtime**: PHP 8.2+ (XAMPP / Apache on `http://localhost/research_profile/`)
- **Database**: MySQL 8.0+ (`research_profile_db`) with InnoDB and `utf8mb4` encoding
- **Styling**: Modern Tailwind CSS (production-ready aesthetic, zero build-step overhead) + Custom Academic Typography & Accent Styling
- **Icons & Assets**: Lucide / FontAwesome icons via clean SVG/CDN
- **Client Scripting**: Modern Vanilla JavaScript (search debounce, dynamic form repeaters, citation copy modal, tab switching)
