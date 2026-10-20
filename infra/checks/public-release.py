#!/usr/bin/env python3
"""Explicit public release acceptance, using only newly created disposable visitors."""
import concurrent.futures
import http.cookiejar
import json
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


owner, stranger = browser(), browser()
try:
    status, bootstrap, headers = call(owner, 'bootstrap')
    assert status == 200 and 'no-store' in headers['Cache-Control']
    assert all(c.secure and not c.domain_specified for c in owner[0])
    session = next(c for c in owner[0] if c.name == 'brainsprint_session')
    assert session.has_nonstandard_attr('HttpOnly') or session.has_nonstandard_attr('httponly')
    assert call(owner, 'runs', {}, csrf=False)[0] == 419
    assert call(owner, 'runs', {}, origin='https://foreign.example')[0] == 403
    status, run, _ = call(owner, 'runs', {})
    assert status == 200
    assert call(owner, 'runs', {})[1]['id'] == run['id']
    assert call(stranger, 'bootstrap')[0] == 200
    assert call(stranger, f"runs/{run['id']}")[0] == 404
    assert call(owner, 'bootstrap')[1]['experiment'] == bootstrap['experiment']
    clients = []
    for _ in range(2):
        client = browser()
        for cookie in owner[0]:
            client[0].set_cookie(cookie)
        clients.append(client)
    while run['current_step'] != 'done':
        screen = next(s for s in run['configuration']['steps'] if s['id'] == run['current_step'])
        kind = screen['type']
        if kind in ('visual_memory', 'tutorial'):
            answer = {'cells': screen['pattern']}
        elif kind == 'pattern':
            answer = {'choice': screen['correct_choice']}
        elif kind == 'logic':
            answer = {'order': screen['correct_order']}
        else:
            answer = {'acknowledged': True}
        path = f"runs/{run['id']}/steps/{screen['id']}/complete"
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            saved = list(pool.map(lambda c: call(c, path, {'answer': answer}), clients))
        assert all(s[0] == 200 for s in saved), [(s[0], s[1]) for s in saved]
        assert saved[0][1] == saved[1][1]
        run = saved[0][1]
        assert call(owner, 'bootstrap')[1]['run'] == run
    assert run['demo_completed'] and run['result']['kind'] == 'illustrative_demo'
    assert call(owner, 'privacy/delete', {}, csrf=False)[0] == 419
    assert call(owner, 'privacy/delete', {})[1] == {'deleted': True}
    assert call(owner, 'bootstrap')[1]['run'] is None
    assert call(clients[0], 'bootstrap')[1]['run'] is None
    print('PASS: public HTTPS, secure host-only cookies, CSRF/origin, ownership, stable experiment, full funnel, concurrent deduplication, restore, illustrative result and deletion.')
finally:
    # These cookie jars belong exclusively to this invocation, never a real visitor.
    for client in [owner, stranger]:
        try:
            call(client, 'privacy/delete', {})
        except Exception:
            pass
