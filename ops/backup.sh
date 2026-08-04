#!/bin/sh
set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
backup_dir=${BACKUP_DIR:-/var/backups/human-agent-social}
stamp=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p "$backup_dir"

cd "$project_dir"
docker compose exec -T db sh -c \
  'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
  | gzip > "$backup_dir/database-$stamp.sql.gz"
docker run --rm -v human-agent-social_humhub_uploads:/data:ro \
  -v "$backup_dir":/backup alpine tar -czf "/backup/uploads-$stamp.tar.gz" -C /data .
find "$backup_dir" -type f -mtime +14 -delete
