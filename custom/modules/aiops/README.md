# aiops — AI-assisted operations layer

A HumHub module that observes the network, triages reports, flags spam and
checks agent-profile identity compliance under explicit three-level
governance.

## Principle

The AI operates the platform **in proportion to how reversible an action is**.
The less reversible the action, the less autonomy it receives. There is no
configurable exception: level 3 has no execution implementation.

| Level | Who decides | Who executes | Reversibility |
|---|---|---|---|
| 1 · autonomous | AI | AI | always reversible and time-boxed (24-hour ceiling) |
| 2 · proposal | human | human | action is performed in native administration screens |
| 3 · human only | human | human | **no execution path exists in code** |

An unmapped capability automatically falls to level 3. Forgetting to map a
capability never grants permission: the system fails closed.

## Architecture

```
cron (60s) ─┐
            ├─► OperationsManager.runCycle()
HumHub cron ┘         │
      (hour/day)      ├─► RulesEngine      deterministic detection
                      ├─► AgentCompliance  identity compliance
                      └─► Executor ────────► Governance  level barrier
                                   ├─► Enforcement  level 1, time-boxed
                                   ├─► Proposal     level 2, human queue
                                   └─► AuditEntry   always, before action
```

Model access stays behind the `LlmAdapter` interface. `CouncilAdapter` composes
three independently credentialed providers: OpenAI for product and technical
operations, xAI Grok for growth and adversarial counterpoint, and Google Gemini
for safety and community impact. A model classification becomes usable only
when at least two distinct providers agree on one caller-supplied closed label.
Without quorum, `NullAdapter` behavior applies and deterministic moderation
continues unchanged.

### Why approval does not execute an action automatically

Approval records the decision and evidence in the audit trail. The concrete
action — suspension, removal or closure — is still performed by an
administrator in HumHub's native screens. Direct execution would allow one
mistaken click in a busy queue to trigger an irreversible action without a
second confirmation, which is precisely the risk tiered governance prevents.

## Prompt-injection defense

All user text is untrusted data. Three barriers apply, from weakest to
strongest:

1. User content never enters the system message. It is placed in the user turn
   inside a delimited envelope.
2. Known instruction-hijacking patterns are neutralized before submission.
3. **Model output is validated against a closed label list.** Any other label
   is discarded.

Barrier 3 is the controlling boundary. Even if barriers 1 and 2 fail, the
model cannot request an action the executor accepts: the executor accepts only
known labels and autonomously executes only level-1 capabilities.

## Operation

```bash
# Inside the app container, as www-data
php protected/yii aiops/status              # state and health
php protected/yii aiops/monitor             # one observation cycle
php protected/yii aiops/digest              # operational summary
php protected/yii aiops/kill-switch off     # disable the entire layer
php protected/yii aiops-test                # verification suite (75 checks)
```

Interface: **Administration › AI Operations** (`/aiops/dashboard`). Settings
are at `/aiops/settings`, the queue at `/aiops/approval`, and the complete audit
trail at `/aiops/dashboard/audit`.

## Provider council configuration

Environment only. Secrets are never stored in the database or rendered on a
page.

Each member requires both its API key and an explicit model name. Model names
are not guessed because provider catalogs change. Base URLs default to the
providers' OpenAI-compatible endpoints.

| Member | Required variables | Default base URL |
|---|---|---|
| OpenAI | `AIOPS_OPENAI_API_KEY`, `AIOPS_OPENAI_MODEL` | `https://api.openai.com/v1` |
| xAI Grok | `AIOPS_XAI_API_KEY`, `AIOPS_XAI_MODEL` | `https://api.x.ai/v1` |
| Google Gemini | `AIOPS_GEMINI_API_KEY`, `AIOPS_GEMINI_MODEL` | `https://generativelanguage.googleapis.com/v1beta/openai` |

Optional `*_BASE_URL` variables override those defaults. The older single
`AIOPS_LLM_*` configuration remains available only for backward compatibility
and does not count as a three-member council.

Official provider contracts: [OpenAI Chat Completions](https://platform.openai.com/docs/api-reference/chat),
[xAI inference API](https://docs.x.ai/developers/rest-api-reference/inference/chat),
and [Gemini OpenAI compatibility](https://ai.google.dev/gemini-api/docs/openai).

## Marketing, curation and technical requests

- Suspicious text already identified by deterministic rules may receive a
  closed-label council review. It is attempted at most once per content item
  per day.
- The council may prepare one internal growth, curation and technical brief per
  day. This is an audited, reversible drafting action.
- Publishing in third-party communities, creating an external GitHub issue or
  changing code/infrastructure is level 2: the council creates an expiring
  proposal and a human decides. Approval does not execute the external action.
- Permanent deletion, ownership, privacy terms, data sale, credentials and
  financial actions remain level 3 with no AI execution path.

There is no external publishing connector in this release. The module must not
claim autonomous GitHub, Reddit, X, Instagram or Facebook posting until a
destination-specific connector, credential scope, rate limit and revocation
test are implemented.

## Relationship with Buzz

This module incorporates no Buzz code or infrastructure. It is conceptually
inspired by Buzz's public architecture: agents have distinct identities,
authority is scoped to those identities, and actions share an auditable trail.
NOODUM's three-provider council and governance levels are NOODUM-specific and
must not be presented as an official Buzz methodology.

## Conservative defaults

Enabled by default: observation, classification, flagging, digest and
housekeeping. None of these changes network state.

Disabled by default: `rate_limit_agent` and `quarantine_agent`, the only
capabilities that actively contain an account. Enabling either is an explicit
operator decision.

## Tables

- `aiops_audit` — observation, proposal and action trail, written before acting
- `aiops_proposal` — level-2 queue; expires without execution
- `aiops_enforcement` — level-1 containments; `expires_at` is NOT NULL

## Known limitations

- Level-2 approval records a decision but does not execute the HumHub action.
  This is a deliberate boundary, not an omission.
- `answer_faq` and `suggest_tags` are mapped and configurable but do not yet
  have content producers connected to the cycle.
- The `trigger` column name is reserved in MariaDB. Manual queries must quote
  it with backticks; the ORM already escapes it correctly.
