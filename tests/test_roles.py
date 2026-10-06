import os
import urllib.request
import urllib.parse
import http.cookiejar
import re

BASE_URL = os.environ.get('BASE_URL', 'http://localhost/research_profile').rstrip('/')

def test_login(email, password, expected_sub):
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    r = opener.open(f'{BASE_URL}/login.php')
    html = r.read().decode('utf-8')
    csrf = re.search(r'name=["\']csrf_token["\']\s+value=["\']([a-f0-9]+)["\']', html).group(1)
    data = urllib.parse.urlencode({'csrf_token': csrf, 'email': email, 'password': password}).encode('utf-8')
    req = urllib.request.Request(f'{BASE_URL}/login.php', data=data)
    resp = opener.open(req)
    final_url = resp.geturl()
    passed = expected_sub in final_url
    print(f"{email} ({expected_sub}) -> {final_url} | OK: {passed}")

test_login('super@admin.com', 'Admin@123', 'admin/index.php')
test_login('assistant.cse@iter.ac.in', 'Assistant@123', 'assistant/index.php')
test_login('debabrata.singh@iter.ac.in', 'Faculty@123', 'dashboard/index.php')
test_login('ksp@soa.ac.in', 'Faculty@123', 'dashboard/index.php')
