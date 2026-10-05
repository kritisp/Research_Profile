import urllib.request
import urllib.parse
import http.cookiejar
import re
import sys

BASE_URL = "http://localhost/research_profile"

class NoRedirectHandler(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class SwitchWorkflowTester:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))

    def get(self, path):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        req = urllib.request.Request(url, headers={'User-Agent': 'SwitchAuditBot/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore'), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore'), e.geturl()
        except Exception as e:
            return 0, str(e), ""

    def post(self, path, data):
        url = f"{BASE_URL}/{path.lstrip('/')}"
        encoded_data = urllib.parse.urlencode(data).encode('utf-8')
        req = urllib.request.Request(url, data=encoded_data, headers={'User-Agent': 'SwitchAuditBot/1.0'})
        try:
            with self.opener.open(req) as resp:
                return resp.status, resp.read().decode('utf-8', errors='ignore'), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf-8', errors='ignore'), e.geturl()
        except Exception as e:
            return 0, str(e), ""

    def extract_csrf(self, html):
        match = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', html)
        if match:
            return match.group(1)
        match = re.search(r'value=["\']([a-f0-9]+)["\']\s+name=["\']csrf_token["\']', html)
        return match.group(1) if match else ""

def test_switch():
    print("=" * 65)
    print("STARTING PROFILE SWITCH & DELEGATE WORKFLOW TEST")
    print("=" * 65)
    tester = SwitchWorkflowTester()

    # 1. Login as Assistant (admin role)
    status, login_html, _ = tester.get('login.php')
    login_csrf = tester.extract_csrf(login_html)
    status, body, dest = tester.post('login.php', {
        'csrf_token': login_csrf,
        'email': 'assistant.cse@iter.ac.in',
        'password': 'TestDevPass@123'
    })
    print(f"Login assistant result: HTTP {status} -> {dest}")
    assert "assistant/index.php" in dest or status == 200, "Assistant login failed"

    # 2. Check assistant portal page
    status, asst_html, _ = tester.get('assistant/index.php')
    asst_csrf = tester.extract_csrf(asst_html)
    assert status == 200 and "Debabrata Singh" in asst_html, "Assistant portal does not show assigned faculty"
    assert "<form action=" in asst_html and "switch.php" in asst_html, "Switch button must be a form"
    assert 'method="POST"' in asst_html or 'method="post"' in asst_html, "Switch form must use POST"
    print("[PASS] Assistant portal renders POST switch form with CSRF")

    # 3. Test GET on switch.php -> MUST return HTTP 405 Method Not Allowed
    status_get, body_get, _ = tester.get('assistant/switch.php?profile_id=1')
    assert status_get == 405, f"GET to switch.php should be HTTP 405, got {status_get}"
    assert "Method Not Allowed" in body_get, "Should reject GET with Method Not Allowed"
    print("[PASS] GET to assistant/switch.php rejected with HTTP 405 Method Not Allowed")

    # 4. Test POST to switch.php without CSRF -> MUST return HTTP 403 Forbidden
    status_no_csrf, body_no_csrf, _ = tester.post('assistant/switch.php', {'profile_id': 1})
    assert status_no_csrf == 403, f"POST without CSRF should be HTTP 403, got {status_no_csrf}"
    assert "CSRF" in body_no_csrf, "Should state CSRF rejection"
    print("[PASS] POST to assistant/switch.php without CSRF rejected with HTTP 403")

    # 5. Test POST to switch.php targeting unassigned faculty (IDOR check)
    # Profile 2 is Dr. Priyadarshi Kanungo (ECE), not assigned to CSE assistant
    status_idor, body_idor, dest_idor = tester.post('assistant/switch.php', {
        'csrf_token': asst_csrf,
        'profile_id': 2
    })
    assert "assistant/index.php" in dest_idor or "Unauthorized" in body_idor, "Unauthorized switch should be rejected"
    print("[PASS] Unauthorized profile switch rejected")

    # 6. Test valid POST switch to assigned faculty (Profile 1)
    status_switch, body_switch, dest_switch = tester.post('assistant/switch.php', {
        'csrf_token': asst_csrf,
        'profile_id': 1
    })
    assert "dashboard/index.php" in dest_switch, f"Should redirect to dashboard, got {dest_switch}"
    assert "Debabrata Singh" in body_switch or "Switched to faculty profile" in body_switch, "Dashboard should display switched faculty context"
    assert "switch_back.php" in body_switch, "Dashboard should display Exit Delegate Mode button"
    print("[PASS] Valid POST switch entered faculty management mode in dashboard")

    # 7. Test GET on switch_back.php -> MUST return HTTP 405 Method Not Allowed
    status_sb_get, body_sb_get, _ = tester.get('assistant/switch_back.php')
    assert status_sb_get == 405, f"GET to switch_back.php should be HTTP 405, got {status_sb_get}"
    print("[PASS] GET to assistant/switch_back.php rejected with HTTP 405 Method Not Allowed")

    # 8. Test POST to switch_back.php without CSRF -> MUST return HTTP 403
    status_sb_nocsrf, _, _ = tester.post('assistant/switch_back.php', {})
    assert status_sb_nocsrf == 403, f"POST to switch_back.php without CSRF should be HTTP 403, got {status_sb_nocsrf}"
    print("[PASS] POST to assistant/switch_back.php without CSRF rejected with HTTP 403")

    # 9. Test valid POST exit delegate mode with CSRF
    dash_csrf = tester.extract_csrf(body_switch)
    status_exit, body_exit, dest_exit = tester.post('assistant/switch_back.php', {
        'csrf_token': dash_csrf
    })
    assert "assistant/index.php" in dest_exit, f"Should redirect back to assistant index, got {dest_exit}"
    assert "Exited faculty management mode" in body_exit or "Research Assistant Portal" in body_exit
    print("[PASS] Valid POST switch_back returned safely to assistant portal")

    print("=" * 65)
    print("SWITCH WORKFLOW & POST ENFORCEMENT VERIFICATION: ALL PASSED")
    print("=" * 65)
    return True

if __name__ == '__main__':
    ok = test_switch()
    sys.exit(0 if ok else 1)
