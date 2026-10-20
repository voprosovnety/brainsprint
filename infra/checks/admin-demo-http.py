#!/usr/bin/env python3
"""Focused local T4 check. Requires private admin:local credentials; never prints secrets."""
import html
import http.cookiejar
import json
import pathlib
import re
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
credentials = json.loads((ROOT / '.local/admin-access.json').read_text())
api = 'http://127.0.0.1:8008'
web = 'http://127.0.0.1:3000'
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def request(origin, path, body=None, headers=None):
    req = urllib.request.Request(origin + path, data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Accept': 'application/json', 'Content-Type': 'application/json', **(headers or {})})
    try:
        with client.open(req, timeout=15) as response:
            return response.status, response.read().decode(), response.geturl(), response.headers
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.geturl(), error.headers


def csrf(page):
    return html.unescape(re.search(r'<meta name="csrf-token" content="([^"]+)"', page)[1])


status, body, _, _ = request(web, '/api/v1/bootstrap')
assert status == 200
consumer_token = urllib.parse.unquote(next(c.value for c in jar if c.name == 'XSRF-TOKEN' and c.path == '/'))
status, body, _, _ = request(web, '/api/v1/runs', {}, {'X-XSRF-TOKEN': consumer_token, 'Origin': web})
assert status == 200
saved = json.loads(body)
consumer_cookie = next(c.value for c in jar if c.name == 'brainsprint_session')
status, login, _, _ = request(api, '/admin/login', headers={'Accept': 'text/html'})
assert status == 200
snapshot = html.unescape(re.search(r'wire:snapshot="([^"]+)"', login)[1])
status, body, _, _ = request(api, '/admin/livewire/update', {'components': [{'snapshot': snapshot,
    'updates': {'data.email': credentials['email'], 'data.password': credentials['password']},
    'calls': [{'method': 'authenticate', 'params': []}]}]}, {'X-CSRF-TOKEN': csrf(login), 'X-Livewire': ''})
assert status == 200 and 'redirect' in body
assert next(c for c in jar if c.name == 'brainsprint_admin_session').path == '/admin'
for path in ['/admin/analytics', '/admin/runs', '/admin/events', '/admin/configurations', '/admin/visitors']:
    status, page, url, headers = request(api, path, headers={'Accept': 'text/html'})
    assert status == 200 and '/login' not in url and 'no-store' in headers['Cache-Control']
assert next(c.value for c in jar if c.name == 'brainsprint_session') == consumer_cookie
status, body, _, _ = request(web, '/api/v1/runs/' + saved['id'])
assert status == 200 and json.loads(body) == saved
for origin in [web, api]:
    status, body, _, headers = request(origin, '/api/v1/demo-analytics')
    result = json.loads(body)
    assert status == 200 and result['dataset'] == 'synthetic' and 'no-store' in headers['Cache-Control']
    assert set(result) == {'dataset', 'cohort', 'totals', 'stage_conversions', 'quiz_elapsed_seconds', 'experiment', 'v3'}
    for query in ['dataset=real', 'dataset=all', 'from=1900-01-01', 'run_id=private', 'sql=select']:
        assert request(origin, '/api/v1/demo-analytics?' + query)[0] == 422
for path in ['/api/admin', '/api/v1/admin', '/api/v1/admin/runs']:
    assert request(web, path)[0] == 404
status, page, _, _ = request(api, '/admin/analytics', headers={'Accept': 'text/html'})
request(api, '/admin/logout', {}, {'X-CSRF-TOKEN': csrf(page), 'Accept': 'text/html'})
assert request(api, '/admin/runs', headers={'Accept': 'text/html'})[2].endswith('/admin/login')
assert json.loads(request(web, '/api/v1/runs/' + saved['id'])[1]) == saved
print('PASS: real admin login/logout, direct resources, same-host cross-port cookie isolation, preserved consumer run, synthetic-only API/proxy, private cache, admin proxy denial')
