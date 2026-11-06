<?php

namespace humhub\modules\aiops\services;

use humhub\modules\aiops\models\AuditEntry;
use humhub\modules\aiops\models\Enforcement;
use humhub\modules\aiops\models\Proposal;
use humhub\modules\aiops\Module;

/**
 * Resumo operacional diario.
 *
 * Os numeros vem sempre do banco, nunca do modelo. O modelo, quando disponivel,
 * apenas escreve um paragrafo de leitura sobre esses numeros ja apurados — se
 * ele estiver fora do ar, o digest sai completo, so sem a prosa.
 */
final class Digest
{
    public function __construct(private Module $module, private OperationsManager $manager)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function build(): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - 86400);

        $facts = [
            'period' => 'last 24h',
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'health' => $this->manager->healthSnapshot(),
            'autonomous_actions' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_EXECUTED])->count(),
            'observations' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_OBSERVED])->count(),
            'blocked_attempts' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_BLOCKED])->count(),
            'reversals' => AuditEntry::find()
                ->where(['>=', 'created_at', $since])
                ->andWhere(['result' => AuditEntry::RESULT_REVERTED])->count(),
            'proposals_pending' => Proposal::findPending()->count(),
            'proposals_created' => Proposal::find()->where(['>=', 'created_at', $since])->count(),
            'enforcements_active' => Enforcement::findActive()->count(),
            'agent_compliance' => (new AgentCompliance())->summary(),
        ];

        $facts['narrative'] = $this->narrate($facts);

        return $facts;
    }

    /**
     * Paragrafo de leitura. Recebe apenas numeros ja apurados — nenhum texto de
     * usuario chega ate aqui, entao esta chamada nao tem superficie de injecao.
     */
    private function narrate(array $facts): ?string
    {
        if (!$this->module->isCapabilityEnabled('daily_digest')) {
            return null;
        }

        $llm = $this->manager->getLlm();
        if (!$llm->isAvailable()) {
            return null;
        }

        $payload = json_encode([
            'health' => $facts['health'],
            'autonomous_actions' => $facts['autonomous_actions'],
            'observations' => $facts['observations'],
            'blocked_attempts' => $facts['blocked_attempts'],
            'reversals' => $facts['reversals'],
            'pending_proposals' => $facts['proposals_pending'],
            'active_enforcements' => $facts['enforcements_active'],
            'agent_compliance' => $facts['agent_compliance'],
        ], JSON_UNESCAPED_UNICODE);

        return $llm->summarize(
            $payload,
            'Write a short operational summary (maximum 5 sentences) for the administrator '
            . 'of a social network for humans and AI agents. Use only the supplied numbers. '
            . 'Do not invent data or suggest irreversible actions. Write in English.'
        );
    }

    /** Versao em texto, para log e leitura no terminal. */
    public function render(array $digest): string
    {
        $h = $digest['health'];
        $c = $digest['agent_compliance'];

        $lines = [
            '=== Operational digest — ' . $digest['generated_at'] . ' UTC (' . $digest['period'] . ') ===',
            sprintf('Network:     %d users | %d publications/24h | %d communities', $h['users_total'], $h['content_24h'], $h['spaces_total']),
            sprintf('Moderation:  %d open reports | %d active enforcements', $h['reports_open'], $digest['enforcements_active']),
            sprintf('AI:          %d autonomous actions | %d observations | %d reversals', $digest['autonomous_actions'], $digest['observations'], $digest['reversals']),
            sprintf('Governance:  %d pending proposals | %d created | %d blocked attempts', $digest['proposals_pending'], $digest['proposals_created'], $digest['blocked_attempts']),
            sprintf('Compliance:  %d profiles with issues (%d in grace period, %d actionable)', $c['total_issues'], $c['in_grace'], $c['actionable']),
            'Model:       ' . $h['llm'],
        ];

        if (!empty($digest['narrative'])) {
            $lines[] = '';
            $lines[] = $digest['narrative'];
        }

        return implode("\n", $lines);
    }
}
