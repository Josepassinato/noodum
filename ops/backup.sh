#!/bin/sh
set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
backup_dir=${BACKUP_DIR:-"$project_dir/backups"}
stamp=$(date -u +%Y%m%dT%H%M%SZ)
mkdir -p "$backup_dir"

cd "$project_dir"
docker compose exec -T db sh -c \
  'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
  | gzip > "$backup_dir/database-$stamp.sql.gz"
docker compose exec -T app tar -czf - -C /var/www/html/uploads . \
  > "$backup_dir/uploads-$stamp.tar.gz"
find "$backup_dir" -type f -mtime +14 -delete
