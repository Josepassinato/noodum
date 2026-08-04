#!/bin/sh
set -eu
curl -fsS "${HUMHUB_BASE_URL:-http://localhost:8080}/healthz"

