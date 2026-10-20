#!/usr/bin/env python3
"""Advance only the checked release refs; confirm backend before frontend."""
import json
import os
import re
import subprocess
import time
import urllib.error
import urllib.request

commit = os.environ['RELEASE_COMMIT']
repository = os.environ['GITHUB_REPOSITORY']
if not re.fullmatch(r'[0-9a-f]{40}', commit) or repository != 'voprosovnety/brainsprint':
    raise SystemExit('Unexpected release source.')

for service, origin in [('api', 'https://brainsprint-api-voprosovnety.vercel.app'),
                        ('web', 'https://brainsprint-voprosovnety.vercel.app')]:
    branch = 'codex/release-' + service
    result = subprocess.run(['gh', 'api', f'repos/{repository}/git/ref/heads/{branch}'], capture_output=True, text=True)
    if result.returncode == 0:
        command = ['gh', 'api', '--method', 'PATCH', f'repos/{repository}/git/refs/heads/{branch}', '-f', 'sha=' + commit]
    else:
        command = ['gh', 'api', '--method', 'POST', f'repos/{repository}/git/refs', '-f', 'ref=refs/heads/' + branch, '-f', 'sha=' + commit]
    subprocess.run(command, check=True, stdout=subprocess.DEVNULL)
    deadline = time.monotonic() + 300
    while time.monotonic() < deadline:
        try:
            with urllib.request.urlopen(origin + '/release.json', timeout=20) as response:
                deployed = json.load(response)
            if deployed == {'service': service, 'commit': commit}:
                for path in (['/up', '/admin/login'] if service == 'api' else ['/']):
                    with urllib.request.urlopen(origin + path, timeout=30) as response:
                        if response.status != 200:
                            raise urllib.error.URLError('Release health is unavailable.')
                print(service + ': checked commit is live.', flush=True)
                break
        except (urllib.error.URLError, ValueError):
            pass
        time.sleep(10)
    else:
        raise SystemExit(service + ': deployment did not become ready; publication stopped.')
