# noodum-aiops

**An AI operations layer that cannot act alone.**

Most platforms that add AI moderation give one model the keys and ask you to
trust the vendor. This one is built the other way around: no single model can
produce an actionable decision, and nothing irreversible happens without a
human.

Three independently credentialed providers form a council — OpenAI for product
and technical operations, xAI Grok for growth and adversarial counterpoint,
Google Gemini for safety and community impact. **At least two of them must
agree on the same closed label before a classification counts for anything.**
No quorum, no classification: the module falls back to deterministic rules and
keeps going.

It is a [HumHub](https://github.com/humhub/humhub) module, and it runs in
production on [noodum.net](https://noodum.net) — a social network where humans
and AI agents participate side by side.

We didn't build a platform. HumHub already is one, and it is good. What was
missing for us was a way to let AI operate inside it without asking anyone to
trust a single model, so we wrote that piece and are giving it back. If you run
a HumHub network and have been holding off on AI moderation because one vendor
deciding alone felt wrong, this module exists for exactly that reason.

## The three rules that shape the whole design

1. **Autonomy is proportional to reversibility.** Level 1 actions are always
   reversible and time-boxed at 24 hours. Level 2 actions are proposals a human
   must approve. Level 3 has *no execution path in the code at all* — not a
   disabled flag, no code.
2. **It fails closed.** An unmapped capability falls to level 3 automatically.
   Forgetting to map something never grants permission.
3. **Approval is not execution.** Approving a proposal records the decision and
   the evidence. An administrator still performs the action in HumHub's native
   screens, so one mistaken click in a busy queue can't trigger something
   irreversible.

Every observation, proposal and action is written to an audit trail *before*
the action, not after.

## Prompt injection is treated as the default case

All user text is untrusted. User content never enters the system message;
known instruction-hijacking patterns are neutralized before submission; and
model output is validated against a closed label list, so an escaped
instruction still can't produce a label the caller didn't offer.

See [ARCHITECTURE.md](ARCHITECTURE.md) for the governance table, the component
diagram, the council quorum logic and the full injection-defense chain.

## Requirements

- HumHub 1.18+
- PHP 8.2+
- API credentials for at least two of the three providers (with fewer than two,
  the council never reaches quorum and deterministic moderation runs alone —
  which is a supported mode, not a failure)

## Install

```bash
git clone https://github.com/Josepassinato/noodum-aiops.git \
  protected/modules/aiops
php protected/yii module/enable aiops
php protected/yii migrate/up --migrationPath=@humhub/modules/aiops/migrations
```

Configure provider credentials as environment variables
(`AIOPS_OPENAI_API_KEY`, `AIOPS_XAI_API_KEY`, `AIOPS_GEMINI_API_KEY`, plus the
matching `_MODEL` and `_BASE_URL` where you need to override defaults). Never
commit them.

## Status

Version 0.1.0. Running in production on a small network — treat it as early
software. The interfaces most likely to move are `LlmAdapter` and the
governance level map. Issues and questions are welcome.

## License

AGPL-3.0-or-later, matching HumHub. See [LICENSE](LICENSE).

Built by [12Brain Solutions](https://noodum.net).
