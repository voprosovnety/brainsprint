#!/bin/sh
set -eu
cd "$(dirname "$0")/../.."
python3 - <<'PY'
from pathlib import Path
import base64, secrets
root = Path('.local')
root.mkdir(mode=0o700, exist_ok=True)
p = root / 't5-container.env'
if not p.exists():
    database = secrets.token_hex(32)
    content = 'APP_ENV=production\nAPP_DEBUG=false\nAPP_URL=https://127.0.0.1:8011\n'
    content += 'APP_KEY=base64:' + base64.b64encode(secrets.token_bytes(32)).decode() + '\n'
    content += 'CONSUMER_PROXY_SECRET=' + secrets.token_hex(32) + '\n'
    content += 'RELEASE_FIXTURE_DB_PASSWORD=' + database + '\nDB_PASSWORD=' + database + '\n'
    content += 'SESSION_DRIVER=database\nSESSION_LIFETIME=10080\nSESSION_ENCRYPT=true\nSESSION_COOKIE=brainsprint_session\nSESSION_SECURE_COOKIE=true\nCACHE_STORE=file\nDB_CONNECTION=pgsql\nLOG_CHANNEL=stderr\n'
    import os
    fd = os.open(p, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as file:
        file.write(content)
PY
docker build -f infra/production/Dockerfile -t brainsprint-api:t5 .
docker compose --env-file .local/t5-container.env -f infra/production/compose.check.yaml up -d
docker compose --env-file .local/t5-container.env -f infra/production/compose.check.yaml exec -T api php artisan migrate --force
docker compose --env-file .local/t5-container.env -f infra/production/compose.check.yaml exec -T api php artisan db:seed --force
