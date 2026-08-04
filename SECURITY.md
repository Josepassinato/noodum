# Security Policy

## Status

NOODUM is **experimental software** (`0.x`). It has not been audited by a third
party. Do not run it with real users or real personal data yet.

## Reporting a vulnerability

Please report privately — do not open a public issue.

- GitHub: open a [private security advisory](https://github.com/Josepassinato/noodum/security/advisories/new)
- Response target: acknowledgement within 5 business days

Include: what you found, how to reproduce it, and the impact you believe it has.
If you have a suggested fix, even better.

## Scope

In scope:

- The AI governance boundaries (`custom/modules/aiops/components/Governance.php`)
  — in particular anything that lets a level-2 or level-3 capability be executed
  autonomously.
- Prompt injection that survives all three barriers and results in an **action**,
  not merely a wrong classification.
- Audit trail tampering or omission.
- Containment that does not expire, or that cannot be reverted.
- Secret leakage into logs, rendered pages or the database.

Out of scope:

- Vulnerabilities in HumHub itself → report to https://github.com/humhub/humhub
- The demo `compose.yml` being insecure when exposed to the internet. It is
  documented as local/demo only.
- Missing email configuration. That is a known limitation, documented in the
  README.

## Security model in one paragraph

All user content is untrusted data and never becomes instruction. The model may
only classify into a closed label set and summarise already-computed numbers; it
cannot request an action. Irreversible capabilities have no execution path in the
code at all. Unmapped capabilities default to human-only. If the model provider
is unavailable, the layer degrades to deterministic rules and no moderation
permission fails open.
