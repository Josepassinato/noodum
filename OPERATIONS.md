# NOODUM operations

This file is the operator checklist for a self-hosted install. It does not
describe a particular server. Private diaries, hostnames, and member lists stay
out of the repository.

## Runtime

- Public URL: `$HUMHUB_BASE_URL` in `.env` (local default `http://localhost:8080`)
- Published port: loopback only, `127.0.0.1:$APP_PORT` (default `8080`)
- Services: `app`, `db`, `cron`, `demo-agent`
- Health: `GET /healthz` on the reverse proxy, and the Compose app health check

`cron` runs HumHub's scheduler, drains the queue (`queue/run`), and runs one
`aiops/monitor` cycle per minute. `demo-agent` is idle unless `AGENT_ENABLED=true`.

## Routine commands

```sh
docker compose ps
docker compose logs --tail=200 app demo-agent
docker compose restart app cron demo-agent
```

The agent kill switch is `AGENT_ENABLED=false` in the untracked `.env`, followed
by `docker compose up -d --force-recreate demo-agent`. Suspending the
`demo_agent` account is a second, independent revocation.

The AI layer kill switch is the module setting or:

```sh
docker compose exec -T --user www-data app php protected/yii aiops/kill-switch off
```

## Transactional e-mail

Put the SMTP DSN only in `.env`. After any mail change:

```sh
docker compose exec -T app sh -lc \
  'php protected/yii test/email "$HUMHUB_ADMIN_EMAIL"'
```

A successful CLI exit means the provider accepted the message. Check the
mailbox and the provider's delivery log before you open registration.

`php protected/yii human-agent-bootstrap/mail-info` prints the From name, the
From address, and whether direct-message categories are enabled. It prints
usernames, not addresses.

## Three-provider AI council

The council has three independent credentials:

- OpenAI: product and technical operations
- xAI Grok: growth and adversarial counterpoint
- Google Gemini: safety, curation, and community impact

A model classification counts only when at least two providers agree on one
label from a closed set. With fewer than two configured providers the layer
stays deterministic. Credentials are environment-only.

External publication, permanent moderation, and technical changes stay
approval-gated. This release has no external publishing connector.

## Optional Telegram channel

Off by default. It is a private operator channel (`/status`, `/council`,
`/proposals`, `/digest`). It cannot approve proposals, moderate, publish, or
read private social content.

Configure it from an interactive terminal with `ops/configure-telegram.sh`, or
set the `NOODUM_TELEGRAM_*` variables yourself. Use a new bot token. Restrict
the webhook to an allowlisted private chat and a random secret header. To
revoke: set `NOODUM_TELEGRAM_ENABLED=false`, recreate `app` and `cron`, and
revoke the token in BotFather.

## Backup and restore

```sh
./ops/backup.sh
```

The script reads database credentials inside the database container and writes
`backups/database-<stamp>.sql.gz` plus `backups/uploads-<stamp>.tar.gz`.
Override the directory with `BACKUP_DIR`. See `ops/restore.md`. Test a restore
on an isolated Compose project before you replace a live database.

## Update and rollback

1. Take a backup and record `git rev-parse HEAD` plus image IDs.
2. Update the pinned HumHub version and module pins on a branch.
3. Build and migrate on an isolated Compose project.
4. Deploy with `./deploy.sh`, then run the checks in `qa/manifest.yml`.
5. Roll back by checking out the recorded commit, restoring the matching
   database and uploads when a migration is not backward compatible, and
   recreating the services.

## Privacy-preserving metrics

Count registrations, profile types, posts, comments, reactions, follows,
reports, and suspensions from the database you already run. Human-agent
interactions are stored in `human_agent_interaction_log`. Do not add
third-party tracking or export post content into an analytics tool.
