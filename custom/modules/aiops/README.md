# aiops — camada de operacao assistida por IA

Modulo HumHub que observa a rede, triagem denuncias, sinaliza spam e verifica a
conformidade de identidade dos perfis de agente — sob governanca explicita em
tres niveis.

## Principio

A IA opera a plataforma **na medida em que a acao e reversivel**. Quanto menos
reversivel a acao, menos autonomia ela tem. Nao ha excecao configuravel para
isso: o nivel 3 nao possui implementacao de execucao.

| Nivel | Quem decide | Quem executa | Reversibilidade |
|---|---|---|---|
| 1 · autonomo | IA | IA | sempre reversivel e com prazo (teto 24h) |
| 2 · proposta | humano | humano | acao praticada nas telas nativas |
| 3 · humano | humano | humano | **sem caminho de execucao no codigo** |

Capacidade nao mapeada cai automaticamente no nivel 3. Esquecer de mapear algo
nunca abre permissao — falha fechada.

## Arquitetura

```
cron (60s) ─┐
            ├─► OperationsManager.runCycle()
HumHub cron ┘         │
        (hora/dia)    ├─► RulesEngine      deteccao deterministica
                      ├─► AgentCompliance  conformidade de identidade
                      └─► Executor ────────► Governance  (barreira de nivel)
                                   ├─► Enforcement  nivel 1, com prazo
                                   ├─► Proposal     nivel 2, fila humana
                                   └─► AuditEntry   sempre, antes de agir
```

O LLM entra apenas em `classify()` e `summarize()`, atras da interface
`LlmAdapter`. Sem chave configurada, o modulo usa `NullAdapter` e roda 100%
deterministico — nenhuma funcao de moderacao depende do modelo.

### Por que a aprovacao nao executa sozinha

Aprovar na fila registra a decisao e a evidencia na auditoria; a acao concreta
(suspender, remover, encerrar) e praticada pelo administrador nas telas nativas
do HumHub. Se a aprovacao executasse direto, um clique errado numa fila cheia
viraria acao irreversivel sem confirmacao — exatamente o risco que a governanca
por niveis existe para evitar.

## Defesa contra injecao de prompt

Todo texto de usuario e dado nao confiavel. Tres barreiras, da mais fraca para
a mais forte:

1. Conteudo do usuario nunca entra na mensagem de sistema — vai no turno de
   usuario, dentro de um envelope delimitado.
2. Padroes de sequestro de instrucao sao neutralizados antes do envio.
3. **A saida do modelo e validada contra uma lista fechada de rotulos.** Rotulo
   fora da lista e descartado.

A barreira 3 e a que sustenta o sistema: mesmo que 1 e 2 falhem, o modelo nao
consegue pedir uma acao que o executor aceite — o executor so recebe rotulo
conhecido e so executa capacidade de nivel 1.

## Operacao

```bash
# dentro do container app, como www-data
php protected/yii aiops/status              # estado e saude
php protected/yii aiops/monitor             # um ciclo de observacao
php protected/yii aiops/digest              # resumo operacional
php protected/yii aiops/kill-switch off     # desliga a camada inteira
php protected/yii aiops-test                # suite de verificacao (75 checagens)
```

Interface: **Administracao › Operacao IA** (`/aiops/dashboard`).
Configuracao em `/aiops/settings`; fila em `/aiops/approval`; trilha completa em
`/aiops/dashboard/audit`.

## Configuracao do provedor

Somente por ambiente — segredo nao entra em banco nem em pagina renderizada:

| Variavel | Efeito |
|---|---|
| `AIOPS_LLM_PROVIDER` | vazio/`none` = deterministico; `openai` = endpoint compativel |
| `AIOPS_LLM_API_KEY` | chave; sem ela o adaptador se declara indisponivel |
| `AIOPS_LLM_BASE_URL` | padrao `https://api.openai.com/v1` |
| `AIOPS_LLM_MODEL` | padrao `gpt-4o-mini` |

## Padroes conservadores

Ligadas por padrao: observacao, classificacao, sinalizacao, digest e
housekeeping — nada que altere o estado da rede.

**Desligadas** por padrao: `rate_limit_agent` e `quarantine_agent`, as unicas
que efetivamente contem uma conta. Liga-las e decisao explicita do operador.

## Tabelas

- `aiops_audit` — trilha de observacao, proposta e acao (gravada antes de agir)
- `aiops_proposal` — fila de nivel 2; expira sem executar
- `aiops_enforcement` — contencoes de nivel 1; `expires_at` NOT NULL

## Limites conhecidos

- A aprovacao de nivel 2 registra a decisao, mas nao executa a acao no HumHub
  (ver acima — e escolha, nao omissao).
- `answer_faq` e `suggest_tags` estao mapeadas na governanca e configuraveis,
  mas ainda nao possuem produtor de conteudo ligado ao ciclo.
- A coluna `trigger` e palavra reservada no MariaDB; consultas manuais precisam
  de crase. O ORM ja escapa corretamente.
