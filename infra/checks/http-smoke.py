#!/usr/bin/env python3
"""Real same-origin cookies/CSRF/retry/ownership/rate checks; local development only."""
import concurrent.futures
import http.cookiejar
import json
import os
import time
import urllib.error
import urllib.parse
import urllib.request

SESSION_COOKIE = os.environ.get('BRAINSPRINT_SESSION_COOKIE', 'brainsprint_session')
BASE = os.environ.get('BRAINSPRINT_WEB_ORIGIN', 'http://127.0.0.1:3000')
assert urllib.parse.urlparse(BASE).hostname in ('127.0.0.1', '127.0.0.2', 'localhost'), 'Local fixtures only'

def browser():
    jar = http.cookiejar.CookieJar()
    return jar, urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def call(client, path, data=None, csrf=True, origin=BASE):
    jar, opener = client
    headers = {'Accept': 'application/json', 'Origin': origin}
    if csrf:
        token = next((c.value for c in jar if c.name == 'XSRF-TOKEN'), None)
        if token:
            headers['X-XSRF-TOKEN'] = urllib.parse.unquote(token)
    if data is not None:
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(BASE + '/api/v1/' + path, headers=headers, data=None if data is None else json.dumps(data).encode())
    try:
        response = opener.open(req)
    except urllib.error.HTTPError as e:
        response = e
    return response.status, json.load(response), response.headers

def progress_call(client, path, data=None, **options):
    # V3 has more writes than the per-minute budget; respect it without weakening the API.
    for attempt in range(3):
        response = call(client, path, data, **options)
        if response[0] != 429 or attempt == 2:
            return response
        time.sleep(min(65, max(1, int(response[2].get('Retry-After', '60')))))

owner = browser()
status, bootstrap, headers = progress_call(owner, 'bootstrap')
assert status == 200 and 'no-store' in headers['Cache-Control']
assert any(c.name == SESSION_COOKIE and c.has_nonstandard_attr('httponly') for c in owner[0])
assert progress_call(owner, 'runs', {}, csrf=False)[0] == 419
assert progress_call(owner, 'runs', {}, origin='https://foreign.example')[0] == 403
# Independent cookie jars share the same initialized anonymous session for concurrent retries.
clients = []
for _ in range(2):
    client = browser()
    for cookie in owner[0]:
        client[0].set_cookie(cookie)
    clients.append(client)
with concurrent.futures.ThreadPoolExecutor(2) as pool:
    starts = list(pool.map(lambda c: call(c, 'runs', {}), clients))
assert all(s[0] == 200 for s in starts)
run = starts[0][1]
assert run['id'] == starts[1][1]['id']
stranger = browser()
call(stranger, 'bootstrap')
assert call(stranger, f"runs/{run['id']}")[0] == 404
assert call(stranger, f"runs/{run['id']}/steps/{run['current_step']}/complete", {'answer': {'acknowledged': True}})[0] == 404
# Real progression through both ordinary and terminal completions; duplicate every write.
while run['current_step'] != 'done':
    screen = next(s for s in run['configuration']['steps'] if s['id'] == run['current_step'])
    kind = screen['type']
    if kind in ('single_choice', 'scale'):
        answer = {'choice': screen['options'][0]['id']}
    elif kind == 'processing' and screen.get('prompts'):
        answer = {'focus': 'yes', 'practice': 'no'}
    elif kind == 'email':
        answer = {'email': None}
    elif kind == 'paywall':
        assert run['result'] is None and run['teaser'] and run['commerce']['deadline']
        answer = {'offer_id': screen['offer_id'], 'acknowledged': True}
    elif kind == 'upsell':
        answer = {'decision': os.environ.get('BRAINSPRINT_GUIDE_CHOICE', 'accept')}
    else:
        answer = {'acknowledged': True}
    step = f"runs/{run['id']}/steps/{screen['id']}/complete"
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        completed = list(pool.map(lambda c: progress_call(c, step, {'answer': answer}), clients))
    assert all(s[0] == 200 for s in completed), completed
    assert completed[0][1] == completed[1][1]
    saved = completed[0][1]
    assert progress_call(owner, 'bootstrap')[1]['run'] == saved
    if kind == 'single_choice':
        alternative = screen['options'][1]['id']
        assert progress_call(owner, step, {'answer': {'choice': alternative}})[0] == 409
    if run['result']:
        assert saved['result'] == run['result']
    run = saved
assert run['demo_completed'] and run['result']['kind'] == 'illustrative_sample'
# One session reaching its quota must not exhaust another session's quota.
limited = browser()
call(limited, 'bootstrap')
for _ in range(60):
    status, _, headers = call(limited, 'bootstrap')
    if status == 429:
        break
assert status == 429 and headers.get('Retry-After')
assert call(stranger, 'bootstrap')[0] == 200
# Only delete the explicitly disposable visitor created in this script, never existing history.
assert progress_call(owner, 'privacy/delete', {}, csrf=False)[0] == 419
assert progress_call(owner, 'privacy/delete', {}, origin='https://foreign.example')[0] == 403
assert progress_call(owner, 'privacy/delete', {'visitor_id': 'foreign'})[0] == 422
assert progress_call(owner, 'privacy/delete?visitor_id=foreign', {})[0] == 422
old_cookie = next(c.value for c in owner[0] if c.name == SESSION_COOKIE)
assert progress_call(owner, 'privacy/delete', {})[1] == {'deleted': True}
assert next(c.value for c in owner[0] if c.name == SESSION_COOKIE) != old_cookie
assert progress_call(owner, 'bootstrap')[1]['run'] is None
assert progress_call(owner, f"runs/{run['id']}")[0] == 404
assert call(stranger, 'bootstrap')[0] == 200
# A stale cookie replay cannot recover the deleted visitor identity.
assert call(clients[0], 'bootstrap')[1]['run'] is None
print('PASS: deletion CSRF/origin/body/rotation/clean restore/stale-cookie replay; full funnel, actual cookies, CSRF, origin, concurrent retries, restoration, ownership, stable report/finish, independent rate limits')
