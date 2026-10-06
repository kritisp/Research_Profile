import urllib.request
import urllib.parse
import http.cookiejar
import json
import re
import subprocess
import sys
import time
import os

BASE_URL = os.environ.get('BASE_URL', "http://localhost/research_profile").rstrip('/')

class NoRedirectHandler(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class SecurityTester:
    def __init__(self, follow_redirects=True):
        self.cj = http.cookiejar.CookieJar()
        handlers = [urllib.request.HTTPCookieProcessor(self.cj)]
        if not follow_redirects:
            handlers.append(NoRedirectHandler())
        self.opener = urllib.request.build_opener(*handlers)
        self.results = []

    def log_result(self, test_name, passed, details=""):
        status = "PASS" if passed else "FAIL"
        self.results.append((test_name, passed, details))
        print(f"[{status}] {test_name}: {details}")

    def get(self, path):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        req = urllib.request.Request(url, headers={'User-Agent': 'SecurityAuditBot/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore'), resp.headers
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore'), e.headers
        except Exception as e:
            return 0, str(e), {}

    def post(self, path, data):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        encoded_data = urllib.parse.urlencode(data).encode('utf-8')
        req = urllib.request.Request(url, data=encoded_data, headers={'User-Agent': 'SecurityAuditBot/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore'), resp.headers
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore'), e.headers
        except Exception as e:
            return 0, str(e), {}

    def extract_csrf(self, html):
        match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', html)
        if match:
            return match.group(1)
        match = re.search(r'value=["\']([a-f0-9]+)["\']\s+name=["\']csrf_token["\']', html)
        return match.group(1) if match else ""

import shutil
import secrets

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PHP_BIN = os.environ.get("PHP_BINARY") or shutil.which("php") or r"C:\xampp\php\php.exe"

def run_php(code):
    temp_file = os.path.join(ROOT_DIR, f'temp_audit_{secrets.token_hex(4)}.php')
    with open(temp_file, 'w', encoding='utf-8') as f:
        f.write("<?php " + code)
    try:
        res = subprocess.run([PHP_BIN, temp_file], cwd=ROOT_DIR, capture_output=True, text=True, env=os.environ)
        return res.stdout.strip(), res.stderr.strip()
    finally:
        if os.path.exists(temp_file):
            try:
                os.remove(temp_file)
            except OSError:
                pass

def run_tests():
    tester = SecurityTester(follow_redirects=True)
    print("=" * 65)
    print("STARTING END-TO-END SECURITY AUDIT & VERIFICATION SUITE")
    print("=" * 65)

    # 1. Test Seed & Migration CLI-only Protection against direct HTTP execution
    status1, body1, _ = tester.get('database/migrate.php')
    passed1 = status1 in [200, 403] and ("This script can only be run from the command-line" in body1 or len(body1.strip()) < 100)
    tester.log_result("CLI Guard: database/migrate.php blocked from web", passed1, f"HTTP {status1}, prevented direct web execution")

    status2, body2, _ = tester.get('database/seed_demo.php')
    passed2 = status2 in [200, 403] and ("This script can only be run from the command-line" in body2 or len(body2.strip()) < 100)
    tester.log_result("CLI Guard: database/seed_demo.php blocked from web", passed2, f"HTTP {status2}, prevented direct web execution")

    # 2. Test Login Page for default credentials & sensitive exposure
    status, login_html, _ = tester.get('login.php')
    has_demo_card = "AdminPassword@123" in login_html or "superadmin@iter.ac.in" in login_html or "super@admin.com" in login_html or "Demo Credentials" in login_html
    tester.log_result("Default Credentials Exposure check in login.php", not has_demo_card, "No demo credentials cards or hardcoded passwords exposed")

    # 3. Test CSRF Protection Enforcement on public POST without token
    status, body, _ = tester.post('register.php', {'full_name': 'Test CSRF Hacker'})
    passed_csrf = (status == 403 and "CSRF security token" in body)
    tester.log_result("CSRF Protection: POST to register.php without token", passed_csrf, f"Returned HTTP {status} with CSRF rejection")

    # 4. Test Registration Privilege Escalation Prevention
    status, reg_html, _ = tester.get('register.php')
    reg_csrf = tester.extract_csrf(reg_html)
    timestamp = int(time.time())
    test_email = f"audit_faculty_{timestamp}@iter.ac.in"
    reg_data = {
        'csrf_token': reg_csrf,
        'full_name': 'Dr. Security Escalation Test',
        'email': test_email,
        'department_id': 1,
        'institution': 'ITER, SOA University',
        'password': 'SecureFacultyPassword@2026',
        'password_confirm': 'SecureFacultyPassword@2026',
        'role': 'super_admin' # Maliciously attempting privilege escalation!
    }
    status, reg_post_resp, _ = tester.post('register.php', reg_data)
    
    # Query database to verify role
    php_code = f"require_once 'config/database.php'; $db = Database::getConnection(); $stmt = $db->prepare('SELECT role, status FROM users WHERE email = ?'); $stmt->execute(['{test_email}']); echo json_encode($stmt->fetch());"
    stdout, _ = run_php(php_code)
    user_record = json.loads(stdout) if stdout else None
    passed_priv_esc = user_record and user_record.get('role') == 'faculty'
    tester.log_result("Privilege Escalation Defense: POST role='super_admin' on registration", passed_priv_esc, 
                      f"User created with role='{user_record.get('role') if user_record else 'NONE'}' (strictly forced to faculty)")

    # 5. Test Inactive User Blocking
    # Log out of registration session first so tester is an unauthenticated guest
    tester.get('logout.php')
    # Mark the newly created user as inactive
    php_code = f"require_once 'config/database.php'; $db = Database::getConnection(); $db->prepare(\"UPDATE users SET status = 'inactive' WHERE email = ?\")->execute(['{test_email}']);"
    run_php(php_code)
    
    # Attempt login with inactive account
    status, login_html, _ = tester.get('login.php')
    login_csrf = tester.extract_csrf(login_html)
    status, inactive_login_resp, _ = tester.post('login.php', {
        'csrf_token': login_csrf,
        'email': test_email,
        'password': 'SecureFacultyPassword@2026'
    })
    passed_inactive = ("account is currently inactive" in inactive_login_resp or "account is inactive" in inactive_login_resp)
    tester.log_result("Authentication: Inactive User Blocking on login", passed_inactive, "Inactive account login rejected with proper notice")

    # Reactivate the test user for session and IDOR testing
    php_code = f"require_once 'config/database.php'; $db = Database::getConnection(); $db->prepare(\"UPDATE users SET status = 'active' WHERE email = ?\")->execute(['{test_email}']);"
    run_php(php_code)

    # 6. Test Login & Session Generation
    status, login_html, _ = tester.get('login.php')
    login_csrf = tester.extract_csrf(login_html)
    status, active_login_resp, _ = tester.post('login.php', {
        'csrf_token': login_csrf,
        'email': test_email,
        'password': 'SecureFacultyPassword@2026'
    })
    # Verify user is now logged in by accessing dashboard
    status_dash, body_dash, _ = tester.get('dashboard/index.php')
    passed_login = status_dash == 200 and ("Faculty Dashboard" in body_dash or "Dr. Security Escalation Test" in body_dash)
    tester.log_result("Authentication: Login & Session Establishment", passed_login, "Successfully authenticated and established secure session")

    # 7. Test IDOR Protection on Cross-Profile Publication Deletion
    # Find a publication belonging to a DIFFERENT faculty member (e.g. Profile 1)
    php_code = "require_once 'config/database.php'; $db = Database::getConnection(); echo $db->query('SELECT id FROM publications LIMIT 1')->fetchColumn();"
    pub_id, _ = run_php(php_code)
    pub_id = int(pub_id) if pub_id else 1
    
    dash_csrf = tester.extract_csrf(body_dash)
    # Logged in as test user (who does NOT own publication pub_id), attempt to delete it
    status_del, body_del, _ = tester.post('dashboard/delete_item.php', {
        'csrf_token': dash_csrf,
        'type': 'publication',
        'id': pub_id
    })
    # Check that publication pub_id still exists in database!
    php_code = f"require_once 'config/database.php'; $db = Database::getConnection(); echo $db->query('SELECT COUNT(*) FROM publications WHERE id = {pub_id}')->fetchColumn();"
    count_after, _ = run_php(php_code)
    passed_idor = (int(count_after) == 1)
    tester.log_result("IDOR Defense: Cross-Profile publication delete blocked", passed_idor, 
                      f"Target publication id={pub_id} was preserved in database; unauthorized deletion blocked")

    # 8. Test Clean Slugs & URL Rewriting
    status_slug, body_slug, _ = tester.get('researchers/dr-debabrata-singh')
    passed_slug = status_slug == 200 and ("Debabrata Singh" in body_slug or "Associate Professor" in body_slug)
    tester.log_result("Clean Researcher URL: /researchers/dr-debabrata-singh", passed_slug, f"HTTP {status_slug} with profile content verified")

    status_slug_param, body_slug_param, _ = tester.get('profile.php?slug=dr-debabrata-singh')
    passed_slug_param = status_slug_param == 200 and "Debabrata Singh" in body_slug_param
    tester.log_result("Slug Query URL: profile.php?slug=dr-debabrata-singh", passed_slug_param, f"HTTP {status_slug_param} with profile content verified")

    status_id, body_id, _ = tester.get('profile.php?id=1')
    passed_id = status_id == 200 and ("Debabrata Singh" in body_id or "Faculty Profile" in body_id)
    tester.log_result("Backward Compatibility: profile.php?id=1", passed_id, f"HTTP {status_id} with profile content verified")

    # 9. Test Error Handling & Non-existent Profile Safe 404
    status_404, body_404, _ = tester.get('profile.php?slug=non-existent-faculty-profile-test')
    passed_404 = status_404 == 404 and "could not be found" in body_404 and "PDOException" not in body_404 and "SQLSTATE" not in body_404
    tester.log_result("Error Handling: Non-existent profile 404", passed_404, f"HTTP {status_404} with safe branded error and no SQL leaks")

    # 10. Test Public Navigation: No Admin/Assistant links for guests
    # Logout first
    tester.get('logout.php')
    status_home, body_home, _ = tester.get('')
    has_admin_in_nav = "admin/index.php" in body_home or "assistant/index.php" in body_home
    tester.log_result("Public Navigation Cleansing: No admin portals exposed to guests", not has_admin_in_nav, "No administrative console links in public guest navigation")

    # 11. Test Academic Profile Metrics Transparency
    has_metrics_notice = ("Self-reported" in body_slug or "Last updated" in body_slug or "Self-reported" in body_slug_param or "Last updated" in body_slug_param)
    has_fake_calc = "* 0.72" in body_slug or "* 0.85" in body_slug or "* 0.72" in body_slug_param
    tester.log_result("Citation Metrics Transparency: Explicitly self-reported with timestamps", has_metrics_notice and not has_fake_calc, "Metrics marked as self-reported without fabricated multipliers")

    # Clean up test user
    cleanup_php = f"require_once 'config/database.php'; $db = Database::getConnection(); $db->prepare('DELETE FROM users WHERE email = ?')->execute(['{test_email}']);"
    run_php(cleanup_php)

    print("=" * 65)
    all_passed = all(p for _, p, _ in tester.results)
    print(f"OVERALL SUITE RESULT: {'ALL TESTS PASSED' if all_passed else 'SOME TESTS FAILED'}")
    print("=" * 65)
    return all_passed

if __name__ == '__main__':
    success = run_tests()
    sys.exit(0 if success else 1)
