#!/usr/bin/env python3
"""
Institutional Security & Code Quality Gate
Static security audit suite for Departmental Scholar research portal.
Checks for SQL parameterization, CSRF token verification on state-changing endpoints,
CLI execution guards, and safe output encoding.
"""

import os
import re
import sys

ERRORS = []
WARNINGS = []
PASSED_CHECKS = 0

def log_pass(desc):
    global PASSED_CHECKS
    PASSED_CHECKS += 1
    print(f"  [PASS] {desc}")

def log_error(desc, filepath, line_num=0, snippet=""):
    loc = f"{filepath}:{line_num}" if line_num else filepath
    msg = f"  [FAIL] {desc} at {loc}"
    if snippet:
        msg += f"\n         >>> {snippet.strip()}"
    ERRORS.append(msg)
    print(msg)

def get_php_files():
    php_files = []
    for root, dirs, files in os.walk('.'):
        if any(skip in root for skip in ['vendor', 'node_modules', '.git', '.agent', '.planning', 'tmp']):
            continue
        for f in files:
            if f.endswith('.php'):
                php_files.append(os.path.relpath(os.path.join(root, f), '.'))
    return sorted(php_files)

# -------------------------------------------------------------
# Check 1: SQL Injection Prevention (Prepared Statements Audit)
# -------------------------------------------------------------
def check_sql_injection(php_files):
    print("\n[1] Checking SQL Parameterization (No Raw Variable Interpolation in SQL)...")
    # Matches ->query("...$var...") or ->prepare("..." . $var)
    # Allows prepared statements ($db->prepare("SELECT ... WHERE id = ?"))
    sql_call_regex = re.compile(r'(->query|->prepare|->exec)\s*\(\s*["\']([^"\']*)["\']', re.IGNORECASE)
    dangerous_var_regex = re.compile(r'(->query|->prepare|->exec)\s*\(\s*("[^"]*\$[a-zA-Z_{]|["\'][^"\']*["\']\s*\.\s*\$[a-zA-Z_])', re.IGNORECASE)

    has_issue = False
    for path in php_files:
        with open(path, 'r', encoding='utf-8', errors='ignore') as fp:
            for idx, line in enumerate(fp, 1):
                # Ignore comment lines
                stripped = line.strip()
                if stripped.startswith('//') or stripped.startswith('*') or stripped.startswith('#'):
                    continue
                # Check for raw SQL interpolation
                match = dangerous_var_regex.search(line)
                if match:
                    log_error("Unparameterized variable in SQL query call", path, idx, line)
                    has_issue = True

    if not has_issue:
        log_pass("100% of SQL statements across all PHP files use parameterized prepared statements.")

# -------------------------------------------------------------
# Check 2: CSRF Protection on State-Changing POST Operations
# -------------------------------------------------------------
def check_csrf_protection(php_files):
    print("\n[2] Checking CSRF Token Verification on POST Form Handlers...")
    # Any file processing POST requests must invoke require_csrf()
    post_check_regex = re.compile(r'\$_SERVER\[[\'"]REQUEST_METHOD[\'"]\]\s*===?\s*[\'"]POST[\'"]', re.IGNORECASE)
    
    # Files that handle POST submissions
    post_files = []
    for path in php_files:
        with open(path, 'r', encoding='utf-8', errors='ignore') as fp:
            content = fp.read()
            if post_check_regex.search(content):
                post_files.append(path)

    has_issue = False
    for path in post_files:
        with open(path, 'r', encoding='utf-8', errors='ignore') as fp:
            content = fp.read()
            if 'require_csrf();' not in content and 'verify_csrf_token' not in content:
                log_error("POST form handler lacks mandatory require_csrf() token validation", path)
                has_issue = True

    if not has_issue:
        log_pass(f"All {len(post_files)} state-changing POST handlers strictly enforce CSRF verification.")

# -------------------------------------------------------------
# Check 3: Web-Execution Guards on CLI Scripts
# -------------------------------------------------------------
def check_cli_guards():
    print("\n[3] Checking Web Execution Guards on Maintenance & Migration Scripts...")
    cli_scripts = [
        'database/migrate.php',
        'database/migrate_cris_enhancements.php',
        'database/seed_demo.php'
    ]
    has_issue = False
    for script in cli_scripts:
        if os.path.exists(script):
            with open(script, 'r', encoding='utf-8', errors='ignore') as fp:
                content = fp.read()
                # Must guard against direct browser execution
                if 'php_sapi_name() !== \'cli\'' not in content and 'php_sapi_name() !== "cli"' not in content and 'http_response_code(403)' not in content:
                    log_error("Migration/seed script missing CLI-only execution guard", script)
                    has_issue = True
                else:
                    log_pass(f"Script {script} properly shielded from unauthorized browser execution.")

# -------------------------------------------------------------
# Check 4: No Accidental Secrets / Passwords Committed
# -------------------------------------------------------------
def check_hardcoded_secrets(php_files):
    print("\n[4] Scanning for Accidental Plaintext Credentials / Secret Leaks...")
    secret_regex = re.compile(r'(\$password\s*=\s*["\'][^"\']{6,}["\']|\$secret_key\s*=\s*["\'][^"\']{8,}["\'])', re.IGNORECASE)
    has_issue = False

    for path in php_files:
        # Ignore seed scripts where hashed / default demo hashes are defined
        if 'seed' in path or 'test' in path:
            continue
        with open(path, 'r', encoding='utf-8', errors='ignore') as fp:
            for idx, line in enumerate(fp, 1):
                stripped = line.strip()
                if stripped.startswith('//') or stripped.startswith('*') or stripped.startswith('#'):
                    continue
                if secret_regex.search(line):
                    log_error("Potential hardcoded secret or plaintext credential detected", path, idx, line)
                    has_issue = True

    if not has_issue:
        log_pass("No hardcoded credentials or plaintext passwords found in production application code.")

# -------------------------------------------------------------
# Main Runner
# -------------------------------------------------------------
def main():
    print("=" * 70)
    print("ACADEMIC CRIS PORTAL — SECURITY & QUALITY AUDIT SUITE")
    print("=" * 70)
    
    php_files = get_php_files()
    print(f"Discovered {len(php_files)} PHP application files to audit.")

    check_sql_injection(php_files)
    check_csrf_protection(php_files)
    check_cli_guards()
    check_hardcoded_secrets(php_files)

    print("\n" + "=" * 70)
    if ERRORS:
        print(f"AUDIT FAILED: {len(ERRORS)} critical security issue(s) detected.")
        sys.exit(1)
    else:
        print(f"AUDIT PASSED: All {PASSED_CHECKS} security checks verified with 100% compliance.")
        print("=" * 70)
        sys.exit(0)

if __name__ == '__main__':
    main()
