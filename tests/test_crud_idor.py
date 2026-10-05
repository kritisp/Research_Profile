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

class SecurityTester:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))

    def get(self, path):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        req = urllib.request.Request(url, headers={'User-Agent': 'SecurityAuditBot/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore')
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore')
        except Exception as e:
            return 0, str(e)

    def post(self, path, data):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        encoded_data = urllib.parse.urlencode(data).encode('utf-8')
        req = urllib.request.Request(url, data=encoded_data, headers={'User-Agent': 'SecurityAuditBot/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore')
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore')
        except Exception as e:
            return 0, str(e)

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
    temp_file = os.path.join(ROOT_DIR, f'temp_crud_{secrets.token_hex(4)}.php')
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

def run_crud_idor_tests():
    print("=" * 65)
    print("STARTING ADVANCED CRUD & IDOR TEST SUITE")
    print("=" * 65)
    tester = SecurityTester()

    # 1. Create a Faculty A user and Faculty B user
    timestamp = int(time.time())
    email_a = f"faculty_a_{timestamp}@iter.ac.in"
    email_b = f"faculty_b_{timestamp}@iter.ac.in"
    password = "ComplexPassword@123"

    php_setup = f"""
    require_once 'config/database.php';
    $db = Database::getConnection();
    $hash = password_hash('{password}', PASSWORD_DEFAULT);
    $deptId = (int)$db->query("SELECT id FROM departments LIMIT 1")->fetchColumn();
    
    // User A
    $uStmt = $db->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'faculty', 'active')");
    $uStmt->execute(['Faculty A', '{email_a}', $hash]);
    $uid_a = (int)$db->lastInsertId();

    $pStmt = $db->prepare("INSERT INTO faculty_profiles (user_id, department_id, slug, is_verified) VALUES (?, ?, ?, 1)");
    $pStmt->execute([$uid_a, $deptId, 'faculty-a-{timestamp}']);
    $prof_a = (int)$db->lastInsertId();

    // User B
    $uStmt->execute(['Faculty B', '{email_b}', $hash]);
    $uid_b = (int)$db->lastInsertId();

    $pStmt->execute([$uid_b, $deptId, 'faculty-b-{timestamp}']);
    $prof_b = (int)$db->lastInsertId();

    // Create records belonging to Faculty B
    $prStmt = $db->prepare("INSERT INTO projects (faculty_profile_id, title, funding_agency) VALUES (?, 'Faculty B Project', 'DST')");
    $prStmt->execute([$prof_b]);
    $proj_b = (int)$db->lastInsertId();

    $ptStmt = $db->prepare("INSERT INTO patents (faculty_profile_id, title) VALUES (?, 'Faculty B Patent')");
    $ptStmt->execute([$prof_b]);
    $pat_b = (int)$db->lastInsertId();

    $awStmt = $db->prepare("INSERT INTO awards (faculty_profile_id, title) VALUES (?, 'Faculty B Award')");
    $awStmt->execute([$prof_b]);
    $award_b = (int)$db->lastInsertId();

    $edStmt = $db->prepare("INSERT INTO education (faculty_profile_id, degree, institution) VALUES (?, 'Ph.D.', 'IIT')");
    $edStmt->execute([$prof_b]);
    $edu_b = (int)$db->lastInsertId();

    $tcStmt = $db->prepare("INSERT INTO teaching (faculty_profile_id, course_title) VALUES (?, 'Distributed Systems')");
    $tcStmt->execute([$prof_b]);
    $teach_b = (int)$db->lastInsertId();

    $exStmt = $db->prepare("INSERT INTO academic_experience (faculty_profile_id, position_title, organization, start_year) VALUES (?, 'Visiting Scientist', 'MIT', 2022)");
    $exStmt->execute([$prof_b]);
    $exp_b = (int)$db->lastInsertId();

    echo json_encode([
        'uid_a' => $uid_a, 'prof_a' => $prof_a,
        'uid_b' => $uid_b, 'prof_b' => $prof_b,
        'proj_b' => $proj_b, 'pat_b' => $pat_b,
        'award_b' => $award_b, 'edu_b' => $edu_b,
        'teach_b' => $teach_b, 'exp_b' => $exp_b
    ]);
    """
    stdout, stderr = run_php(php_setup)
    try:
        data = json.loads(stdout)
    except Exception as e:
        print("EXCEPTION:", e)
        print("STDOUT:", stdout)
        print("STDERR:", stderr)
        raise e
    prof_a = data['prof_a']
    prof_b = data['prof_b']
    proj_b = data['proj_b']
    pat_b = data['pat_b']
    award_b = data['award_b']
    edu_b = data['edu_b']
    teach_b = data['teach_b']
    exp_b = data['exp_b']

    # 2. Login as Faculty A
    status, login_html = tester.get('login.php')
    login_csrf = tester.extract_csrf(login_html)
    st_log, body_log = tester.post('login.php', {
        'csrf_token': login_csrf,
        'email': email_a,
        'password': password
    })

    # Get CSRF from dashboard
    status, dash_html = tester.get('dashboard/index.php')
    dash_csrf = tester.extract_csrf(dash_html)

    # 3. Faculty A attempts IDOR delete on Faculty B's project
    tester.post('dashboard/delete_item.php', {'csrf_token': dash_csrf, 'type': 'project', 'id': proj_b})
    # 4. Faculty A attempts IDOR delete on Faculty B's patent
    tester.post('dashboard/delete_item.php', {'csrf_token': dash_csrf, 'type': 'patent', 'id': pat_b})
    # 5. Faculty A attempts IDOR delete on Faculty B's award
    tester.post('dashboard/delete_item.php', {'csrf_token': dash_csrf, 'type': 'award', 'id': award_b})
    # 6. Faculty A attempts IDOR delete on Faculty B's education
    tester.post('dashboard/delete_item.php', {'csrf_token': dash_csrf, 'type': 'education', 'id': edu_b})
    # 7. Faculty A attempts IDOR delete on Faculty B's teaching
    tester.post('dashboard/delete_item.php', {'csrf_token': dash_csrf, 'type': 'teaching', 'id': teach_b})
    # 8. Faculty A attempts IDOR delete on Faculty B's experience/appointment
    tester.post('dashboard/delete_item.php', {'csrf_token': dash_csrf, 'type': 'experience', 'id': exp_b})

    # 9. Faculty A attempts IDOR edit on Faculty B's profile
    status_edit, body_edit = tester.get(f'dashboard/edit_profile.php?profile_id={prof_b}')
    passed_edit_profile = (status_edit == 403 or "Unauthorized" in body_edit)

    # 10. Faculty A attempts IDOR add publication to Faculty B's profile
    status_add_pub, body_add_pub = tester.get(f'dashboard/add_publication.php?profile_id={prof_b}')
    passed_add_pub = (status_add_pub == 403 or "Unauthorized" in body_add_pub)

    # 11. Faculty A attempts IDOR add project to Faculty B's profile
    status_add_proj, body_add_proj = tester.get(f'dashboard/add_project.php?profile_id={prof_b}')
    passed_add_proj = (status_add_proj == 403 or "Unauthorized" in body_add_proj)

    # 12. Faculty A attempts IDOR add patent to Faculty B's profile
    status_add_pat, body_add_pat = tester.get(f'dashboard/add_patent.php?profile_id={prof_b}')
    passed_add_pat = (status_add_pat == 403 or "Unauthorized" in body_add_pat)

    # 13. Faculty A attempts IDOR add appointment to Faculty B's profile
    status_add_app, body_add_app = tester.get(f'dashboard/add_appointment.php?profile_id={prof_b}')
    passed_add_app = (status_add_app == 403 or "Unauthorized" in body_add_app)

    # Verify that all Faculty B records are STILL in database!
    php_verify = f"""
    require_once 'config/database.php';
    $db = Database::getConnection();
    $proj_exists = (int)$db->query("SELECT COUNT(*) FROM projects WHERE id = {proj_b}")->fetchColumn();
    $pat_exists = (int)$db->query("SELECT COUNT(*) FROM patents WHERE id = {pat_b}")->fetchColumn();
    $award_exists = (int)$db->query("SELECT COUNT(*) FROM awards WHERE id = {award_b}")->fetchColumn();
    $edu_exists = (int)$db->query("SELECT COUNT(*) FROM education WHERE id = {edu_b}")->fetchColumn();
    $teach_exists = (int)$db->query("SELECT COUNT(*) FROM teaching WHERE id = {teach_b}")->fetchColumn();
    $exp_exists = (int)$db->query("SELECT COUNT(*) FROM academic_experience WHERE id = {exp_b}")->fetchColumn();
    echo json_encode([
        'proj' => $proj_exists, 'pat' => $pat_exists,
        'award' => $award_exists, 'edu' => $edu_exists,
        'teach' => $teach_exists, 'exp' => $exp_exists
    ]);
    """
    stdout, _ = run_php(php_verify)
    results = json.loads(stdout)

    print(f"Project preserved: {results['proj'] == 1}")
    print(f"Patent preserved: {results['pat'] == 1}")
    print(f"Award preserved: {results['award'] == 1}")
    print(f"Education preserved: {results['edu'] == 1}")
    print(f"Teaching preserved: {results['teach'] == 1}")
    print(f"Experience preserved: {results['exp'] == 1}")
    print(f"Edit Profile IDOR Blocked: {passed_edit_profile}")
    print(f"Add Publication IDOR Blocked: {passed_add_pub}")
    print(f"Add Project IDOR Blocked: {passed_add_proj}")
    print(f"Add Patent IDOR Blocked: {passed_add_pat}")
    print(f"Add Appointment IDOR Blocked: {passed_add_app}")

    # Cleanup
    php_cleanup = f"""
    require_once 'config/database.php';
    $db = Database::getConnection();
    $db->prepare("DELETE FROM users WHERE email IN ('{email_a}', '{email_b}')")->execute();
    """
    run_php(php_cleanup)

    all_passed = (
        results['proj'] == 1 and
        results['pat'] == 1 and
        results['award'] == 1 and
        results['edu'] == 1 and
        results['teach'] == 1 and
        results['exp'] == 1 and
        passed_edit_profile and
        passed_add_pub and
        passed_add_proj and
        passed_add_pat and
        passed_add_app
    )
    print("=" * 65)
    print(f"CRUD IDOR VERIFICATION RESULT: {'ALL PASS' if all_passed else 'FAIL'}")
    print("=" * 65)
    return all_passed

if __name__ == '__main__':
    ok = run_crud_idor_tests()
    sys.exit(0 if ok else 1)
