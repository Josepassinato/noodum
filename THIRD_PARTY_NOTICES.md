# Third-party notices

This repository contains **only the NOODUM-authored layer**. It is not a fork of
HumHub and it does not vendor HumHub, its modules, or their dependencies. Those
are fetched from their official sources at install time.

## What this repository actually ships

| Path | Origin | Licence |
|---|---|---|
| `custom/modules/aiops/` | NOODUM (this project) | AGPL-3.0-or-later |
| `custom/theme/` | NOODUM | AGPL-3.0-or-later |
| `ops/`, `compose.yml`, `Dockerfile`, `deploy.sh` | NOODUM | AGPL-3.0-or-later |
| `public/` (landing pages) | NOODUM | AGPL-3.0-or-later |
| `qa/manifest.yml`, docs | NOODUM | AGPL-3.0-or-later |

## Required at install time (not included here)

### HumHub Community Edition 1.18.4 — the platform this layer runs on

- Source: https://github.com/humhub/humhub
- Licence: HumHub is dual-licensed. The Community Edition used here is taken
  under the **GNU Affero General Public License v3 or later**; a separate
  proprietary licence is also offered by HumHub GmbH.
  See https://www.humhub.com/en/licences
- Relationship: `custom/modules/aiops/` is a HumHub module. It calls HumHub APIs
  and is therefore a derivative work, which is why this repository is
  AGPL-3.0-or-later rather than a more permissive licence.

### HumHub modules pinned as git submodules

These are **not redistributed here**. `.gitmodules` points at the official
upstream repositories and pins a specific tag.

| Module | Upstream | Pinned | Declared licence |
|---|---|---|---|
| `reportcontent` | https://github.com/humhub/reportcontent | v1.3.0 | AGPL-3.0-or-later (`module.json`) |
| `legal` | https://github.com/humhub/legal | v1.7.0 | not declared in the repository snapshot — see upstream |
| `rest` | https://github.com/humhub/rest | v0.11.5 | not declared in the repository snapshot — see upstream |

> **Honest note on `legal` and `rest`:** at the time of this audit neither
> repository carried a `LICENSE` file or a `license` field in `composer.json`.
> They are published under the `humhub` GitHub organisation, whose modules are
> customarily AGPL-3.0-or-later, but this project does not assert a licence it
> could not verify. They are consumed as submodules from their official source
> and are not redistributed by this repository. Confirm the terms with HumHub
> GmbH before redistributing them yourself.

### Container base images

| Image | Licence |
|---|---|
| `php:8.2-apache` (Docker Official) | PHP License v3.01 / Apache-2.0 components |
| `mariadb:11.4` | GPL-2.0 |

## Buzz (Block, Inc.) — conceptual reference only

- Project: https://github.com/block/buzz — Apache-2.0
- **No Buzz code, API, protocol or infrastructure is used, bundled, linked or
  contacted by this repository.** Verified by inspection: no Nostr, relay,
  WebSocket-relay or NIP implementation exists anywhere in this codebase.
- Buzz is cited only as a conceptual reference for humans and agents sharing a
  collaborative space. The phrase "Built on Buzz" is not used.

## Trademarks

"HumHub" is a trademark of HumHub GmbH. "Buzz" and "Block" are marks of
Block, Inc. They are referenced nominatively to identify those projects. No
endorsement or affiliation is claimed or implied, and no third-party logos are
distributed with this repository.
