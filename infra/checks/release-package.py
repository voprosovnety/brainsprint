#!/usr/bin/env python3
"""Narrow production-image check against compose.check.yaml disposable fixtures only.
HTTP requests simulate the TLS terminator; this is not a real HTTPS certificate check.
"""
import html
import json
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
ENV = dict(line.split('=', 1) for line in (ROOT / '.local/t5-container.env').read_text().splitlines() if '=' in line)
BASE = 'http://127.0.0.1:8011'
SECRET = ENV['CONSUMER_PROXY_SECRET']
COMPOSE = ['docker', 'compose', '--env-file', str(ROOT / '.local/t5-container.env'), '-f', str(ROOT / 'infra/production/compose.check.yaml')]


def command(*args, data=None):
    result = subprocess.run(COMPOSE + list(args), input=data, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    assert result.returncode == 0, 'Fixture command failed (output intentionally withheld to protect secrets)'
    return result.stdout


# Reuse the existing ignored local admin credential only inside the disposable DB.
# No credential is passed in shell arguments, printed, or retained in another host file.
credentials = json.loads((ROOT / '.local/admin-access.json').read_text())
command('exec', '-T', 'api', 'php', '-r', '''$d=json_decode(stream_get_contents(STDIN),true); umask(0077); file_put_contents('/tmp/release-admin-secret',$d['password']);''', data=json.dumps(credentials))
try:
    command('exec', '-T', 'api', 'php', 'artisan', 'admin:release', credentials['email'], '--password-file=/tmp/release-admin-secret')
finally:
    command('exec', '-T', 'api', 'php', '-r', "unlink('/tmp/release-admin-secret');")
assert 'disabled' in command('exec', '-T', 'api', 'sh', '-c', 'php artisan admin:local blocked@example.test; test $? -eq 1')
command('exec', '-T', 'api', 'php', 'artisan', 'analytics:seed-demo')
assert 'preview' in command('exec', '-T', 'api', 'php', 'artisan', 'privacy:retention')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None


opener = urllib.request.build_opener(NoRedirect)
cookies = {}


def call(path, data=None, proxy=True, csrf=True, extra=None):
    headers = {'Accept': 'application/json', 'Content-Type': 'application/json', 'Cookie': '; '.join(f'{k}={v}' for k, v in cookies.items())}
    if proxy:
        headers['X-Consumer-Proxy'] = SECRET
    if csrf and 'XSRF-TOKEN' in cookies:
        headers['X-XSRF-TOKEN'] = urllib.parse.unquote(cookies['XSRF-TOKEN'])
    headers.update(extra or {})
    req = urllib.request.Request(BASE + path, headers=headers, data=None if data is None else json.dumps(data).encode())
    try:
        response = opener.open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    for cookie in response.headers.get_all('Set-Cookie', []):
        key, value = cookie.split(';', 1)[0].split('=', 1)
        cookies[key] = value
    return response.status, body, response.headers


assert call('/up', extra={'Host': 'healthcheck.railway.app'})[0] == 200
assert call('/api/v1/bootstrap', proxy=False)[0] == 403
assert call('/api/v1/bootstrap', extra={'Host': 'hostile.example'})[0] == 400
status, body, headers = call('/api/v1/bootstrap', extra={'X-Forwarded-Host': 'hostile.example', 'X-Visitor-Id': 'admin'})
assert status == 200 and 'no-store' in headers['Cache-Control']
for cookie in headers.get_all('Set-Cookie'):
    assert 'secure' in cookie.lower() and 'domain=' not in cookie.lower()
assert call('/api/v1/runs', {}, csrf=False)[0] == 419
status, body, _ = call('/api/v1/runs', {})
assert status == 200
run = json.loads(body)
status, login, headers = call('/admin/login', extra={'Accept': 'text/html'})
assert status == 200 and 'no-store' in headers['Cache-Control']
admin_cookies = [c.lower() for c in headers.get_all('Set-Cookie', []) if c.startswith('brainsprint_admin_session=')]
assert len(admin_cookies) == 1 and all(x in admin_cookies[0] for x in ['secure', 'httponly', 'path=/admin', 'samesite=strict'])
assert 'domain=' not in admin_cookies[0]
assert 'https://127.0.0.1:8011/admin/livewire/' in login
snapshot = html.unescape(re.search(r'wire:snapshot="([^"]+)"', login)[1])
token = html.unescape(re.search(r'<meta name="csrf-token" content="([^"]+)"', login)[1])
status, body, _ = call('/admin/livewire/update', {'components': [{'snapshot': snapshot, 'updates': {'data.email': credentials['email'], 'data.password': credentials['password']}, 'calls': [{'method': 'authenticate', 'params': []}]}]}, extra={'X-CSRF-TOKEN': token, 'X-Livewire': ''})
assert status == 200 and 'redirect' in body
assert call('/admin/runs')[0] == 200
assert json.loads(call('/api/v1/runs/' + run['id'])[1])['id'] == run['id']
assert call('/api/v1/privacy/delete', {})[0] == 200
assert call('/admin/runs')[0] == 200
assert json.loads(call('/api/v1/bootstrap')[1])['run'] is None
assert call('/api/v1/runs/' + run['id'])[0] == 404
status, body, _ = call('/api/v1/demo-analytics')
assert status == 200 and json.loads(body)['dataset'] == 'synthetic'
assert call('/api/v1/demo-analytics?dataset=real')[0] == 422
assert call('/.env')[0] in (403, 404) and call('/composer.json')[0] == 404
assets = re.findall(r'(?:href|src)="https://127\.0\.0\.1:8011([^"?]+\.(?:css|js))', login)
assert assets, 'Local static Filament assets must be present'
for asset in assets[:3]:
    status, body, _ = call(asset)
    assert status == 200 and len(body) > 100
print('PASS: production Apache/PHP image, health, private document root, local Filament assets, deliberate release admin, secure host-only scoped cookies, fixed HTTPS URLs, host/proxy trust, CSRF, deletion/admin isolation, synthetic metrics and retention preview on disposable PostgreSQL')
