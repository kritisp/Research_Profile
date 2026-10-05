# Requirements & Scope Traceability

## Functional Requirements

### 1. Authentication & Role-Based Access Control (RBAC)
- **FR-AUTH-01**: Secure user registration and login with email and hashed passwords.
- **FR-AUTH-02**: Three distinct roles: `super_admin`, `admin` (faculty delegate/coordinator), and `faculty`.
- **FR-AUTH-03**: Role-based access control protecting admin endpoints and mutation actions.
- **FR-AUTH-04**: Password reset / update capability and secure session destruction on logout.

### 2. Department & Institutional Management
- **FR-DEPT-01**: Standardized departments for ITER (e.g., Computer Science & Engineering, Computer Science & Information Technology, Electronics & Communication Engineering, Electrical Engineering, Mechanical Engineering, Civil Engineering, etc.).
- **FR-DEPT-02**: Super Admin can create, edit, activate, or deactivate departments.

### 3. Comprehensive Faculty Profile Management
- **FR-PROF-01 (Basic Info)**: Salutation, Full Name, Designation (Professor, Associate Professor, Assistant Professor, HoD, Dean), Department, Institutional Email, Phone, Cabin/Office Location, Joining Year, Profile Photo upload.
- **FR-PROF-02 (Scholarly IDs & Metrics)**: ORCID ID, Scopus Author ID, Google Scholar Profile Link, ResearchGate Link, Web of Science ID, total citations count, h-index, i10-index.
- **FR-PROF-03 (Research Statement & Interests)**: Executive bio/about statement, tagged research interests (e.g., "Machine Learning", "VLSI Design", "Fluid Mechanics").
- **FR-PROF-04 (Qualifications)**: Education history (Degree, Institution, Passing Year, Field of Study).
- **FR-PROF-05 (Experience)**: Academic and industry work history with positions and durations.

### 4. Scholarly Output & Research Management
- **FR-PUB-01 (Publications)**: Comprehensive publication tracking:
  - Title, full author list, publication type (`Journal Article`, `Conference Paper`, `Book Chapter`, `Book`).
  - Journal/Conference name, publication year, volume, issue, page numbers, publisher.
  - DOI, direct link, abstract, indexing category (`SCI/SCIE`, `Scopus`, `UGC Care`, `Peer-Reviewed`), citation count.
- **FR-PUB-02 (Citation & BibTeX)**: Automatic BibTeX generation and one-click citation copy (APA, IEEE, BibTeX formats).
- **FR-PROJ-01 (Sponsored Research & Grants)**: Title, funding body (DST, SERB, DRDO, AICTE, Industry, etc.), grant amount (INR Lakhs), role (`Principal Investigator`, `Co-PI`), project timeline, and status (`Ongoing`, `Completed`).
- **FR-PAT-01 (Patents & IP)**: Invention title, patent number, filing/granting country, status (`Filed`, `Published`, `Granted`), year/dates.
- **FR-AWD-01 (Awards & Recognitions)**: Award title, awarding agency/society (e.g. IEEE, INAE, Springer), year, and description.
- **FR-TEACH-01 (Teaching & Courses)**: Course title, course code, level (`Undergraduate`, `Postgraduate`, `Ph.D.`), semester/year.

### 5. Delegated Administration (Trusted Assistant Workflow)
- **FR-DEL-01**: Faculty members can grant delegate access to specific registered Admin/Assistant accounts.
- **FR-DEL-02**: Super Admins can assign departmental coordinators (Admins) to manage faculty profiles.
- **FR-DEL-03**: Admins/Delegates can switch between assigned faculty profiles in their dashboard to add, update, and manage research records on the faculty's behalf.
- **FR-DEL-04**: Complete audit trail showing who added or modified any publication or research item.

### 6. Public Directory & Academic Profile Experience
- **FR-PUBUI-01 (Public Directory)**: Filterable, searchable directory of ITER faculty profiles with search by name, department filter, and research keyword tags.
- **FR-PUBUI-02 (Scholar-Style Profile)**:
  - Distinguished academic header with verified badges, department, contact info, and social/academic links.
  - Google Scholar-style metrics sidebar (Total Citations, h-index, i10-index, publication chart).
  - Tabbed sections: Publications (with search and year filters), Research Projects, Patents, Teaching, Awards, and Biography.
- **FR-PUBUI-03 (Export & Sharing)**: One-click print/export view for accreditation (NAAC/NIRF) or CV sharing.

---

## Non-Functional Requirements (NFR)
- **NFR-SEC-01 (Security)**: All database queries must use PDO prepared statements. Zero raw string concatenation in SQL queries.
- **NFR-SEC-02 (XSS Defense)**: All user-generated content displayed in templates must be escaped with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **NFR-SEC-03 (CSRF Defense)**: Anti-CSRF tokens generated per session and validated on state-changing requests.
- **NFR-SEC-04 (File Upload Security)**: Uploaded photos and documents must validate MIME type, file extension whitelist (`jpg`, `jpeg`, `png`, `webp`, `pdf`), file size limit (max 2MB), and store files outside public PHP execution.
- **NFR-UI-01 (Design Standards)**: No generic "AI slop" or cookie-cutter templates. Crisp contrast, academic serif/sans-serif typography, clean badges, subtle borders, and intuitive mobile responsiveness.
- **NFR-PERF-01 (Speed)**: Page loads on local Apache under 100ms. Database indexes on foreign keys and search columns.

---

## Requirement Traceability Matrix
| Req ID | Component | Target Phase | Status |
| :--- | :--- | :--- | :--- |
| FR-AUTH-01 to 04 | Auth & RBAC Core | Phase 1 | Planned |
| FR-DEPT-01 to 02 | Department Schema & Seeding | Phase 1 | Planned |
| NFR-SEC-01 to 04 | Security Architecture & Helpers | Phase 1 | Planned |
| FR-PROF-01 to 05 | Comprehensive Profile Management | Phase 2 | Planned |
| FR-PUB-01 to 02 | Publications & BibTeX Export | Phase 2 | Planned |
| FR-PROJ, PAT, AWD, TEACH | Research & Academic Extensions | Phase 2 | Planned |
| FR-DEL-01 to 04 | Assistant Delegation System | Phase 3 | Planned |
| FR-PUBUI-01 to 03 | Public Directory & Academic UI | Phase 4 | Planned |
| NFR-UI-01, PERF-01 | Polish, Search, Filter & Audit | Phase 5 | Planned |
