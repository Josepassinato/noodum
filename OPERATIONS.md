# NOODUM operations

## Runtime

- Public URL: `$HUMHUB_BASE_URL` (see `.env`)
- App loopback: `127.0.0.1:8234`
- Services: `app`, `db`, `cron`, `demo-agent`
- Health: `GET /healthz` and the Compose app health check

## Routine commands

```sh
docker compose ps
docker compose logs --tail=200 app demo-agent
docker compose restart app cron demo-agent
```

The agent kill switch is `AGENT_ENABLED=false` in the protected `.env`, followed
by `docker compose up -d --force-recreate demo-agent`. Suspension of the
`demo_agent` account is a second independent revocation mechanism.

## Backup and restore

Run `set -a; . ./.env; set +a; ./ops/backup.sh` only if shell-safe quoting is
present in the local environment, or schedule the script directly because its
database credentials are read inside the database container. Backups contain a
compressed SQL dump and uploads archive. See `ops/restore.md` before restoring.

## Update and rollback

1. Take a backup and record `git rev-parse HEAD` plus image IDs.
2. Update pinned HumHub and module versions in a branch.
3. Build and migrate on an isolated Compose project.
4. Deploy with `./deploy.sh`, then run QA.
5. Roll back by checking out the recorded commit, restoring matching database
   and uploads when a migration is not backward compatible, and recreating the
   services.

## Privacy-preserving metrics

Aggregate native database records for registrations, profile types, posts,
comments, reactions, follows, reports and suspensions. Human-agent interactions
are recorded in `human_agent_interaction_log`. Do not add third-party tracking
or store content in analytics exports.
