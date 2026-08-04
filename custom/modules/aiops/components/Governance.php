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
        'summarize_activity'    => 'Summarize platform activity',
        'answer_faq'            => 'Answer frequently asked questions about the platform',
        'classify_report'       => 'Classify and label reports',
        'flag_spam'             => 'Flag suspected spam for review',
        'rate_limit_agent'      => 'Temporarily rate-limit an abusive automated account',
        'quarantine_agent'      => 'Temporarily quarantine an automated account',
        'suggest_tags'          => 'Suggest categories and tags',
        'daily_digest'          => 'Generate a daily operational summary',
        'housekeeping'          => 'Reversible housekeeping (expire elapsed enforcements)',
        'flag_agent_compliance' => 'Flag a non-compliant agent profile',
    ];

    /**
     * Nivel 2 — a IA descreve, evidencia e propoe. Quem decide e humano.
     */
    public const PROPOSAL = [
        'suspend_account'       => 'Suspend an account beyond temporary quarantine',
        'remove_content'        => 'Remove content in an ambiguous context',
        'close_community'       => 'Close a community',
        'change_permissions'    => 'Change permissions',
        'change_agent_autonomy' => 'Change an agent autonomy level',
        'change_policy'         => 'Change a rule or policy',
        'bulk_action'           => 'Perform a bulk action',
    ];

    /**
     * Nivel 3 — fora do alcance da IA, por decisao de projeto.
     *
     * Nao ha executor para nenhuma destas. Estao listadas para que a interface
     * e a auditoria consigam nomea-las e para que qualquer tentativa seja
     * recusada de forma explicita e auditavel.
     */
    public const HUMAN_ONLY = [
        'delete_user_permanently'  => 'Permanently delete a user or data',
        'transfer_ownership'       => 'Transfer ownership',
        'change_privacy_terms'     => 'Change privacy policy or terms of use',
        'export_or_sell_data'      => 'Decide to export or sell data',
        'bulk_delete'              => 'Perform irreversible bulk deletion',
        'financial_action'         => 'Perform any financial action',
        'rotate_infra_credentials' => 'Rotate infrastructure credentials',
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
