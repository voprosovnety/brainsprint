#!/usr/bin/env python3
"""Explicit public release acceptance, using only newly created disposable visitors."""
import concurrent.futures
import http.cookiejar
import json
import time
import urllib.error
import urllib.parse
import urllib.request

BASE = 'https://brainsprint-voprosovnety.vercel.app'


def browser():
    jar = http.cookiejar.CookieJar()
    return jar, urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def call(client, path, data=None, csrf=True, origin=BASE):
    jar, opener = client
    headers = {'Accept': 'application/json', 'Origin': origin}
    token = next((c.value for c in jar if c.name == 'XSRF-TOKEN'), None)
    if csrf and token:
        headers['X-XSRF-TOKEN'] = urllib.parse.unquote(token)
    if data is not None:
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(BASE + '/api/v1/' + path, headers=headers,
                                 data=None if data is None else json.dumps(data).encode())
    try:
        response = opener.open(req, timeout=65)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.load(response), response.headers


def progress_call(client, path, data=None, **options):
    # The full v3 path plus duplicate writes exceeds the normal minute budget.
    for attempt in range(3):
        response = call(client, path, data, **options)
        if response[0] != 429 or attempt == 2:
            return response
        time.sleep(min(60, max(1, int(response[2].get('Retry-After', '60')))))


owner, stranger = browser(), browser()
try:
    status, bootstrap, headers = progress_call(owner, 'bootstrap')
    assert status == 200 and 'no-store' in headers['Cache-Control']
    assert all(c.secure and not c.domain_specified for c in owner[0])
    session = next(c for c in owner[0] if c.name == 'brainsprint_session')
    assert session.has_nonstandard_attr('HttpOnly') or session.has_nonstandard_attr('httponly')
    assert progress_call(owner, 'runs', {}, csrf=False)[0] == 419
    assert progress_call(owner, 'runs', {}, origin='https://foreign.example')[0] == 403
    status, run, _ = progress_call(owner, 'runs', {})
    assert status == 200
    assert progress_call(owner, 'runs', {})[1]['id'] == run['id']
    assert progress_call(stranger, 'bootstrap')[0] == 200
    assert progress_call(stranger, f"runs/{run['id']}")[0] == 404
    assert bootstrap['experiment'] is None and bootstrap['restart_required'] is False
    clients = []
    for _ in range(2):
        client = browser()
        for cookie in owner[0]:
            client[0].set_cookie(cookie)
        clients.append(client)
    while run['current_step'] != 'done':
        screen = next(s for s in run['configuration']['steps'] if s['id'] == run['current_step'])
        kind = screen['type']
        if kind in ('single_choice', 'scale'):
            answer = {'choice': screen['options'][0]['id']}
        elif kind == 'processing' and screen['prompts']:
            answer = {'focus': 'yes', 'practice': 'no'}
        elif kind == 'email':
            answer = {'email': None}
        elif kind == 'paywall':
            answer = {'offer_id': screen['offer_id'], 'acknowledged': True}
        elif kind == 'upsell':
            answer = {'decision': 'decline'}
        else:
            answer = {'acknowledged': True}
        path = f"runs/{run['id']}/steps/{screen['id']}/complete"
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            saved = list(pool.map(lambda c: progress_call(c, path, {'answer': answer}), clients))
        assert all(s[0] == 200 for s in saved), [(s[0], s[1]) for s in saved]
        assert saved[0][1] == saved[1][1]
        run = saved[0][1]
        assert progress_call(owner, 'bootstrap')[1]['run'] == run
    assert run['demo_completed'] and run['result']['kind'] == 'illustrative_sample'
    assert progress_call(owner, 'privacy/delete', {}, csrf=False)[0] == 419
    assert progress_call(owner, 'privacy/delete', {})[1] == {'deleted': True}
    assert progress_call(owner, 'bootstrap')[1]['run'] is None
    assert progress_call(clients[0], 'bootstrap')[1]['run'] is None
    print('PASS: public HTTPS, secure host-only cookies, CSRF/origin, ownership, direct quiz entry, full funnel, concurrent deduplication, restore, illustrative result and deletion.')
finally:
    # These cookie jars belong exclusively to this invocation, never a real visitor.
    for client in [owner, stranger]:
        try:
            progress_call(client, 'privacy/delete', {})
        except Exception:
            pass
