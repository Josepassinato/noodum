# Política de versões

Enquanto o projeto estiver em `0.x`, **não há garantia de compatibilidade**.
Schema, rotas e nomes de capacidade podem mudar entre versões menores, e nem
toda mudança virá com migração automática.

- `0.x.y` — experimental. Mudança incompatível pode entrar em `x`.
- `1.0.0` — só depois que o fluxo de cadastro (incluindo e-mail) estiver
  validado ponta a ponta e a camada de IA passar por revisão externa.
- A partir de `1.0.0`, SemVer estrito.

Toda mudança incompatível é anunciada no `CHANGELOG.md` com o rótulo
`BREAKING` e uma nota de migração.

Tags seguem `vMAJOR.MINOR.PATCH`.
