<?php

namespace humhub\modules\aiops\components;

/**
 * Mapa de governanca da camada de operacao por IA.
 *
 * Esta classe e a fonte unica de verdade sobre o que a IA pode fazer sozinha,
 * o que ela so pode propor, e o que ela nunca pode tocar. Nenhum outro ponto do
 * modulo decide nivel: todos consultam aqui.
 *
 * Regra estrutural: capacidades de NIVEL 3 nao possuem caminho de execucao no
 * codigo. Nao existe um `else` que as execute. Se algo tentar executa-las, o
 * executor lanca excecao — a ausencia de implementacao e a protecao, nao um
 * `if` que alguem possa inverter depois.
 */
final class Governance
{
    /** Autonomo: a IA executa. Sempre reversivel e sempre com prazo. */
    public const LEVEL_AUTONOMOUS = 1;

    /** Proposta: a IA registra na fila; um humano aprova ou rejeita. */
    public const LEVEL_PROPOSAL = 2;

    /** Humano: a IA nunca executa e nunca propoe execucao automatica. */
    public const LEVEL_HUMAN_ONLY = 3;

    /**
     * Nivel 1 — observar, classificar, resumir e conter de forma reversivel.
     *
     * Toda contencao aqui expira sozinha (ver Enforcement::expires_at). Nenhuma
     * delas remove conteudo ou acesso de forma permanente.
     */
    public const AUTONOMOUS = [
        'summarize_activity'    => 'Resumir atividade da plataforma',
        'answer_faq'            => 'Responder duvidas frequentes sobre a plataforma',
        'classify_report'       => 'Classificar e etiquetar denuncias',
        'flag_spam'             => 'Sinalizar suspeita de spam para revisao',
        'rate_limit_agent'      => 'Limitar temporariamente conta automatizada abusiva',
        'quarantine_agent'      => 'Colocar conta automatizada em quarentena temporaria',
        'suggest_tags'          => 'Sugerir categorias e etiquetas',
        'daily_digest'          => 'Gerar resumo operacional diario',
        'housekeeping'          => 'Manutencao reversivel (expirar contencoes vencidas)',
        'flag_agent_compliance' => 'Sinalizar perfil de agente fora de conformidade',
    ];

    /**
     * Nivel 2 — a IA descreve, evidencia e propoe. Quem decide e humano.
     */
    public const PROPOSAL = [
        'suspend_account'       => 'Suspender conta alem da quarentena temporaria',
        'remove_content'        => 'Remover conteudo em contexto ambiguo',
        'close_community'       => 'Encerrar comunidade',
        'change_permissions'    => 'Alterar permissoes',
        'change_agent_autonomy' => 'Alterar nivel de autonomia de agente',
        'change_policy'         => 'Alterar regra ou politica',
        'bulk_action'           => 'Acao em lote',
    ];

    /**
     * Nivel 3 — fora do alcance da IA, por decisao de projeto.
     *
     * Nao ha executor para nenhuma destas. Estao listadas para que a interface
     * e a auditoria consigam nomea-las e para que qualquer tentativa seja
     * recusada de forma explicita e auditavel.
     */
    public const HUMAN_ONLY = [
        'delete_user_permanently'  => 'Exclusao permanente de usuario ou dados',
        'transfer_ownership'       => 'Mudanca de titularidade',
        'change_privacy_terms'     => 'Alteracao de privacidade ou termos de uso',
        'export_or_sell_data'      => 'Decisao de exportacao ou venda de dados',
        'bulk_delete'              => 'Exclusao irreversivel em lote',
        'financial_action'         => 'Qualquer acao financeira',
        'rotate_infra_credentials' => 'Troca de credenciais de infraestrutura',
    ];

    /**
     * Nivel de uma capacidade. Capacidade desconhecida cai em NIVEL 3 — o mais
     * restritivo — para que esquecer de mapear algo nunca abra permissao.
     */
    public static function levelFor(string $capability): int
    {
        if (isset(self::AUTONOMOUS[$capability])) {
            return self::LEVEL_AUTONOMOUS;
        }
        if (isset(self::PROPOSAL[$capability])) {
            return self::LEVEL_PROPOSAL;
        }

        return self::LEVEL_HUMAN_ONLY;
    }

    public static function label(string $capability): string
    {
        return self::AUTONOMOUS[$capability]
            ?? self::PROPOSAL[$capability]
            ?? self::HUMAN_ONLY[$capability]
            ?? $capability;
    }

    public static function all(): array
    {
        return self::AUTONOMOUS + self::PROPOSAL + self::HUMAN_ONLY;
    }

    /** A IA pode executar sem passar por humano? */
    public static function isAutonomous(string $capability): bool
    {
        return self::levelFor($capability) === self::LEVEL_AUTONOMOUS;
    }

    /** A IA pode no maximo propor? */
    public static function requiresApproval(string $capability): bool
    {
        return self::levelFor($capability) === self::LEVEL_PROPOSAL;
    }

    /** Reservado a humano — a IA nao encosta. */
    public static function isHumanOnly(string $capability): bool
    {
        return self::levelFor($capability) === self::LEVEL_HUMAN_ONLY;
    }

    /**
     * Teto de duracao de uma contencao autonoma, em segundos.
     *
     * Existe para que nenhuma acao de nivel 1 vire permanente por acidente:
     * mesmo que o worker morra, a contencao vence sozinha.
     */
    public const MAX_ENFORCEMENT_SECONDS = 86400;
}
