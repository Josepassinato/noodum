# Versioning policy

While the project remains in `0.x`, **compatibility is not guaranteed**.
Schemas, routes and capability names may change between minor versions, and
not every change will include an automatic migration.

- `0.x.y` — experimental. A breaking change may ship in `x`.
- `1.0.0` — only after the complete signup flow, including email, is verified
  end to end and the AI layer has received external review.
- Strict SemVer applies from `1.0.0` onward.

Every incompatible change must be announced in `CHANGELOG.md` with the
`BREAKING` label and a migration note.

Tags follow `vMAJOR.MINOR.PATCH`.
