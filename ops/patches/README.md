# Patches to third-party modules

`custom/modules/legal`, `custom/modules/rest`, and `custom/modules/reportcontent`
are git submodules of upstream HumHub repositories. A local commit there is not
ours to push, and `git submodule update` will drop it.

There are no active patches in this directory.

## Check compatibility before patching

Compare the module's `minVersion` and `maxVersion` with the HumHub core you
actually run before treating upstream as buggy.

This layer pins HumHub **1.18.4**. The gitlinks match that core:

| Module | Pin | Why |
|---|---|---|
| `legal` | `v1.6.2` | `maxVersion` 1.18. `v1.7.0` requires core 1.19 and uses a different user-event type, which returns HTTP 500 on registration. |
| `reportcontent` | `v1.2.3` | `maxVersion` 1.18. `v1.3.x` requires core 1.19. |
| `rest` | `v0.11.5` | `maxVersion` 1.18. |

The `v1.6.2` and `v1.7.0` tags of `legal` ship the same migrations, so the pin
does not change schema. Do not patch 1.7.0 to make it run on 1.18.

```bash
cd custom/modules/<module>
git fetch origin
git show origin/master:module.json | grep -E 'version|Version'
```

## Bind mounts

Some module files are bind-mounted into the container. An editor that writes to
a temporary file and renames it changes the inode, and the container keeps
serving the old file. After an edit:

```bash
docker compose up -d --force-recreate app cron demo-agent
```
