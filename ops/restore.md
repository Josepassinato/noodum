# Restore

1. Stop the application with `docker compose stop app cron`.
2. Restore the selected database dump into the `db` service.
3. Restore the matching uploads archive into the `humhub_uploads` volume.
4. Start with `docker compose up -d` and run `docker compose exec app php protected/yii migrate/up --interactive=0`.
5. Validate `/healthz`, login, feed and one uploaded image.

Always test restoration on an isolated Compose project before replacing live data.

