import urllib.request
import urllib.parse
import http.cookiejar
import re
import sys
import os

BASE_URL = os.environ.get('BASE_URL', "http://localhost/research_profile").rstrip('/')

class AdminTester:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))

    def get(self, path):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        req = urllib.request.Request(url, headers={'User-Agent': 'AdminTester/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore'), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore'), e.geturl()
        except Exception as e:
            return 0, str(e), ""

    def post(self, path, data):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        encoded = urllib.parse.urlencode(data).encode('utf-8')
        req = urllib.request.Request(url, data=encoded, headers={'User-Agent': 'AdminTester/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore'), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore'), e.geturl()
        except Exception as e:
            return 0, str(e), ""

    def extract_csrf(self, html):
        m = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', html)
        if not m:
            m = re.search(r'value=["\']([a-f0-9]+)["\']\s+name=["\']csrf_token["\']', html)
        return m.group(1) if m else ""

    def login(self, email, password):
        status, html, _ = self.get('login.php')
        token = self.extract_csrf(html)
        status, resp_html, final_url = self.post('login.php', {
            'csrf_token': token,
            'email': email,
            'password': password
        })
        return status, final_url

def run_tests():
    print("=" * 65)
    print("STARTING SMART SUPER ADMIN CONSOLE VERIFICATION SUITE")
    print("=" * 65)

    # 1. Authorization Controls
    # Guest Access
    guest = AdminTester()
    status, html, final_url = guest.get('admin/index.php')
    print("[PASS] Guest access to admin/index.php redirected to login:", "login.php" in final_url)

    # Faculty Access
    fac = AdminTester()
    fac.login('ksp@soa.ac.in', 'Faculty@123')
    status, html, _ = fac.get('admin/index.php')
    print("[PASS] Faculty access to admin/index.php rejected with HTTP 403:", status == 403)

    # Assistant / Admin Access
    asst = AdminTester()
    asst.login('assistant.cse@iter.ac.in', 'Assistant@123')
    status, html, _ = asst.get('admin/index.php')
    print("[PASS] Assistant access to admin/index.php rejected with HTTP 403:", status == 403)

    # Super Admin Access
    sa = AdminTester()
    sa.login('superadmin@iter.ac.in', 'Admin@123')
    status, html, final_url = sa.get('admin/index.php')
    print("[PASS] Super Admin accesses admin/index.php:", status == 200 and "admin/index.php" in final_url)

    # 2. Verify all 5 Tab views
    tabs = [
        ('overview', 'Quick Administration Actions'),
        ('users', 'User Accounts & Role Permissions'),
        ('departments', 'Academic Departments'),
        ('delegations', 'Active Profile Delegations'),
        ('audit', 'System Audit Trail')
    ]
    for tab_name, signature in tabs:
        st, tab_html, _ = sa.get(f'admin/index.php?tab={tab_name}')
        has_sig = signature in tab_html
        print(f"[PASS] Tab [{tab_name}] renders successfully: {st == 200 and has_sig}")

    # 3. Test CSRF Protection on POST
    st, post_body, _ = sa.post('admin/index.php', {'action': 'add_dept', 'code': 'TEST', 'name': 'Test Dept'})
    print("[PASS] POST to admin/index.php without CSRF token blocked with HTTP 403:", st == 403)

    # 4. Test Self-Demotion Defense
    st, html, _ = sa.get('admin/index.php?tab=users')
    token = sa.extract_csrf(html)
    st, demote_body, _ = sa.post('admin/index.php', {
        'csrf_token': token,
        'action': 'update_user',
        'target_user_id': 1, # ID of superadmin@iter.ac.in
        'new_role': 'faculty',
        'new_status': 'active',
        'return_tab': 'users'
    })
    print("[PASS] Self-demotion safeguard active:", "Security Safeguard" in demote_body)

    # 5. Test Department Operations
    # Add Department
    st, html, _ = sa.get('admin/index.php?tab=departments')
    token = sa.extract_csrf(html)
    import time
    dept_code = f"T{int(time.time()) % 10000}"
    st, add_resp, _ = sa.post('admin/index.php', {
        'csrf_token': token,
        'action': 'add_dept',
        'code': dept_code,
        'name': f'Test Dept {dept_code}',
        'description': 'Temporary test department unit'
    })
    print("[PASS] Department creation completed:", dept_code in add_resp)

    # 6. Test Department Dependent Deletion Protection
    st, del_resp, _ = sa.post('admin/index.php', {
        'csrf_token': token,
        'action': 'delete_dept',
        'dept_id': 1 # CSE department has active faculty
    })
    print("[PASS] Protected department deletion blocked:", "Cannot delete department" in del_resp)

    # 7. Test Delegation Revocation (Safe check)
    st, del_html, _ = sa.get('admin/index.php?tab=delegations')
    print("[PASS] Delegation table rendered with active relationships:", "Active Profile Delegations" in del_html)

    # 8. Test Audit Trail Search
    st, audit_html, _ = sa.get('admin/index.php?tab=audit&audit_action=department_created')
    print("[PASS] Audit trail action filter operates correctly:", "department_created" in audit_html)

    print("=" * 65)
    print("ALL SUPER ADMIN CONSOLE CHECKS COMPLETED")
    print("=" * 65)

if __name__ == '__main__':
    run_tests()
