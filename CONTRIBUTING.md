# Contributing

Thanks for looking. This is an experimental project — expect rough edges and
say so when you find them.

## Before you start

Read [`qa/manifest.yml`](qa/manifest.yml). It is the product contract: roles,
expected behaviours and — importantly — **anti-behaviours**. A change that
enables an anti-behaviour will not be merged, however well written.

## What this repository is

The NOODUM layer only. HumHub is **not** vendored here. To work on the code you
need a running HumHub 1.18.4 with this layer mounted — `docker compose up -d`
does that for you.

## Ground rules for the AI layer

These are not style preferences; they are the reason the project exists.

1. **Never widen autonomy silently.** Moving a capability from level 2 to level 1
   requires an explicit justification in the PR describing how the action is
   reversible and time-boxed.
2. **Never add an execution path for a level-3 capability.** Not behind a flag,
   not behind a setting, not "temporarily".
3. **Never let model output become an action.** The model classifies into a
   closed label set and summarises numbers. That is all.
4. **Audit before acting.** Write the trail entry first; if the action fails, the
   entry records the failure.
5. **Every autonomous containment expires.** No exceptions, no configuration that
   removes the ceiling.

## Development

```bash
cp .env.example .env      # replace every replace-with-* value
docker compose up -d
docker compose exec app su -s /bin/sh www-data -c \
  "php protected/yii module/enable aiops && \
   php protected/yii migrate/up --include-module-migrations=1 --interactive=0"
```

## Tests

Run before every PR — they must be green:

```bash
docker compose exec app su -s /bin/sh www-data -c "php protected/yii aiops-test"
```

If you touch governance, containment, the approval workflow or the sanitiser,
**add a check to `custom/modules/aiops/commands/SelfTestController.php`**. A PR
that changes a boundary without adding a check that would fail if the boundary
broke is incomplete.

## Commits and PRs

- Conventional-commit style: `feat(aiops): ...`, `fix(ops): ...`, `docs: ...`
- One logical change per commit
- Describe what changed **and what you verified**, with the actual output
- If you found a problem you did not fix, say so in the PR

## Licence of contributions

By contributing you agree your work is licensed under
[AGPL-3.0-or-later](LICENSE), the same terms as the project.

If your contribution is authored wholly or partly by an AI agent, say so in the
PR description and name the human or organisation responsible for it. That is
the same standard the network itself applies to agent profiles.
