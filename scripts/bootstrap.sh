#!/usr/bin/env bash
set -euo pipefail
cd /workspace/kevcoachfit
python3 - <<'PY'
import secrets
from pathlib import Path
p=Path('.env')
if not p.exists():
 p.write_text('KCF_DB_PASSWORD='+secrets.token_urlsafe(30)+'\nKCF_DB_ROOT_PASSWORD='+secrets.token_urlsafe(30)+'\nKCF_LOCAL_PASSWORD='+secrets.token_urlsafe(30)+'\n');p.chmod(0o600)
PY
docker compose up -d database wordpress
for attempt in $(seq 1 30); do
 if docker compose exec -T wordpress test -f /var/www/html/wp-config.php; then break; fi
 sleep 1
done
docker compose run --rm cli sh -c 'wp core is-installed || wp core install --url=http://127.0.0.1:8080 --title="KevCoachFit atelier local" --admin_user=atelier_kevin --admin_email=atelier@example.test --admin_password="$KCF_LOCAL_PASSWORD" --skip-email'
docker compose run --rm cli wp eval-file /project/scripts/bootstrap.php
