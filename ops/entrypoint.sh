#!/bin/sh
set -eu

mkdir -p protected/config protected/runtime uploads
chown www-data:www-data protected/config
chown -R www-data:www-data protected/runtime uploads
find protected/config -maxdepth 1 -type f ! -name console.php ! -name common.php \
  -exec chown www-data:www-data {} +

if [ ! -f protected/config/dynamic.php ]; then
  php protected/yii installer/write-db-config \
    "$HUMHUB_DB_HOST" "$HUMHUB_DB_NAME" "$HUMHUB_DB_USER" "$HUMHUB_DB_PASSWORD"
fi

if ! MYSQL_PWD="$HUMHUB_DB_PASSWORD" mariadb \
  -h "$HUMHUB_DB_HOST" -u "$HUMHUB_DB_USER" "$HUMHUB_DB_NAME" \
  -Nse "SELECT 1 FROM migration LIMIT 1" >/dev/null 2>&1; then
  php protected/yii installer/install-db
  php protected/yii installer/write-site-config \
    "${HUMHUB_SITE_NAME:-NOODUM}" "${HUMHUB_SITE_EMAIL:-noreply@example.invalid}"
  php protected/yii installer/create-admin-account \
    "$HUMHUB_ADMIN_USER" "$HUMHUB_ADMIN_EMAIL" "$HUMHUB_ADMIN_PASSWORD"
  php protected/yii installer/set-base-url "$HUMHUB_BASE_URL"
fi

exec "$@"
