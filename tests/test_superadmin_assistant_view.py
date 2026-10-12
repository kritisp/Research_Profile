import urllib.request
import urllib.parse
import http.cookiejar
import re

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. Login as Super Admin
r = opener.open('http://localhost/research_profile/login.php')
html = r.read().decode('utf-8')
csrf = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', html).group(1)
data = urllib.parse.urlencode({'csrf_token': csrf, 'email': 'super@admin.com', 'password': 'Admin@123'}).encode('utf-8')
opener.open('http://localhost/research_profile/login.php', data=data)

# 2. Test Super Admin on assistant/index.php default view (Admins & Assistants View)
r = opener.open('http://localhost/research_profile/assistant/index.php')
body = r.read().decode('utf-8')
assert r.status == 200
assert 'Admins &amp; Assistants View' in body or 'Admins & Assistants View' in body
assert 'Pooja Mohapatra' in body
print("[PASS] Super Admin opens Assistant Portal -> sees Admin Cards View")

# 3. Test Super Admin inspecting Pooja's panel (admin_id=5)
r = opener.open('http://localhost/research_profile/assistant/index.php?tab=admins&admin_id=5')
body = r.read().decode('utf-8')
assert r.status == 200
assert 'Inspecting Admin:' in body
assert 'Dr. Debabrata Singh' in body # assigned to Pooja
print("[PASS] Super Admin drills down into Admin Pooja's panel -> sees assigned faculty & audit badges")

# 4. Test Super Admin on Faculty-Wise View (tab=all_faculties)
r = opener.open('http://localhost/research_profile/assistant/index.php?tab=all_faculties')
body = r.read().decode('utf-8')
assert r.status == 200
assert 'Faculty-Wise Master Overview (All Institutional Scholars)' in body
assert 'Assigned Admin(s):' in body
print("[PASS] Super Admin opens Faculty-Wise View -> sees all institutional faculties with assigned admin indicators")

# 5. Test Regular Admin Login (Pooja)
cj_admin = http.cookiejar.CookieJar()
opener_admin = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj_admin))
r = opener_admin.open('http://localhost/research_profile/login.php')
html = r.read().decode('utf-8')
csrf = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', html).group(1)
data = urllib.parse.urlencode({'csrf_token': csrf, 'email': 'assistant.cse@iter.ac.in', 'password': 'Assistant@123'}).encode('utf-8')
opener_admin.open('http://localhost/research_profile/login.php', data=data)

# Test Regular Admin on assistant/index.php
r = opener_admin.open('http://localhost/research_profile/assistant/index.php')
body = r.read().decode('utf-8')
assert r.status == 200
assert 'Admins &amp; Assistants View' not in body # Only their own portfolio
assert 'Dr. Debabrata Singh' in body
print("[PASS] Regular Admin sees only their own portfolio without super admin tabs")

print("ALL ASSISTANT PORTAL HIERARCHY TESTS COMPLETED SUCCESSFULLY!")
