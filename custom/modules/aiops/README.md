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

The LLM is used only by `classify()` and `summarize()`, behind the
`LlmAdapter` interface. Without a configured key, the module uses
`NullAdapter` and operates entirely deterministically. No moderation function
depends on the model.

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

## Provider configuration

Environment only. Secrets are never stored in the database or rendered on a
page.

| Variable | Effect |
|---|---|
| `AIOPS_LLM_PROVIDER` | empty/`none` = deterministic; `openai` = compatible endpoint |
| `AIOPS_LLM_API_KEY` | key; without it the adapter reports itself unavailable |
| `AIOPS_LLM_BASE_URL` | default `https://api.openai.com/v1` |
| `AIOPS_LLM_MODEL` | default `gpt-4o-mini` |

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
