#!/bin/sh
# Build a source archive for operators who want to publish the tree they have
# checked out. Review the archive before you share it. The script refuses to
# pack env files and overrides, but it will pack anything else you left in the
# working tree.
set -eu
project_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
release_dir="$project_dir/releases"
mkdir -p "$release_dir"
tar -czf "$release_dir/noodum-source.tar.gz" \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='compose.override.yml' \
  --exclude='compose.override.yml.*' \
  --exclude='releases' \
  --exclude='protected/runtime' \
  --exclude='uploads' \
  --exclude='backups' \
  --exclude='*.sql' \
  --exclude='*.sql.gz' \
  -C "$project_dir" .
chmod 0644 "$release_dir/noodum-source.tar.gz"
