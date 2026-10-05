# Phase 4 Plan: Institutional Preloader & Visual Experience

## Objective
Design and implement an academic-grade, atmospheric splash preloader for the ITER Research Portal landing page that:
1. Avoids plain/stark white blinding backgrounds in favor of ITER's prestigious deep academic navy (`#0a0f1d` / `#0b1120`).
2. Subtly incorporates the official SOA University seal's color palette (Crimson `#e11d48` and Emerald `#10b981`).
3. Delivers a distinct, active, and silky-smooth loading animation (rotating orbital ring, real percentage progression counter `0% -> 100%`, and stage text).
4. Guarantees seamless dismissal (zero hanging screens, smooth CSS opacity/blur fade, and clean DOM unmounting).

---

## Architectural Breakdown

### 1. Visual Hierarchy & Theme
- **Backdrop**: Deep midnight navy `#080d1a` with gentle radial gradient and subtle ambient light.
- **Centerpiece**: Official circular SOA University seal (`assets/img/soa_logo.png`) housed in a crisp circular pedestal.
- **Orbital Animation**: A rotating dual-gradient orbital halo ring (`spin` at 6s) around the logo with crimson & emerald glow.
- **Typography**:
  - Primary: "SIKSHA 'O' ANUSANDHAN" (Letter-spaced serif, crisp white).
  - Secondary: "(DEEMED TO BE UNIVERSITY) • BHUBANESWAR" (Font-mono, slate-400).
  - Division: "Institute of Technical Education and Research (ITER)" (Amber/Gold accent).

### 2. Loading State Machine (Clear & Smooth UX)
- **Progress Track**: Sleek 240px illuminated track with dual-color crimson-to-emerald gradient fill.
- **Percentage Counter**: Clear font-mono number incrementing smoothly from `0%` to `100%`.
- **Dynamic Status Messages**:
  - `0% - 35%`: *Connecting to Scholarly Database...*
  - `36% - 70%`: *Indexing ITER Faculty Profiles...*
  - `71% - 99%`: *Loading Research Publications...*
  - `100%`: *Welcome to ITER Research Portal*
- **Exit Dissolve**:
  - Smooth transition: `opacity: 0`, `filter: blur(6px)`, `transform: scale(1.02)`.
  - Execution on `window.onload` with an intentional 1.2s minimum window to allow the visual identity to breathe, followed by guaranteed DOM removal.
  - Fail-safe timeout at 2.8s ensuring no visitor is ever blocked.

---

## Verification Checklist
- [ ] No blank white screen; dark academic depth preserved.
- [ ] Active and visible loading progress (percentage + status + rotating ring).
- [ ] Smooth dissolve transition out of the viewport.
- [ ] Zero blocking or freezing in any browser state.
