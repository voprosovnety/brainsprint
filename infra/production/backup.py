#!/usr/bin/env python3
"""Produce a bounded authenticated encrypted dump; never upload plaintext."""
import os
from pathlib import Path
import subprocess
import tempfile
from urllib.parse import parse_qs, unquote, urlparse

os.umask(0o077)
url = urlparse(os.environ['DB_URL'])
query = parse_qs(url.query)
if not url.hostname.endswith('.neon.tech') or url.path != '/brainsprint' or query.get('sslmode') != ['verify-full']:
    raise SystemExit('Backup refused: unexpected database or unverified TLS.')
environment = os.environ.copy()
environment.update(PGHOST=url.hostname, PGPORT=str(url.port or 5432), PGDATABASE='brainsprint',
                   PGUSER=unquote(url.username), PGPASSWORD=unquote(url.password), PGSSLMODE='verify-full',
                   PGSSLROOTCERT=query.get('sslrootcert', [os.environ['PGSSLROOTCERT']])[0], PGCONNECT_TIMEOUT='10')
output = Path(os.environ.get('RUNNER_TEMP', '.local')) / 'brainsprint-backup.cms'
with tempfile.TemporaryDirectory(prefix='brainsprint-backup-') as directory:
    plain = Path(directory) / 'database.dump'
    command = ['pg_dump', '--format=custom', '--no-owner', '--no-acl', '--compress=6', '--file', str(plain)]
    if os.environ.get('BRAINSPRINT_BACKUP_DOCKER') == '1':
        # Hosted runners ship PostgreSQL 16; use the project's pinned 17 client.
        command[-1] = '/backup/database.dump'
        prefix = ['docker', 'run', '--rm', '--user', f'{os.getuid()}:{os.getgid()}']
        for name in ['PGHOST', 'PGPORT', 'PGDATABASE', 'PGUSER', 'PGPASSWORD', 'PGSSLMODE', 'PGSSLROOTCERT', 'PGCONNECT_TIMEOUT']:
            prefix += ['--env', name]
        command = prefix + ['--volume', directory + ':/backup', '--volume', '/etc/ssl/certs:/etc/ssl/certs:ro',
                            'postgres:17.6-alpine'] + command
    result = subprocess.run(command,
                            env=environment, capture_output=True, timeout=180)
    if result.returncode:
        raise SystemExit('Backup failed; no plaintext was published.')
    if plain.stat().st_size > 20 * 1024 * 1024:
        raise SystemExit('Backup exceeds the 20 MiB free-storage guard; publication refused.')
    result = subprocess.run(['openssl', 'cms', '-encrypt', '-binary', '-aes-256-gcm', '-in', str(plain),
                             '-out', str(output), '-outform', 'DER', '-recip', 'infra/production/backup-recipient.crt',
                             '-keyopt', 'rsa_padding_mode:oaep', '-keyopt', 'rsa_oaep_md:sha256'], capture_output=True)
    if result.returncode:
        output.unlink(missing_ok=True)
        raise SystemExit('Authenticated backup encryption failed; publication refused.')
print('Encrypted release backup prepared:', output.stat().st_size, 'bytes; plaintext removed.')
