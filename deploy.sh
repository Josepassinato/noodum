#!/bin/sh
set -eu
docker compose build app
docker compose up -d db app cron demo-agent
docker compose exec -T app php protected/yii migrate/up --interactive=0
docker compose exec -T app php protected/yii human-agent-bootstrap
docker compose exec -T app php protected/yii human-agent-bootstrap/seed
curl -fsS "${HUMHUB_BASE_URL:-http://localhost:8080}/healthz"

